<?php

declare(strict_types=1);

namespace Modules\Inventory\Domain;

use Modules\Platform\Domain\Money;

/**
 * The result of valuing an issue: the value that left the pool (which becomes the
 * project material cost the GL books) and the balance remaining after it.
 */
final class StockIssue
{
    public function __construct(
        public readonly Money $issuedValue,
        public readonly StockBalance $remaining,
    ) {}
}
