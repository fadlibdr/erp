<?php

declare(strict_types=1);

use App\Filament\Resources\MaterialIssueResource\Pages\ListMaterialIssues;
use App\Filament\Resources\PurchaseOrderResource\Pages\ListPurchaseOrders;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Modules\Finance\Database\Seeders\ConstructionLedgerSeeder;
use Modules\Finance\Models\Journal;
use Modules\Finance\Services\OutboxRelay;
use Modules\Inventory\Models\Item;
use Modules\Inventory\Models\MaterialIssue;
use Modules\Inventory\Models\StockLedgerEntry;
use Modules\Inventory\Models\Warehouse;
use Modules\Platform\Models\Company;
use Modules\Platform\Models\NumberingSeries;
use Modules\Procurement\Actions\ApprovePurchaseOrder;
use Modules\Procurement\Models\Grn;
use Modules\Procurement\Models\PurchaseOrder;
use Modules\Procurement\Models\PurchaseOrderLine;
use Modules\Procurement\Models\Vendor;
use Modules\Projects\Models\BudgetLine;
use Modules\Projects\Models\Project;
use Modules\Projects\Models\Wbs;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->company = Company::create([
        'code' => 'KON', 'name' => 'PT Kontraktor Uji', 'npwp' => '01.234.567.8-901.000',
        'is_pkp' => true, 'base_currency' => 'IDR', 'sbu_class' => 'medium_large_spec',
    ]);
    foreach (['journal', 'purchase_order', 'grn'] as $key) {
        NumberingSeries::create([
            'company_id' => $this->company->id, 'key' => $key,
            'format' => strtoupper(substr($key, 0, 3)).'-{YYYY}-{#####}', 'next' => 1, 'period_scope' => 'year',
        ]);
    }
    (new ConstructionLedgerSeeder)->seedForCompany($this->company->id);
    $user = User::create(['name' => 'Admin', 'email' => 'a@karya.test', 'password' => 'password']);
    $user->companies()->attach($this->company->id, ['id' => (string) Str::uuid()]);
    $this->actingAs($user);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Filament::setTenant($this->company);

    $this->project = Project::create([
        'company_id' => $this->company->id, 'code' => 'PRJ-001', 'name' => 'Pabrik Uji',
        'contract_number' => 'C-001', 'contract_date' => '2026-03-01', 'service_class' => 'integrated_work',
        'contract_value_minor' => 10_000_000_000, 'currency' => 'IDR', 'retention_percent' => 5,
        'uang_muka_percent' => 20, 'status' => 'active',
    ]);
    $this->wbs = Wbs::create(['project_id' => $this->project->id, 'code' => '1', 'path' => '1', 'name' => 'Struktur', 'depth' => 0, 'weight_ppm' => 0]);
    BudgetLine::create(['project_id' => $this->project->id, 'wbs_id' => $this->wbs->id, 'cost_code' => 'MAT', 'budget_minor' => 2_000_000_000, 'currency' => 'IDR']);
    $vendor = Vendor::create(['company_id' => $this->company->id, 'code' => 'VEN-01', 'name' => 'CV Material', 'is_pkp' => true]);
    $this->item = Item::create(['company_id' => $this->company->id, 'code' => 'BESI', 'name' => 'Besi Beton', 'unit' => 'batang']);
    $this->warehouse = Warehouse::create(['company_id' => $this->company->id, 'code' => 'GD-01', 'name' => 'Gudang Proyek']);

    $this->po = PurchaseOrder::create([
        'company_id' => $this->company->id, 'project_id' => $this->project->id, 'vendor_id' => $vendor->id,
        'po_date' => '2026-07-04', 'status' => 'draft', 'total_minor' => 800_000, 'currency' => 'IDR',
    ]);
    $this->line = PurchaseOrderLine::create([
        'purchase_order_id' => $this->po->id, 'wbs_id' => $this->wbs->id, 'cost_code' => 'MAT',
        'description' => 'Besi beton', 'quantity' => 100, 'unit_rate_minor' => 8_000, 'amount_minor' => 800_000,
    ]);
    app(ApprovePurchaseOrder::class)->execute($this->po);
    app(OutboxRelay::class)->drain();
});

it('receives goods from the PO action, then issues material to the project', function () {
    // Terima Barang: 100 units @ Rp 8.000 into the warehouse.
    Livewire::test(ListPurchaseOrders::class)
        ->callTableAction('receive', $this->po, [
            'received_date' => '2026-07-05',
            'item_id' => $this->item->id,
            'warehouse_id' => $this->warehouse->id,
            'qty' => 100,
            'amount_minor' => 800_000,
        ])
        ->assertHasNoTableActionErrors();

    expect(Grn::where('company_id', $this->company->id)->count())->toBe(1);
    expect(app(OutboxRelay::class)->drain())->toBe(1);
    $accrual = Journal::with('lines')->where('fact_type', 'procurement.goods_received')->firstOrFail();
    expect($accrual->lines->sum('debit_minor'))->toBe($accrual->lines->sum('credit_minor'));
    // The receipt must have moved stock (else the issue below has nothing on hand).
    expect(StockLedgerEntry::where('item_id', $this->item->id)->where('movement_type', 'grn')->count())->toBe(1);

    // Buat Pemakaian: issue 50 units → 50 × Rp 8.000 = Rp 400.000 at moving average.
    Livewire::test(ListMaterialIssues::class)
        ->callAction('issue', [
            'project_id' => $this->project->id, 'cost_code' => 'MAT',
            'warehouse_id' => $this->warehouse->id, 'issue_date' => '2026-07-06',
            'item_id' => $this->item->id, 'qty' => 50,
        ])
        ->assertHasNoActionErrors();

    $issue = MaterialIssue::firstOrFail();
    expect((int) $issue->total_minor)->toBe(400_000);
    expect(app(OutboxRelay::class)->drain())->toBe(1);
    $cost = Journal::with('lines')->where('fact_type', 'inventory.material_issued')->firstOrFail();
    expect($cost->lines->firstWhere('account_code', '5102')->debit_minor)->toBe(400_000);
});
