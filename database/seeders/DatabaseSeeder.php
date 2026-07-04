<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Finance\Database\Seeders\ConstructionLedgerSeeder;
use Modules\Platform\Models\Company;
use Modules\Platform\Models\NumberingSeries;
use Modules\Tax\Database\Seeders\PphFinalRateSeeder;

/**
 * Staging seed: a demo company with the construction chart of accounts, the PPh-final
 * rate matrix, its document-numbering series, and one admin user tied to it — enough
 * to log into the panel and drive every money path end to end.
 */
final class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(PphFinalRateSeeder::class);

        $company = Company::query()->firstOrCreate(
            ['code' => 'KON'],
            [
                'name' => 'PT Karya Konstruksi', 'legal_name' => 'PT Karya Konstruksi Nusantara',
                'npwp' => '01.234.567.8-901.000', 'is_pkp' => true, 'base_currency' => 'IDR',
                'sbu_class' => 'medium_large_spec',
            ],
        );

        (new ConstructionLedgerSeeder)->seedForCompany($company->id);

        foreach (['journal', 'progress_claim', 'vendor_bill', 'purchase_order', 'grn'] as $key) {
            NumberingSeries::query()->firstOrCreate(
                ['company_id' => $company->id, 'key' => $key],
                ['format' => strtoupper(substr($key, 0, 3)).'-{YYYY}-{#####}', 'next' => 1, 'period_scope' => 'year'],
            );
        }

        $user = User::query()->firstOrCreate(
            ['email' => 'admin@karya.test'],
            ['name' => 'Admin', 'password' => 'password'],
        );

        // Link the user to the company (the Filament tenant). The pivot carries a UUID.
        if (! DB::table('company_user')->where('company_id', $company->id)->where('user_id', $user->id)->exists()) {
            DB::table('company_user')->insert([
                'id' => (string) Str::uuid(), 'company_id' => $company->id, 'user_id' => $user->id,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }
}
