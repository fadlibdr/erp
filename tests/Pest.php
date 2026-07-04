<?php

declare(strict_types=1);
use Modules\Finance\Database\Seeders\ConstructionLedgerSeeder;
use Modules\Platform\Models\Company;
use Modules\Platform\Models\NumberingSeries;
use Modules\Procurement\Models\PurchaseOrder;
use Modules\Procurement\Models\PurchaseOrderLine;
use Modules\Procurement\Models\Vendor;
use Modules\Projects\Models\BudgetLine;
use Modules\Projects\Models\Project;
use Modules\Projects\Models\Wbs;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test bootstrap
|--------------------------------------------------------------------------
| Feature tests boot the full Laravel app and hit a PostgreSQL test database.
| Unit and Arch tests need neither, and mirror the pure-domain checks in
| bin/domain-tests.php so they run in CI alongside the rest of the suite.
*/

pest()->extend(TestCase::class)->in('Feature');

expect()->extend('toBalance', function () {
    $debits = 0;
    $credits = 0;
    foreach ($this->value->lines as $line) {
        $debits += $line->debit->minor;
        $credits += $line->credit->minor;
    }
    expect($debits)->toBe($credits);

    return $this;
});

/*
|--------------------------------------------------------------------------
| Shared cost-control fixture (Pass 3)
|--------------------------------------------------------------------------
| A company with the seeded CoA, a project + WBS, a Rp 1.000.000 MAT budget
| line, and a vendor — the substrate for the commitment-loop feature tests.
| Returns [company, project, wbs, vendor].
*/
function karyaCostControlFixture(): array
{
    $company = Company::create([
        'code' => 'KON', 'name' => 'PT Kontraktor Uji', 'npwp' => '01.234.567.8-901.000',
        'is_pkp' => true, 'base_currency' => 'IDR', 'sbu_class' => 'medium_large_spec',
    ]);

    foreach (['journal', 'purchase_order', 'grn'] as $key) {
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

    $wbs = Wbs::create([
        'project_id' => $project->id, 'code' => '1', 'path' => '1', 'name' => 'Struktur', 'depth' => 0, 'weight_ppm' => 0,
    ]);

    BudgetLine::create([
        'project_id' => $project->id, 'wbs_id' => $wbs->id, 'cost_code' => 'MAT',
        'budget_minor' => 1_000_000, 'currency' => 'IDR',
    ]);

    $vendor = Vendor::create([
        'company_id' => $company->id, 'code' => 'VEN-01', 'name' => 'CV Material Uji',
        'npwp' => '02.345.678.9-012.000', 'sbu_class' => null, 'is_pkp' => true,
    ]);

    return [$company, $project, $wbs, $vendor];
}

/** A single-line PO for $amount on the fixture's WBS × MAT bucket. */
function karyaPo(Company $company, Project $project, Wbs $wbs, Vendor $vendor, int $amount): PurchaseOrder
{
    $po = PurchaseOrder::create([
        'company_id' => $company->id, 'project_id' => $project->id, 'vendor_id' => $vendor->id,
        'po_date' => '2026-07-04', 'status' => 'draft', 'total_minor' => $amount, 'currency' => 'IDR',
    ]);
    PurchaseOrderLine::create([
        'purchase_order_id' => $po->id, 'wbs_id' => $wbs->id, 'cost_code' => 'MAT',
        'description' => 'Besi beton', 'quantity' => 1, 'unit_rate_minor' => $amount, 'amount_minor' => $amount,
    ]);

    return $po;
}
