<?php

declare(strict_types=1);

namespace Modules\Inventory\Domain;

use Modules\Platform\Domain\Money;

/**
 * A running stock position for one item in one warehouse: quantity (carried in
 * thousandths so decimal(18,3) qty is exact integer math) and its moving-average
 * valuation. Immutable — each movement returns a new balance.
 */
final class StockBalance
{
    public function __construct(
        public readonly int $qtyMilli,
        public readonly Money $value,
    ) {}

    public static function opening(Money $zero): self
    {
        return new self(0, $zero);
    }
}
