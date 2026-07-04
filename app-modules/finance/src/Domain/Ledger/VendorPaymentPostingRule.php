<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\Ledger;

use Modules\Platform\Domain\Currency;
use Modules\Platform\Domain\Money;

/**
 * Posting rule for a vendor payment batch (payables.payment_made):
 *
 *   Dr Accounts Payable   the net settled to the vendor
 *       Cr Bank           cash out
 *
 * Only the net moves — the PPh-final and retention withheld at bill time stay as
 * their own payables (remitted / released on their own schedule).
 */
final class VendorPaymentPostingRule implements PostingRule
{
    public const FACT_TYPE = 'payables.payment_made';

    public function factType(): string
    {
        return self::FACT_TYPE;
    }

    public function toJournal(array $payload, AccountMap $accounts): JournalDraft
    {
        $currency = Currency::from($payload['currency']);
        $amount = Money::ofMinor((int) $payload['amount'], $currency);

        return new JournalDraft(
            description: 'Pembayaran vendor '.($payload['batch_id'] ?? ''),
            lines: [
                JournalLineDraft::debit($accounts->code('accounts_payable'), $amount, memo: 'Pelunasan utang usaha'),
                JournalLineDraft::credit($accounts->code('bank'), $amount, memo: 'Kas/bank keluar'),
            ],
            factType: self::FACT_TYPE,
            sourceReference: $payload['batch_id'] ?? null,
        );
    }
}
