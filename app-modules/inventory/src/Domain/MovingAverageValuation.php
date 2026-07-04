<?php

declare(strict_types=1);

namespace Modules\Inventory\Domain;

use Modules\Platform\Domain\Money;
use RuntimeException;

/**
 * Moving-average (weighted-average) inventory valuation, the method most ID
 * contractors run and the one PSAK 14 permits alongside FIFO.
 *
 * A receipt blends its value into the pool; the unit cost after it is
 * (old value + received value) / (old qty + received qty). An issue leaves at the
 * *current* average, so it removes value proportional to the quantity taken —
 * value_out = pool_value × qty_out / pool_qty, rounded once with banker's rounding
 * (reusing Money::applyRate, the same rounding the tax and ledger math use). No
 * floats: quantity is integer thousandths, value is integer minor units.
 *
 * The stock ledger this feeds is append-only like the GL: every movement stores the
 * delta and the resulting balance, so any historical valuation is reproducible.
 */
final class MovingAverageValuation
{
    /** Blend a receipt into the pool and return the new balance. */
    public function receive(StockBalance $current, int $inQtyMilli, Money $inValue): StockBalance
    {
        if ($inQtyMilli <= 0) {
            throw new RuntimeException('A receipt must have a positive quantity.');
        }

        return new StockBalance(
            qtyMilli: $current->qtyMilli + $inQtyMilli,
            value: $current->value->add($inValue),
        );
    }

    /**
     * Value an issue at the current moving average and return the issued value plus
     * the remaining balance. Issuing the whole pool takes its whole value exactly
     * (no rounding dust left behind).
     */
    public function issue(StockBalance $current, int $outQtyMilli): StockIssue
    {
        if ($outQtyMilli <= 0) {
            throw new RuntimeException('An issue must have a positive quantity.');
        }
        if ($outQtyMilli > $current->qtyMilli) {
            throw new RuntimeException('Cannot issue more than the quantity on hand.');
        }

        $issuedValue = $outQtyMilli === $current->qtyMilli
            ? $current->value
            : $current->value->applyRate($outQtyMilli, $current->qtyMilli);

        $remaining = new StockBalance(
            qtyMilli: $current->qtyMilli - $outQtyMilli,
            value: $current->value->subtract($issuedValue),
        );

        return new StockIssue($issuedValue, $remaining);
    }
}
