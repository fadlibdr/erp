<?php

declare(strict_types=1);

namespace Modules\Tax\Domain;

/**
 * One effective-dated PPh-final rate row.
 *
 * The rate is stored as basis points of a basis point — i.e. an integer
 * numerator over a fixed 10,000 denominator — so 2.65% is 265 and 1.75% is 175,
 * with no float anywhere. Rates are *data*: when a new PP changes them, you ship
 * new rows with a new effective window, you do not touch code.
 */
final class PphFinalRate
{
    public function __construct(
        public readonly ServiceClass $serviceClass,
        public readonly SbuClass $sbuClass,
        public readonly int $rateNumerator,       // over a denominator of 10_000; 265 == 2.65%
        public readonly string $effectiveFrom,    // 'YYYY-MM-DD' inclusive
        public readonly ?string $effectiveTo,     // 'YYYY-MM-DD' inclusive, or null = open-ended
        public readonly string $regulationRef,    // e.g. 'PP 9/2022' or 'PP 51/2008 jo PP 40/2009'
    ) {
    }

    public const DENOMINATOR = 10_000;

    /** Does the given contract/payment date fall inside this row's window? */
    public function coversDate(string $date): bool
    {
        if ($date < $this->effectiveFrom) {
            return false;
        }

        return $this->effectiveTo === null || $date <= $this->effectiveTo;
    }

    public function matches(ServiceClass $serviceClass, SbuClass $sbuClass): bool
    {
        return $this->serviceClass === $serviceClass && $this->sbuClass === $sbuClass;
    }

    public function percent(): string
    {
        return number_format($this->rateNumerator / self::DENOMINATOR * 100, 2) . '%';
    }
}
