<?php

declare(strict_types=1);

use App\Filament\Resources\ArInvoiceResource\Pages\ListArInvoices;
use App\Filament\Resources\ArRetentionResource\Pages\ListArRetentions;
use App\Filament\Resources\PaymentBatchResource\Pages\ListPaymentBatches;
use App\Filament\Resources\VendorBillResource\Pages\ListVendorBills;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Modules\Billing\Actions\IssueTerminInvoice;
use Modules\Billing\Models\ProgressClaim;
use Modules\Finance\Database\Seeders\ConstructionLedgerSeeder;
use Modules\Finance\Services\OutboxRelay;
use Modules\Payables\Actions\ApproveSubcontractorBill;
use Modules\Payables\Models\PaymentBatch;
use Modules\Payables\Models\VendorBill;
use Modules\Platform\Models\Company;
use Modules\Platform\Models\NumberingSeries;
use Modules\Procurement\Models\Vendor;
use Modules\Projects\Models\Project;
use Modules\Receivables\Models\ArInvoice;
use Modules\Receivables\Models\ArRetention;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->company = Company::create([
        'code' => 'KON', 'name' => 'PT Kontraktor Uji', 'npwp' => '01.234.567.8-901.000',
        'is_pkp' => true, 'base_currency' => 'IDR', 'sbu_class' => 'medium_large_spec',
    ]);
    foreach (['journal', 'vendor_bill', 'progress_claim'] as $key) {
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
    $vendor = Vendor::create([
        'company_id' => $this->company->id, 'code' => 'SUB-01', 'name' => 'CV Sub Uji',
        'npwp' => '02.345.678.9-012.000', 'sbu_class' => 'small', 'is_pkp' => true,
    ]);
    $this->bill = VendorBill::create([
        'company_id' => $this->company->id, 'vendor_id' => $vendor->id, 'project_id' => $this->project->id,
        'bill_date' => '2026-07-04', 'contract_date' => '2026-03-01', 'status' => 'draft',
        'service_class' => 'construction_work', 'retention_percent' => 5, 'cost_code' => 'SUB',
        'work_value_minor' => 500_000, 'currency' => 'IDR',
    ]);
    app(ApproveSubcontractorBill::class)->execute($this->bill);
    app(OutboxRelay::class)->drain();

    $claim = ProgressClaim::create([
        'company_id' => $this->company->id, 'project_id' => $this->project->id, 'sequence' => 1,
        'claim_date' => '2026-07-04', 'status' => 'bapp', 'work_value_minor' => 1_000_000, 'currency' => 'IDR',
    ]);
    app(IssueTerminInvoice::class)->execute($claim);
    app(OutboxRelay::class)->drain(); // creates the AR invoice + retention
});

it('renders the settlement list pages', function () {
    Livewire::test(ListVendorBills::class)->assertOk();
    Livewire::test(ListPaymentBatches::class)->assertOk();
    Livewire::test(ListArInvoices::class)->assertOk();
    Livewire::test(ListArRetentions::class)->assertOk();
});

it('pays approved bills via the bulk action', function () {
    Livewire::test(ListVendorBills::class)
        ->callTableBulkAction('pay', [$this->bill], ['payment_date' => '2026-07-31', 'bank' => 'bca']);

    expect($this->bill->refresh()->status)->toBe('paid')
        ->and(PaymentBatch::where('company_id', $this->company->id)->count())->toBe(1);
});

it('receives a customer payment and releases retention', function () {
    $invoice = ArInvoice::firstOrFail();
    Livewire::test(ListArInvoices::class)
        ->callTableAction('receive', $invoice, ['amount' => $invoice->net_minor, 'receipt_date' => '2026-08-01']);
    expect($invoice->refresh()->status)->toBe('paid');

    $retention = ArRetention::firstOrFail();
    Livewire::test(ListArRetentions::class)
        ->callTableAction('release', $retention, ['release_date' => '2027-01-15']);
    expect($retention->refresh()->status)->toBe('released');
});
