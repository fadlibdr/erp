<?php

declare(strict_types=1);

namespace Modules\Tax\Services;

use Illuminate\Support\Facades\Cache;
use Modules\Tax\Domain\PphFinalRate;
use Modules\Tax\Domain\PphFinalRateResolver;
use Modules\Tax\Domain\PphFinalRateTable;
use Modules\Tax\Domain\SbuClass;
use Modules\Tax\Domain\ServiceClass;
use Modules\Tax\Models\PphFinalRateRow;

/**
 * Loads stored PPh-final rows into a pure PphFinalRateTable and hands back a
 * resolver. Rates change rarely, so the table is cached; a regulatory-update
 * release busts the cache. If the table is empty (fresh install before seeding)
 * it falls back to the statutory defaults compiled into the domain.
 */
final class PphFinalRateRepository
{
    public function resolver(): PphFinalRateResolver
    {
        return new PphFinalRateResolver($this->table());
    }

    public function table(): PphFinalRateTable
    {
        return Cache::remember('tax.pph_final_rates', now()->addHours(6), function (): PphFinalRateTable {
            $rows = PphFinalRateRow::all();

            if ($rows->isEmpty()) {
                return PphFinalRateTable::statutory();
            }

            $rates = $rows->map(fn (PphFinalRateRow $r): PphFinalRate => new PphFinalRate(
                serviceClass: ServiceClass::from($r->service_class),
                sbuClass: SbuClass::from($r->sbu_class),
                rateNumerator: $r->rate_numerator,
                effectiveFrom: $r->effective_from->format('Y-m-d'),
                effectiveTo: $r->effective_to?->format('Y-m-d'),
                regulationRef: $r->regulation_ref,
            ))->all();

            return new PphFinalRateTable(array_values($rates));
        });
    }
}
