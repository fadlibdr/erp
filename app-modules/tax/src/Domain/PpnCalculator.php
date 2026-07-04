<?php

declare(strict_types=1);

namespace Modules\Tax\Domain;

use Modules\Platform\Domain\Money;

/**
 * Output VAT (PPN Keluaran). The headline rate moved from 11% to 12% and is
 * configurable per effective date; the default here is 11%. Rate held as an
 * integer percent so the math stays exact.
 */
final class PpnCalculator
{
    public function __construct(
        private readonly int $ratePercent = 11,
    ) {}

    public function on(Money $base): Money
    {
        return $base->applyRate($this->ratePercent, 100);
    }

    public function ratePercent(): int
    {
        return $this->ratePercent;
    }
}
