<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Billing\Actions\IssueTerminInvoice;
use Modules\Billing\Models\ProgressClaim;
use Modules\Finance\Database\Seeders\ConstructionLedgerSeeder;
use Modules\Finance\Models\Journal;
use Modules\Finance\Services\OutboxRelay;
use Modules\Payables\Actions\ApproveSubcontractorBill;
use Modules\Payables\Actions\PayVendorBills;
use Modules\Payables\Models\VendorBill;
use Modules\Platform\Models\Company;
use Modules\Platform\Models\NumberingSeries;
use Modules\Procurement\Models\Vendor;
use Modules\Projects\Models\Project;
use Modules\Receivables\Actions\ReceiveCustomerPayment;
use Modules\Receivables\Actions\ReleaseRetention;
use Modules\Receivables\Models\ArInvoice;
use Modules\Receivables\Models\ArRetention;

uses(RefreshDatabase::class);

function karyaSettlementCompany(array $numberingKeys): Company
{
    $company = Company::create([
        'code' => 'KON', 'name' => 'PT Kontraktor Uji', 'npwp' => '01.234.567.8-901.000',
        'is_pkp' => true, 'base_currency' => 'IDR', 'sbu_class' => 'medium_large_spec',
    ]);
    foreach ($numberingKeys as $key) {
        NumberingSeries::create([
            'company_id' => $company->id, 'key' => $key,
            'format' => strtoupper(substr($key, 0, 3)).'-{YYYY}-{#####}', 'next' => 1, 'period_scope' => 'year',
        ]);
    }
    (new ConstructionLedgerSeeder)->seedForCompany($company->id);

    return $company;
}

function karyaSettlementProject(Company $company): Project
{
    return Project::create([
        'company_id' => $company->id, 'code' => 'PRJ-001', 'name' => 'Pabrik Uji',
        'contract_number' => 'C-001', 'contract_date' => '2026-03-01',
        'service_class' => 'integrated_work', 'contract_value_minor' => 10_000_000_000,
        'currency' => 'IDR', 'retention_percent' => 5, 'uang_muka_percent' => 20, 'status' => 'active',
    ]);
}

it('settles a vendor bill: Dr Accounts Payable / Cr Bank', function () {
    $company = karyaSettlementCompany(['journal', 'vendor_bill']);
    $project = karyaSettlementProject($company);
    $vendor = Vendor::create([
        'company_id' => $company->id, 'code' => 'SUB-01', 'name' => 'CV Sub Uji',
        'npwp' => '02.345.678.9-012.000', 'sbu_class' => 'small', 'is_pkp' => true,
    ]);
    $bill = VendorBill::create([
        'company_id' => $company->id, 'vendor_id' => $vendor->id, 'project_id' => $project->id,
        'bill_date' => '2026-07-04', 'contract_date' => '2026-03-01', 'status' => 'draft',
        'service_class' => 'construction_work', 'retention_percent' => 5, 'cost_code' => 'SUB',
        'work_value_minor' => 500_000, 'currency' => 'IDR',
    ]);
    app(ApproveSubcontractorBill::class)->execute($bill);
    app(OutboxRelay::class)->drain(); // accrual posted; net payable = 521.250

    $batch = app(PayVendorBills::class)->execute($company->id, [$bill->id], '2026-07-31');
    expect($bill->refresh()->status)->toBe('paid')
        ->and((int) $batch->total_minor)->toBe(521_250);

    expect(app(OutboxRelay::class)->drain())->toBe(1);
    $journal = Journal::with('lines')->where('fact_type', 'payables.payment_made')->firstOrFail();
    expect($journal->lines->sum('debit_minor'))->toBe($journal->lines->sum('credit_minor'))
        ->and($journal->lines->firstWhere('account_code', '2101')->debit_minor)->toBe(521_250)
        ->and($journal->lines->firstWhere('account_code', '1101')->credit_minor)->toBe(521_250);
});

it('records AR from a termin, receives cash, and releases retention at FHO', function () {
    $company = karyaSettlementCompany(['journal', 'progress_claim']);
    $project = karyaSettlementProject($company);
    $claim = ProgressClaim::create([
        'company_id' => $company->id, 'project_id' => $project->id, 'sequence' => 1,
        'claim_date' => '2026-07-04', 'status' => 'bapp', 'work_value_minor' => 1_000_000, 'currency' => 'IDR',
    ]);
    app(IssueTerminInvoice::class)->execute($claim);
    app(OutboxRelay::class)->drain(); // posts AR journal + records the AR sub-ledger

    // The AR sub-ledger was built from the termin fact.
    $invoice = ArInvoice::where('source_claim_id', $claim->id)->firstOrFail();
    $retention = ArRetention::where('source_claim_id', $claim->id)->firstOrFail();
    expect((int) $invoice->net_minor)->toBe(833_500)
        ->and((int) $retention->amount_minor)->toBe(50_000);

    // Customer pays the net receivable → Dr Bank / Cr AR; invoice marked paid.
    app(ReceiveCustomerPayment::class)->execute($invoice, 833_500, '2026-08-01');
    expect($invoice->refresh()->status)->toBe('paid');
    expect(app(OutboxRelay::class)->drain())->toBe(1);
    $receiptJournal = Journal::with('lines')->where('fact_type', 'receivables.receipt_received')->firstOrFail();
    expect($receiptJournal->lines->firstWhere('account_code', '1101')->debit_minor)->toBe(833_500)
        ->and($receiptJournal->lines->firstWhere('account_code', '1102')->credit_minor)->toBe(833_500);

    // Retention released at final hand-over → Dr Bank / Cr Retention Receivable.
    app(ReleaseRetention::class)->execute($retention, '2027-01-15');
    expect($retention->refresh()->status)->toBe('released');
    expect(app(OutboxRelay::class)->drain())->toBe(1);
    $relJournal = Journal::with('lines')->where('fact_type', 'receivables.retention_released')->firstOrFail();
    expect($relJournal->lines->firstWhere('account_code', '1101')->debit_minor)->toBe(50_000)
        ->and($relJournal->lines->firstWhere('account_code', '1103')->credit_minor)->toBe(50_000);
});
