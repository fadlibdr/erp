<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Finance\Actions\CloseFiscalPeriod;
use Modules\Finance\Database\Seeders\ConstructionLedgerSeeder;
use Modules\Finance\Domain\Ledger\JournalDraft;
use Modules\Finance\Domain\Ledger\JournalLineDraft;
use Modules\Finance\Models\FiscalPeriod;
use Modules\Finance\Models\Journal;
use Modules\Finance\Models\RevrecRun;
use Modules\Finance\Services\LedgerPosting;
use Modules\Finance\Services\OutboxRelay;
use Modules\Payables\Actions\ApproveSubcontractorBill;
use Modules\Payables\Models\VendorBill;
use Modules\Platform\Domain\Currency;
use Modules\Platform\Domain\Money;
use Modules\Platform\Models\Company;
use Modules\Platform\Models\NumberingSeries;
use Modules\Procurement\Models\Vendor;
use Modules\Projects\Models\Project;

uses(RefreshDatabase::class);

/**
 * Month-end close — the highest-risk workflow. A subcontract cost lands in the GL,
 * then the close recognises PSAK 72 revenue for the project and locks the period so
 * nothing else can post into it.
 */
function karyaCloseFixture(): array
{
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
        'contract_number' => 'C-001', 'contract_date' => '2026-03-01',
        'service_class' => 'integrated_work', 'contract_value_minor' => 10_000_000_000,
        'currency' => 'IDR', 'retention_percent' => 5, 'uang_muka_percent' => 20, 'status' => 'active',
    ]);
    $vendor = Vendor::create([
        'company_id' => $company->id, 'code' => 'SUB-01', 'name' => 'CV Sub Uji',
        'npwp' => '02.345.678.9-012.000', 'sbu_class' => 'small', 'is_pkp' => true,
    ]);

    // Book Rp 500.000 of subcontract cost into the GL (expense 5101) → cost-to-date.
    $bill = VendorBill::create([
        'company_id' => $company->id, 'vendor_id' => $vendor->id, 'project_id' => $project->id,
        'bill_date' => '2026-07-04', 'contract_date' => '2026-03-01', 'status' => 'draft',
        'service_class' => 'construction_work', 'retention_percent' => 5, 'cost_code' => 'SUB',
        'work_value_minor' => 500_000, 'currency' => 'IDR',
    ]);
    app(ApproveSubcontractorBill::class)->execute($bill);
    app(OutboxRelay::class)->drain();

    $period = FiscalPeriod::create([
        'company_id' => $company->id, 'label' => '2026-07',
        'starts_on' => '2026-07-01', 'ends_on' => '2026-07-31', 'status' => 'open',
    ]);

    return [$company, $project, $period];
}

it('recognises PSAK 72 revenue and locks the period', function () {
    [$company, $project, $period] = karyaCloseFixture();

    $runs = app(CloseFiscalPeriod::class)->execute($company->id, '2026-07', '2026-07-31', [[
        'project_id' => $project->id,
        'contract_value_minor' => 10_000_000_000,
        'estimated_total_cost_minor' => 8_000_000_000,
        'billed_to_date_minor' => 0,
    ]]);

    // POC = 500.000 / 8.000.000.000 = 62 ppm; recognised = 10bn × 62/1e6 = 620.000.
    expect($runs)->toHaveCount(1);
    $run = RevrecRun::firstOrFail();
    expect($run->poc_ratio_ppm)->toBe(62)
        ->and($run->recognized_to_date_minor)->toBe(620_000)
        ->and($run->contract_asset_minor)->toBe(620_000)
        ->and($run->contract_liability_minor)->toBe(0)
        ->and($run->journal_id)->not->toBeNull();

    // The recognition journal is balanced: Dr Contract Asset (1171) / Cr Revenue (4101).
    $journal = Journal::with('lines')->where('fact_type', 'finance.revenue_recognized')->firstOrFail();
    expect($journal->lines->sum('debit_minor'))->toBe($journal->lines->sum('credit_minor'))
        ->and($journal->lines->firstWhere('account_code', '1171')->debit_minor)->toBe(620_000)
        ->and($journal->lines->firstWhere('account_code', '4101')->credit_minor)->toBe(620_000);

    expect($period->refresh()->status)->toBe('closed');
});

it('refuses to close an already-closed period and blocks late posts', function () {
    [$company, $project, $period] = karyaCloseFixture();

    $projects = [[
        'project_id' => $project->id, 'contract_value_minor' => 10_000_000_000,
        'estimated_total_cost_minor' => 8_000_000_000, 'billed_to_date_minor' => 0,
    ]];
    app(CloseFiscalPeriod::class)->execute($company->id, '2026-07', '2026-07-31', $projects);

    // Re-closing is refused.
    expect(fn () => app(CloseFiscalPeriod::class)->execute($company->id, '2026-07', '2026-07-31', $projects))
        ->toThrow(RuntimeException::class);

    // A journal dated inside the closed period is rejected by the FiscalPeriodGuard.
    $idr = Currency::IDR;
    $draft = new JournalDraft('coba posting terlambat', [
        JournalLineDraft::debit('1102', Money::of(1_000, $idr)),
        JournalLineDraft::credit('4101', Money::of(1_000, $idr)),
    ]);
    expect(fn () => app(LedgerPosting::class)->post($company->id, $draft, '2026-07-20'))
        ->toThrow(RuntimeException::class);
});
