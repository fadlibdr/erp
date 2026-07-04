<?php

declare(strict_types=1);

namespace Modules\Finance\Domain;

/**
 * The immutable result of BudgetControlPolicy::decide — the verdict plus the numbers
 * behind it, so a UI badge or an audit line can show *why* without re-deriving them.
 */
final class BudgetDecision
{
    public function __construct(
        public readonly BudgetVerdict $verdict,
        public readonly int $budgetMinor,
        public readonly int $availableMinor,
        public readonly int $projectedMinor,
        public readonly int $overspendMinor,
    ) {}

    public function isBlocking(): bool
    {
        return $this->verdict->isBlocking();
    }
}
