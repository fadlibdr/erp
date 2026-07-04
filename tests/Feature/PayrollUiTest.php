<?php

declare(strict_types=1);

use App\Filament\Resources\PayRunResource\Pages\ListPayRuns;
use App\Filament\Resources\PayRunResource\Pages\ViewPayRun;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Modules\Finance\Database\Seeders\ConstructionLedgerSeeder;
use Modules\Finance\Models\Journal;
use Modules\Finance\Services\OutboxRelay;
use Modules\Payroll\Models\Employee;
use Modules\Payroll\Models\PayRun;
use Modules\Platform\Models\Company;
use Modules\Platform\Models\NumberingSeries;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->company = Company::create([
        'code' => 'KON', 'name' => 'PT Kontraktor Uji', 'npwp' => '01.234.567.8-901.000',
        'is_pkp' => true, 'base_currency' => 'IDR', 'sbu_class' => 'medium_large_spec',
    ]);
    NumberingSeries::create([
        'company_id' => $this->company->id, 'key' => 'journal',
        'format' => 'JRN-{YYYY}-{#####}', 'next' => 1, 'period_scope' => 'year',
    ]);
    (new ConstructionLedgerSeeder)->seedForCompany($this->company->id);
    $user = User::create(['name' => 'Admin', 'email' => 'a@karya.test', 'password' => 'password']);
    $user->companies()->attach($this->company->id, ['id' => (string) Str::uuid()]);
    $this->actingAs($user);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Filament::setTenant($this->company);

    $this->e1 = Employee::create(['company_id' => $this->company->id, 'code' => 'E1', 'name' => 'Budi', 'ptkp_status' => 'TK/0', 'monthly_gross_minor' => 8_000_000]);
    $this->e2 = Employee::create(['company_id' => $this->company->id, 'code' => 'E2', 'name' => 'Siti', 'ptkp_status' => 'K/0', 'monthly_gross_minor' => 10_000_000]);
});

it('runs payroll from the list action and posts a balanced labor journal', function () {
    Livewire::test(ListPayRuns::class)
        ->assertOk()
        ->callAction('run', [
            'period' => '2026-07', 'cost_code' => 'LAB',
            'employee_ids' => [$this->e1->id, $this->e2->id],
        ])
        ->assertHasNoActionErrors();

    $run = PayRun::firstOrFail();
    expect((int) $run->gross_minor)->toBe(18_000_000)
        ->and((int) $run->net_minor)->toBe(16_964_404);

    // The published fact posts a balanced labor journal.
    expect(app(OutboxRelay::class)->drain())->toBe(1);
    $journal = Journal::with('lines')->where('fact_type', 'payroll.run_approved')->firstOrFail();
    expect($journal->lines->sum('debit_minor'))->toBe($journal->lines->sum('credit_minor'));

    Livewire::test(ViewPayRun::class, ['record' => $run->getRouteKey()])->assertOk();
});
