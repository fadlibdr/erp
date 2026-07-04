<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\Ledger;

use Modules\Platform\Domain\Currency;
use Modules\Platform\Domain\Money;

/**
 * Posting rule for the month-end PSAK 72 recognition true-up (finance.revenue_recognized).
 *
 * Progress billing already credited revenue for what was invoiced (the Pass 1
 * ProgressInvoicePostingRule). PSAK 72 says revenue is POC × contract value,
 * independent of billing, so the close posts only the *period true-up* = this
 * period's percentage-of-completion recognition minus what was billed this period:
 *
 *   under-billed (true-up > 0):  Dr Contract Asset      / Cr Contract Revenue
 *   over-billed  (true-up < 0):  Dr Contract Revenue    / Cr Contract Liability
 *
 * so cumulative recognized revenue converges to POC × contract value while the
 * unbilled/advance position lands on the balance sheet. The caller never posts a
 * zero true-up (a no-op period books nothing).
 */
final class Psak72PostingRule implements PostingRule
{
    public const FACT_TYPE = 'finance.revenue_recognized';

    public function factType(): string
    {
        return self::FACT_TYPE;
    }

    public function toJournal(array $payload, AccountMap $accounts): JournalDraft
    {
        $currency = Currency::from($payload['currency']);
        $project = $payload['project_id'] ?? null;
        $trueUp = (int) $payload['true_up'];
        $amount = Money::ofMinor(abs($trueUp), $currency);

        $lines = $trueUp >= 0
            ? [
                JournalLineDraft::debit($accounts->code('contract_asset'), $amount, $project, memo: 'Aset kontrak (pendapatan diakui > ditagih)'),
                JournalLineDraft::credit($accounts->code('contract_revenue'), $amount, $project, memo: 'Penyesuaian pengakuan pendapatan (PSAK 72)'),
            ]
            : [
                JournalLineDraft::debit($accounts->code('contract_revenue'), $amount, $project, memo: 'Penyesuaian pengakuan pendapatan (PSAK 72)'),
                JournalLineDraft::credit($accounts->code('contract_liability'), $amount, $project, memo: 'Liabilitas kontrak (ditagih > pendapatan diakui)'),
            ];

        return new JournalDraft(
            description: 'Pengakuan pendapatan PSAK 72 '.($payload['project_id'] ?? ''),
            lines: $lines,
            factType: self::FACT_TYPE,
            sourceReference: $payload['revrec_run_id'] ?? null,
        );
    }
}
