<?php

declare(strict_types=1);

namespace Modules\Procurement\Domain;

/**
 * Three-way match: purchase order ↔ goods receipt ↔ vendor bill.
 *
 * A subcontractor/supplier bill is only cleared for payment when the quantity
 * billed matches what was received and the price matches what was ordered, each
 * within a tolerance (basis points) that absorbs rounding and agreed minor
 * variances. Quantity is compared receipt-vs-order (you pay for what arrived, not
 * what was promised); price is compared bill-vs-order (the agreed rate governs).
 *
 * Pure integer arithmetic: quantities in thousandths, amounts in minor units. The
 * caller decides what a non-clean verdict means (hold, escalate, or override); the
 * domain only classifies.
 */
final class ThreeWayMatch
{
    public const DEFAULT_QTY_TOLERANCE_BP = 0;      // received must equal ordered by default

    public const DEFAULT_PRICE_TOLERANCE_BP = 100;  // 1% price tolerance

    public function match(
        int $orderedQtyMilli,
        int $receivedQtyMilli,
        int $orderedAmountMinor,
        int $billedAmountMinor,
        int $qtyToleranceBp = self::DEFAULT_QTY_TOLERANCE_BP,
        int $priceToleranceBp = self::DEFAULT_PRICE_TOLERANCE_BP,
    ): MatchVerdict {
        $qtyOk = $this->withinTolerance($orderedQtyMilli, $receivedQtyMilli, $qtyToleranceBp);
        $priceOk = $this->withinTolerance($orderedAmountMinor, $billedAmountMinor, $priceToleranceBp);

        return match (true) {
            $qtyOk && $priceOk => MatchVerdict::Matched,
            ! $qtyOk && ! $priceOk => MatchVerdict::QtyAndPriceVariance,
            ! $qtyOk => MatchVerdict::QtyVariance,
            default => MatchVerdict::PriceVariance,
        };
    }

    /** |actual − expected| ≤ expected × toleranceBp / 10000, using cross-multiplication to stay in integers. */
    private function withinTolerance(int $expected, int $actual, int $toleranceBp): bool
    {
        $delta = abs($actual - $expected);

        return $delta * 10_000 <= abs($expected) * $toleranceBp;
    }
}
