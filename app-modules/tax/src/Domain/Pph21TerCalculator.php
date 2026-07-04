<?php

declare(strict_types=1);

namespace Modules\Tax\Domain;

use Modules\Platform\Domain\Money;

/**
 * PPh 21 monthly withholding by the TER method (PMK 168/2023): from Jan–Nov the
 * employer withholds gross × the monthly TER rate for the employee's category; the
 * December run trues up to the annual Pasal 17 tariff (a later pass). Pure integer
 * arithmetic with banker's rounding via Money::applyRate — the same rounding the
 * rest of the tax and ledger math uses.
 *
 * The category is derived from PTKP status; the rate from the TER table. Both are
 * data, so onboarding a new bracket or a rate change is configuration, not a release.
 */
final class Pph21TerCalculator
{
    /**
     * @param  array<string, list<array{0: int, 1: int}>>  $table  category => [[upToMinor, rateNumerator], …]
     */
    public function __construct(
        private readonly array $table,
    ) {}

    public function monthlyWithholding(Money $gross, PtkpStatus $status): Money
    {
        $category = TerCategory::forStatus($status);
        $rate = $this->rateFor($category, $gross->minor);

        return $gross->applyRate($rate, Pph21TerTable::DENOMINATOR);
    }

    private function rateFor(TerCategory $category, int $grossMinor): int
    {
        foreach ($this->table[$category->value] as [$upTo, $rateNumerator]) {
            if ($grossMinor < $upTo) {
                return $rateNumerator;
            }
        }

        // Unreachable — the table's top bracket is open-ended — but stay total.
        return 0;
    }
}
