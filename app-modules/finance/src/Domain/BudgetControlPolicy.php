<?php

declare(strict_types=1);

namespace Modules\Finance\Domain;

/**
 * The control-budget gate a purchase order (or any commitment) passes through.
 *
 * Cost control on an EPC project is a three-bucket sum against one ceiling:
 *
 *     available = budget − open commitments − actuals booked
 *
 * where *open commitments* are approved-but-not-yet-received POs/subcontracts and
 * *actuals* are costs already in the GL for that WBS × cost code. A new request is
 * BLOCKed when it would push committed + actual + requested past the budget, WARNed
 * when it crosses a soft threshold (default 90%) but still fits, and otherwise OK.
 *
 * This is pure integer arithmetic on minor units — no I/O, no currency object — so
 * it is trivially testable and identical whether called from the PO approval Action
 * or a Filament availability badge. The caller supplies the three numbers (read from
 * the budget line and the commitment ledger); the policy only decides.
 */
final class BudgetControlPolicy
{
    /** Basis points: warn once committed+actual+requested reaches 90% of budget. */
    public const DEFAULT_WARN_THRESHOLD_BP = 9_000;

    public function decide(
        int $budgetMinor,
        int $openCommitmentMinor,
        int $actualMinor,
        int $requestedMinor,
        int $warnThresholdBp = self::DEFAULT_WARN_THRESHOLD_BP,
    ): BudgetDecision {
        $consumed = $openCommitmentMinor + $actualMinor;
        $available = $budgetMinor - $consumed;
        $projected = $consumed + $requestedMinor;

        if ($projected > $budgetMinor) {
            $verdict = BudgetVerdict::Block;
        } elseif ($projected * 10_000 >= $budgetMinor * $warnThresholdBp) {
            // >= the soft threshold (e.g. 90% of budget) but still within it.
            $verdict = BudgetVerdict::Warn;
        } else {
            $verdict = BudgetVerdict::Ok;
        }

        return new BudgetDecision(
            verdict: $verdict,
            budgetMinor: $budgetMinor,
            availableMinor: $available,
            projectedMinor: $projected,
            overspendMinor: max(0, $projected - $budgetMinor),
        );
    }
}
