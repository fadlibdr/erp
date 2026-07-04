<?php

declare(strict_types=1);

namespace Modules\Procurement\Domain;

/**
 * The outcome of matching a vendor bill against its PO and goods receipts.
 * Matched clears for payment; the variance cases hold the bill for a buyer's
 * review (over-delivery, or a price above the agreed PO rate beyond tolerance).
 */
enum MatchVerdict: string
{
    case Matched = 'matched';
    case QtyVariance = 'qty_variance';
    case PriceVariance = 'price_variance';
    case QtyAndPriceVariance = 'qty_and_price_variance';

    public function isClean(): bool
    {
        return $this === self::Matched;
    }
}
