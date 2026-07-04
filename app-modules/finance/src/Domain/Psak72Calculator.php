<?php

declare(strict_types=1);

namespace Modules\Finance\Domain;

use Modules\Platform\Domain\Money;

/**
 * PSAK 72 (= IFRS 15) revenue recognition, cost-to-cost input method.
 *
 *   POC%                 = cost incurred to date / estimated total cost
 *   recognized to date   = POC% × transaction price (contract value)
 *   period recognition   = recognized to date − recognized in prior periods
 *
 * The reconciliation against billing is what produces the balance-sheet position:
 *   contract asset (unbilled)     when recognized-to-date  >  billed-to-date
 *   contract liability (advance)  when billed-to-date       >  recognized-to-date
 *
 * POC is carried in parts-per-million (integer) so the ratio stays exact and the
 * recognized figure rounds once, with banker's rounding, via Money::applyRate.
 */
final class Psak72Calculator
{
    private const PPM = 1_000_000;

    public function pocRatioPpm(Money $costToDate, Money $estimatedTotalCost): int
    {
        if ($estimatedTotalCost->minor <= 0) {
            return 0;
        }
        // ratio * 1e6, clamped to [0, 1e6] (can't recognize past 100%).
        $ppm = (int) ($costToDate->minor * self::PPM / $estimatedTotalCost->minor);

        return max(0, min(self::PPM, $ppm));
    }

    public function recognize(
        Money $contractValue,
        int $pocRatioPpm,
        Money $recognizedInPriorPeriods,
        Money $billedToDate,
    ): Psak72Result {
        $recognizedToDate = $contractValue->applyRate($pocRatioPpm, self::PPM);
        $periodRecognition = $recognizedToDate->subtract($recognizedInPriorPeriods);

        $delta = $recognizedToDate->subtract($billedToDate); // + => asset, − => liability
        $currency = $contractValue->currency;
        $contractAsset = $delta->isNegative() ? Money::zero($currency) : $delta;
        $contractLiability = $delta->isNegative() ? $delta->negate() : Money::zero($currency);

        return new Psak72Result(
            pocRatioPpm: $pocRatioPpm,
            recognizedToDate: $recognizedToDate,
            periodRecognition: $periodRecognition,
            billedToDate: $billedToDate,
            contractAsset: $contractAsset,
            contractLiability: $contractLiability,
        );
    }
}
