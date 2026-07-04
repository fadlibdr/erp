<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\Ledger;

use Modules\Platform\Domain\Currency;
use Modules\Platform\Domain\Money;

/**
 * Posting rule for a retention release (receivables.retention_released) — the cash
 * the customer holds back until final hand-over (FHO / BAST-II) and then pays:
 *
 *   Dr Bank                    retention received
 *       Cr Retention Receivable  clears the held retention sub-ledger
 */
final class RetentionReleasePostingRule implements PostingRule
{
    public const FACT_TYPE = 'receivables.retention_released';

    public function factType(): string
    {
        return self::FACT_TYPE;
    }

    public function toJournal(array $payload, AccountMap $accounts): JournalDraft
    {
        $currency = Currency::from($payload['currency']);
        $project = $payload['project_id'] ?? null;
        $amount = Money::ofMinor((int) $payload['amount'], $currency);

        return new JournalDraft(
            description: 'Pelepasan retensi '.($payload['retention_id'] ?? ''),
            lines: [
                JournalLineDraft::debit($accounts->code('bank'), $amount, $project, memo: 'Penerimaan retensi'),
                JournalLineDraft::credit($accounts->code('retention_receivable'), $amount, $project, memo: 'Pelepasan piutang retensi'),
            ],
            factType: self::FACT_TYPE,
            sourceReference: $payload['retention_id'] ?? null,
        );
    }
}
