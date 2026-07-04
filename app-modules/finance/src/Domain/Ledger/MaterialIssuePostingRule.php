<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\Ledger;

use Modules\Platform\Domain\Currency;
use Modules\Platform\Domain\Money;

/**
 * Posting rule for a material issue (inventory.material_issued) — the moment stored
 * stock becomes an actual project cost:
 *
 *   Dr Project Material Cost   value issued (at moving average), tagged to WBS/cost code
 *       Cr Inventory           the stock leaving the warehouse
 *
 * This is what makes the GL's project cost, budget actuals (CommitmentRepository),
 * and PSAK 72 cost-to-date (CloseFiscalPeriod) all reconcile to the same number: an
 * issue debits the expense that the close reads as cost incurred.
 */
final class MaterialIssuePostingRule implements PostingRule
{
    public const FACT_TYPE = 'inventory.material_issued';

    public function factType(): string
    {
        return self::FACT_TYPE;
    }

    public function toJournal(array $payload, AccountMap $accounts): JournalDraft
    {
        $currency = Currency::from($payload['currency']);
        $project = $payload['project_id'] ?? null;
        $wbs = $payload['wbs_id'] ?? null;
        $costCode = $payload['cost_code'] ?? null;

        $amount = Money::ofMinor((int) $payload['amount'], $currency);

        $lines = [
            JournalLineDraft::debit($accounts->code('project_material_cost'), $amount, $project, $wbs, $costCode, memo: 'Pemakaian material'),
            JournalLineDraft::credit($accounts->code('inventory'), $amount, $project, memo: 'Persediaan keluar'),
        ];

        return new JournalDraft(
            description: 'Pemakaian material '.($payload['issue_id'] ?? ''),
            lines: $lines,
            factType: self::FACT_TYPE,
            sourceReference: $payload['issue_id'] ?? null,
        );
    }
}
