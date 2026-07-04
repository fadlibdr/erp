<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\Ledger;

use Modules\Platform\Domain\Currency;
use Modules\Platform\Domain\Money;

/**
 * Posting rule for a termin invoice (billing.progress_invoice_issued).
 *
 *   Dr Accounts Receivable        net cash to collect
 *   Dr Retention Receivable       retensi withheld by the customer
 *   Dr Advance Liability          uang muka repaid this termin (reduces the liability)
 *   Dr PPh Final Prepaid          construction tax withheld by the customer
 *       Cr Contract Revenue       certified work value (billed; PSAK 72 reclass runs separately)
 *       Cr PPN Output             output VAT
 *
 * Debits (AR + retention + advance + PPh) always equal credits (work + PPN) by
 * construction of the termin figures; JournalDraft re-checks it regardless.
 */
final class ProgressInvoicePostingRule implements PostingRule
{
    public const FACT_TYPE = 'billing.progress_invoice_issued';

    public function factType(): string
    {
        return self::FACT_TYPE;
    }

    public function toJournal(array $payload, AccountMap $accounts): JournalDraft
    {
        $currency = Currency::from($payload['currency']);
        $project = $payload['project_id'] ?? null;

        $money = static fn (string $k): Money => Money::ofMinor((int) $payload[$k], $currency);

        $lines = [
            JournalLineDraft::debit($accounts->code('accounts_receivable'), $money('net_receivable'), $project, memo: 'Termin bersih'),
            JournalLineDraft::debit($accounts->code('retention_receivable'), $money('retention'), $project, memo: 'Retensi ditahan'),
            JournalLineDraft::debit($accounts->code('advance_liability'), $money('uang_muka_recovery'), $project, memo: 'Pemulihan uang muka'),
            JournalLineDraft::debit($accounts->code('pph_final_prepaid'), $money('pph_final'), $project, memo: 'PPh final dipotong ('.($payload['pph_regulation_ref'] ?? '').')'),
            JournalLineDraft::credit($accounts->code('contract_revenue'), $money('work_value'), $project, memo: 'Pendapatan kontrak (termin)'),
            JournalLineDraft::credit($accounts->code('ppn_output'), $money('ppn_output'), $project, memo: 'PPN Keluaran'),
        ];

        // Drop any zero-value lines (e.g. no advance to recover this termin) so the
        // journal stays clean, but keep at least the AR / revenue / PPN backbone.
        $lines = array_values(array_filter(
            $lines,
            static fn (JournalLineDraft $l) => ! $l->debit->isZero() || ! $l->credit->isZero(),
        ));

        return new JournalDraft(
            description: 'Tagihan termin '.($payload['claim_id'] ?? ''),
            lines: $lines,
            factType: self::FACT_TYPE,
            sourceReference: $payload['claim_id'] ?? null,
        );
    }
}
