<?php

declare(strict_types=1);

namespace Modules\Tax\Domain;

/**
 * The monthly TER rate schedule (PMK 168/2023, Lampiran) — for each category, an
 * ascending list of [upToMinor, rateNumerator] where the rate is over 10 000 (so
 * 2% = 200, 0.25% = 25). The first bracket whose ceiling the gross falls under gives
 * the rate; the top bracket is open-ended (PHP_INT_MAX).
 *
 * This carries a representative slice of the official schedule — enough to withhold
 * correctly across the common salary range — held as data, like the PPh-final matrix
 * (PphFinalRateTable). The full DGT bracket list is reconciled at seed time; adding a
 * bracket is data entry, not a code change.
 */
final class Pph21TerTable
{
    public const DENOMINATOR = 10_000;

    /**
     * @return array<string, list<array{0: int, 1: int}>> category => [[upToMinor, rateNumerator], …]
     */
    public static function statutory(): array
    {
        return [
            'A' => [
                [5_400_000, 0],
                [5_650_000, 25],   // 0.25%
                [5_950_000, 50],   // 0.50%
                [6_300_000, 75],   // 0.75%
                [6_750_000, 100],  // 1.00%
                [7_500_000, 125],  // 1.25%
                [8_550_000, 150],  // 1.50%
                [9_650_000, 175],  // 1.75%
                [10_050_000, 200], // 2.00%
                [10_350_000, 225], // 2.25%
                [11_600_000, 250], // 2.50%
                [PHP_INT_MAX, 300], // 3.00%+ (representative top of the slice)
            ],
            'B' => [
                [6_200_000, 0],
                [6_500_000, 25],
                [6_850_000, 50],
                [7_300_000, 75],
                [9_200_000, 100],
                [10_750_000, 150],
                [11_250_000, 175],
                [PHP_INT_MAX, 200],
            ],
            'C' => [
                [6_600_000, 0],
                [6_950_000, 25],
                [7_350_000, 50],
                [7_800_000, 75],
                [8_850_000, 100],
                [PHP_INT_MAX, 150],
            ],
        ];
    }
}
