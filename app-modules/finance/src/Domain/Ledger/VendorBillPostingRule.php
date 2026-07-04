<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\Ledger;

use Modules\Platform\Domain\Currency;
use Modules\Platform\Domain\Money;

/**
 * Posting rule for an approved subcontractor bill (payables.vendor_bill_approved).
 * The mirror of the ProgressInvoicePostingRule, on the payables side:
 *
 *   Dr Subcontract Cost / WIP     certified work value (accrued to the project)
 *   Dr PPN Input                  creditable input VAT
 *       Cr Accounts Payable       net cash owed to the subcontractor
 *       Cr Retention Payable      retensi withheld from the sub (a liability until released)
 *       Cr PPh Final Payable      construction tax withheld, owed to the state
 *
 * Debits (cost + PPN) always equal credits (net + retention + PPh) by construction
 * of the bill figures — net = work + PPN − retention − PPh, so net + retention +
 * PPh = work + PPN. JournalDraft re-checks the balance regardless.
 *
 * The cost line carries the project / WBS / cost-code dimensions so the whole
 * subcontract spend is sliceable by project in the same ledger as everything else.
 */
final class VendorBillPostingRule implements PostingRule
{
    public const FACT_TYPE = 'payables.vendor_bill_approved';

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

        $money = static fn (string $k): Money => Money::ofMinor((int) $payload[$k], $currency);

        $lines = [
            JournalLineDraft::debit($accounts->code('subcontract_cost'), $money('work_value'), $project, $wbs, $costCode, memo: 'Biaya subkontraktor'),
            JournalLineDraft::debit($accounts->code('ppn_input'), $money('ppn_input'), $project, $wbs, $costCode, memo: 'PPN Masukan'),
            JournalLineDraft::credit($accounts->code('accounts_payable'), $money('net_payable'), $project, memo: 'Utang subkontraktor (neto)'),
            JournalLineDraft::credit($accounts->code('retention_payable'), $money('retention'), $project, memo: 'Retensi ditahan'),
            JournalLineDraft::credit($accounts->code('pph_final_payable'), $money('pph_final'), $project, memo: 'PPh final dipotong ('.($payload['pph_regulation_ref'] ?? '').')'),
        ];

        // Drop any zero-value lines (e.g. a non-PKP vendor has no input VAT, or a
        // bill with no retention) so the journal stays clean, while the cost / AP
        // backbone always remains.
        $lines = array_values(array_filter(
            $lines,
            static fn (JournalLineDraft $l) => ! $l->debit->isZero() || ! $l->credit->isZero(),
        ));

        return new JournalDraft(
            description: 'Tagihan subkontraktor '.($payload['bill_id'] ?? ''),
            lines: $lines,
            factType: self::FACT_TYPE,
            sourceReference: $payload['bill_id'] ?? null,
        );
    }
}
