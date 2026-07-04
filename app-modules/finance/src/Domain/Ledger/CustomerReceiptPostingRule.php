<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\Ledger;

use Modules\Platform\Domain\Currency;
use Modules\Platform\Domain\Money;

/**
 * Posting rule for a customer cash receipt (receivables.receipt_received):
 *
 *   Dr Bank                    cash in
 *       Cr Accounts Receivable  clears the termin receivable
 *
 * The retention receivable is a separate sub-ledger, released on its own event.
 */
final class CustomerReceiptPostingRule implements PostingRule
{
    public const FACT_TYPE = 'receivables.receipt_received';

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
            description: 'Penerimaan pelanggan '.($payload['receipt_id'] ?? ''),
            lines: [
                JournalLineDraft::debit($accounts->code('bank'), $amount, $project, memo: 'Kas/bank masuk'),
                JournalLineDraft::credit($accounts->code('accounts_receivable'), $amount, $project, memo: 'Pelunasan piutang termin'),
            ],
            factType: self::FACT_TYPE,
            sourceReference: $payload['receipt_id'] ?? null,
        );
    }
}
