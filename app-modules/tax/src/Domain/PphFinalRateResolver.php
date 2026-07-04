<?php

declare(strict_types=1);

namespace Modules\Tax\Domain;

use RuntimeException;

/**
 * Resolves the PPh-final rate for a construction payment.
 *
 * The one non-obvious rule this encodes (PP 9/2022 transitional provision):
 * the applicable regime is chosen by the **contract signing date**, not the
 * payment date. A contract signed before 2022-02-21 keeps the old PP 51/2008
 * rates even for progress payments made years later. So callers pass the
 * contract date, and the resolver returns the row whose effective window
 * contains that date.
 */
final class PphFinalRateResolver
{
    public function __construct(
        private readonly PphFinalRateTable $table,
    ) {
    }

    public function resolve(
        ServiceClass $serviceClass,
        SbuClass $sbuClass,
        string $contractDate,
    ): PphFinalRate {
        foreach ($this->table->all() as $rate) {
            if ($rate->matches($serviceClass, $sbuClass) && $rate->coversDate($contractDate)) {
                return $rate;
            }
        }

        throw new RuntimeException(sprintf(
            'No PPh-final rate found for %s / %s effective on %s.',
            $serviceClass->value,
            $sbuClass->value,
            $contractDate,
        ));
    }
}
