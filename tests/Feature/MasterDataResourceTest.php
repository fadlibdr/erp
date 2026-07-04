<?php

declare(strict_types=1);

use App\Filament\Resources\EmployeeResource;
use App\Filament\Resources\ItemResource;
use App\Filament\Resources\VendorResource;
use App\Filament\Resources\WarehouseResource;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Modules\Platform\Models\Company;
use Modules\Procurement\Models\Vendor;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->company = Company::create([
        'code' => 'KON', 'name' => 'PT Kontraktor Uji', 'npwp' => '01.234.567.8-901.000',
        'is_pkp' => true, 'base_currency' => 'IDR', 'sbu_class' => 'medium_large_spec',
    ]);
    $user = User::create(['name' => 'Admin', 'email' => 'a@karya.test', 'password' => 'password']);
    $user->companies()->attach($this->company->id, ['id' => (string) Str::uuid()]);
    $this->actingAs($user);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Filament::setTenant($this->company);
});

it('renders the master-data list + create pages', function () {
    Livewire::test(VendorResource\Pages\ListVendors::class)->assertOk();
    Livewire::test(VendorResource\Pages\CreateVendor::class)->assertOk();
    Livewire::test(EmployeeResource\Pages\ListEmployees::class)->assertOk();
    Livewire::test(EmployeeResource\Pages\CreateEmployee::class)->assertOk();
    Livewire::test(ItemResource\Pages\ListItems::class)->assertOk();
    Livewire::test(ItemResource\Pages\CreateItem::class)->assertOk();
    Livewire::test(WarehouseResource\Pages\ListWarehouses::class)->assertOk();
    Livewire::test(WarehouseResource\Pages\CreateWarehouse::class)->assertOk();
});

it('creates a tenant-scoped vendor through the form', function () {
    Livewire::test(VendorResource\Pages\CreateVendor::class)
        ->fillForm(['code' => 'VEN-99', 'name' => 'CV Uji Baru', 'sbu_class' => 'small', 'is_pkp' => true])
        ->call('create')
        ->assertHasNoFormErrors();

    $vendor = Vendor::where('code', 'VEN-99')->firstOrFail();
    expect($vendor->company_id)->toBe($this->company->id); // tenancy auto-filled company_id
});
