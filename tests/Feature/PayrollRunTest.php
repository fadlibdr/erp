<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Finance\Database\Seeders\ConstructionLedgerSeeder;
use Modules\Finance\Models\Journal;
use Modules\Finance\Services\OutboxRelay;
use Modules\Payroll\Actions\RunPayroll;
use Modules\Payroll\Models\Employee;
use Modules\Platform\Models\Company;
use Modules\Platform\Models\NumberingSeries;
use Modules\Projects\Models\Project;

uses(RefreshDatabase::class);

/**
 * A monthly payroll run decomposes each employee (PPh 21 TER + BPJS), charges the
 * gross to the project as labor cost, and posts a balanced journal via the outbox —
 * the same seam every money path uses.
 */
it('runs payroll and posts a balanced labor-cost journal', function () {
    $company = Company::create([
        'code' => 'KON', 'name' => 'PT Kontraktor Uji', 'npwp' => '01.234.567.8-901.000',
        'is_pkp' => true, 'base_currency' => 'IDR', 'sbu_class' => 'medium_large_spec',
    ]);
    NumberingSeries::create([
        'company_id' => $company->id, 'key' => 'journal',
        'format' => 'JRN-{YYYY}-{#####}', 'next' => 1, 'period_scope' => 'year',
    ]);
    (new ConstructionLedgerSeeder)->seedForCompany($company->id);

    $project = Project::create([
        'company_id' => $company->id, 'code' => 'PRJ-001', 'name' => 'Pabrik Uji',
        'contract_number' => 'C-001', 'contract_date' => '2026-03-01', 'service_class' => 'integrated_work',
        'contract_value_minor' => 10_000_000_000, 'currency' => 'IDR',
        'retention_percent' => 5, 'uang_muka_percent' => 20, 'status' => 'active',
    ]);

    $e1 = Employee::create([
        'company_id' => $company->id, 'code' => 'EMP-1', 'name' => 'Budi', 'ptkp_status' => 'TK/0',
        'monthly_gross_minor' => 8_000_000,
    ]);
    $e2 = Employee::create([
        'company_id' => $company->id, 'code' => 'EMP-2', 'name' => 'Siti', 'ptkp_status' => 'K/0',
        'monthly_gross_minor' => 10_000_000,
    ]);

    $run = app(RunPayroll::class)->execute(
        companyId: $company->id, projectId: $project->id, wbsId: null, costCode: 'LAB',
        period: '2026-07', employeeIds: [$e1->id, $e2->id],
    );

    // Aggregated across both employees.
    expect((int) $run->gross_minor)->toBe(18_000_000)
        ->and((int) $run->pph21_minor)->toBe(320_000)      // 120.000 + 200.000
        ->and((int) $run->net_minor)->toBe(16_964_404)
        ->and($run->lines()->count())->toBe(2);

    expect(app(OutboxRelay::class)->drain())->toBe(1);
    $journal = Journal::with('lines')->where('fact_type', 'payroll.run_approved')->firstOrFail();
    expect($journal->lines->sum('debit_minor'))->toBe($journal->lines->sum('credit_minor'))
        ->and($journal->lines->firstWhere('account_code', '5103')->debit_minor)->toBe(18_000_000)  // labor to project
        ->and($journal->lines->firstWhere('account_code', '5103')->project_id)->toBe($project->id)
        ->and($journal->lines->firstWhere('account_code', '2141')->credit_minor)->toBe(16_964_404); // salaries payable
});
