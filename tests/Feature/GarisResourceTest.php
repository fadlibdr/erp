<?php

declare(strict_types=1);

use App\Filament\Resources\ProgressClaimResource\Pages\CreateProgressClaim;
use App\Filament\Resources\PurchaseOrderResource\Pages\CreatePurchaseOrder;
use App\Filament\Resources\VendorBillResource\Pages\CreateVendorBill;
use App\Filament\Resources\VendorBillResource\Pages\ViewVendorBill;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Modules\Payables\Models\VendorBill;
use Modules\Platform\Models\Company;
use Modules\Procurement\Models\Vendor;

uses(RefreshDatabase::class);

it('renders a paid vendor bill as a GARIS etiket with a Lunas stamp', function () {
    $company = Company::create([
        'code' => 'KON', 'name' => 'PT Kontraktor Uji', 'npwp' => '01.234.567.8-901.000',
        'is_pkp' => true, 'base_currency' => 'IDR', 'sbu_class' => 'medium_large_spec',
    ]);
    $user = User::create(['name' => 'Admin', 'email' => 'a@karya.test', 'password' => 'password']);
    $user->companies()->attach($company->id, ['id' => (string) Str::uuid()]);
    $vendor = Vendor::create([
        'company_id' => $company->id, 'code' => 'SUB-01', 'name' => 'CV Sub Uji',
        'npwp' => '02.345.678.9-012.000', 'sbu_class' => 'small', 'is_pkp' => true,
    ]);
    $bill = VendorBill::create([
        'company_id' => $company->id, 'vendor_id' => $vendor->id, 'number' => 'VEN-2026-00001',
        'bill_date' => '2026-07-04', 'status' => 'paid', 'service_class' => 'construction_work',
        'cost_code' => 'SUB', 'retention_percent' => 5, 'work_value_minor' => 500_000,
        'ppn_input_minor' => 55_000, 'retention_minor' => 25_000, 'pph_withheld_minor' => 8_750,
        'net_payable_minor' => 521_250, 'currency' => 'IDR',
    ]);

    $this->actingAs($user);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Filament::setTenant($company);

    Livewire::test(ViewVendorBill::class, ['record' => $bill->getRouteKey()])
        ->assertOk()
        ->assertSee('Tagihan Vendor / Subkontraktor')  // the etiket title
        ->assertSee('VEN-2026-00001')                   // the real document number
        ->assertSee('Lunas');                           // the wet-cap stamp (paid)
});

it('renders the resource create forms — relationship selects resolve', function () {
    $company = Company::create([
        'code' => 'KON', 'name' => 'PT Kontraktor Uji', 'npwp' => '01.234.567.8-901.000',
        'is_pkp' => true, 'base_currency' => 'IDR', 'sbu_class' => 'medium_large_spec',
    ]);
    $user = User::create(['name' => 'Admin', 'email' => 'a@karya.test', 'password' => 'password']);
    $user->companies()->attach($company->id, ['id' => (string) Str::uuid()]);

    $this->actingAs($user);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Filament::setTenant($company);

    // Before the model relations existed these forms fatal'd on ->relationship(...).
    Livewire::test(CreateVendorBill::class)->assertOk();
    Livewire::test(CreatePurchaseOrder::class)->assertOk();
    Livewire::test(CreateProgressClaim::class)->assertOk();
});
