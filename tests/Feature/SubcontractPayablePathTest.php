<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Finance\Database\Seeders\ConstructionLedgerSeeder;
use Modules\Finance\Models\Journal;
use Modules\Finance\Services\OutboxRelay;
use Modules\Payables\Actions\ApproveSubcontractorBill;
use Modules\Payables\Models\VendorBill;
use Modules\Platform\Models\Company;
use Modules\Platform\Models\NumberingSeries;
use Modules\Procurement\Models\Vendor;
use Modules\Projects\Models\Project;

uses(RefreshDatabase::class);

/**
 * The procure-to-pay money path, end to end — the payables mirror of the termin
 * path: subcontractor bill -> ApproveSubcontractorBill (bill math + PPh resolution)
 * -> outbox -> OutboxRelay -> PostingRuleEngine -> balanced accrual in the GL.
 */
it('approves a subcontractor bill and posts a balanced accrual via the outbox', function () {
    $company = Company::create([
        'code' => 'KON', 'name' => 'PT Kontraktor Uji', 'npwp' => '01.234.567.8-901.000',
        'is_pkp' => true, 'base_currency' => 'IDR', 'sbu_class' => 'medium_large_spec',
    ]);

    foreach (['journal', 'vendor_bill'] as $key) {
        NumberingSeries::create([
            'company_id' => $company->id, 'key' => $key,
            'format' => strtoupper(substr($key, 0, 3)).'-{YYYY}-{#####}', 'next' => 1, 'period_scope' => 'year',
        ]);
    }

    (new ConstructionLedgerSeeder)->seedForCompany($company->id);

    $project = Project::create([
        'company_id' => $company->id, 'code' => 'PRJ-001', 'name' => 'Pabrik Uji',
        'contract_number' => 'C-001', 'contract_date' => '2026-03-01', // post-2022 -> PP 9/2022
        'service_class' => 'integrated_work', 'contract_value_minor' => 10_000_000_000,
        'currency' => 'IDR', 'retention_percent' => 5, 'uang_muka_percent' => 20, 'status' => 'active',
    ]);

    // A subcontractor with a small (kecil) SBU doing pelaksanaan konstruksi -> 1.75%.
    $vendor = Vendor::create([
        'company_id' => $company->id, 'code' => 'SUB-01', 'name' => 'CV Sub Uji',
        'npwp' => '02.345.678.9-012.000', 'sbu_class' => 'small', 'is_pkp' => true,
    ]);

    $bill = VendorBill::create([
        'company_id' => $company->id, 'vendor_id' => $vendor->id, 'project_id' => $project->id,
        'bill_date' => '2026-07-04', 'contract_date' => '2026-03-01', // post-2022 -> PP 9/2022
        'status' => 'draft', 'service_class' => 'construction_work',
        'retention_percent' => 5, 'cost_code' => 'SUB', 'work_value_minor' => 500_000, 'currency' => 'IDR',
    ]);

    app(ApproveSubcontractorBill::class)->execute($bill);

    // The bill's frozen figures match the worked example.
    $bill->refresh();
    expect($bill->status)->toBe('approved')
        ->and($bill->ppn_input_minor)->toBe(55_000)         // 11%
        ->and($bill->retention_minor)->toBe(25_000)         // 5%
        ->and($bill->pph_withheld_minor)->toBe(8_750)       // 1.75% small SBU, pelaksanaan
        ->and($bill->net_payable_minor)->toBe(521_250)
        ->and($bill->gross_minor)->toBe(555_000);

    // Relaying the outbox posts exactly one balanced accrual journal.
    $processed = app(OutboxRelay::class)->drain();
    expect($processed)->toBe(1);

    $journal = Journal::with('lines')->where('source_reference', $bill->id)->firstOrFail();
    $debits = $journal->lines->sum('debit_minor');
    $credits = $journal->lines->sum('credit_minor');
    expect($debits)->toBe($credits)
        ->and($debits)->toBe(555_000)                       // work + PPN
        ->and($journal->fact_type)->toBe('payables.vendor_bill_approved');

    // The cost line carries the project dimension so subcontract spend is sliceable.
    $costLine = $journal->lines->firstWhere('account_code', '5101');
    expect($costLine->debit_minor)->toBe(500_000)
        ->and($costLine->project_id)->toBe($project->id)
        ->and($costLine->cost_code)->toBe('SUB');
});
