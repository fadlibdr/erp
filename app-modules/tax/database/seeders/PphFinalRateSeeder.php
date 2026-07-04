<?php

declare(strict_types=1);

namespace Modules\Tax\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Tax\Domain\PphFinalRateTable;
use Modules\Tax\Models\PphFinalRateRow;

/**
 * Seeds the statutory PPh-final rates from the single source of truth
 * (PphFinalRateTable::statutory()), so the code defaults and the database can
 * never drift. Idempotent: safe to re-run after a regulatory-update release.
 */
final class PphFinalRateSeeder extends Seeder
{
    public function run(): void
    {
        foreach (PphFinalRateTable::statutory()->all() as $rate) {
            PphFinalRateRow::updateOrCreate(
                [
                    'service_class' => $rate->serviceClass->value,
                    'sbu_class' => $rate->sbuClass->value,
                    'effective_from' => $rate->effectiveFrom,
                ],
                [
                    'rate_numerator' => $rate->rateNumerator,
                    'effective_to' => $rate->effectiveTo,
                    'regulation_ref' => $rate->regulationRef,
                ],
            );
        }
    }
}
