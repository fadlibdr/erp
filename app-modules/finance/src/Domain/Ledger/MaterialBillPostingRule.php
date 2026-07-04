<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\Ledger;

use Modules\Platform\Domain\Currency;
use Modules\Platform\Domain\Money;

/**
 * Posting rule for an approved material vendor bill (payables.material_bill_approved)
 * — the last hop of the commitment loop, the mirror of GrnPostingRule's credit:
 *
 *   Dr GR/IR accrual      goods value — CLEARS the accrual the goods receipt raised
 *   Dr PPN Input          creditable input VAT (only now, on the invoice)
 *       Cr Accounts Payable   net cash owed to the vendor
 *       Cr Retention Payable  retensi withheld (a liability until released), if any
 *
 * The cost already hit Inventory/WIP at goods-receipt time; the bill does not
 * re-book it — it settles the "goods received, not invoiced" liability against the
 * real payable. So the GR/IR account nets to zero once a received PO is fully
 * billed, and the unbilled-receipts report is just its remaining balance.
 *
 * Debits (GR/IR + PPN) always equal credits (net + retention) because
 * net = work + PPN − retention; JournalDraft re-checks it regardless.
 */
final class MaterialBillPostingRule implements PostingRule
{
    public const FACT_TYPE = 'payables.material_bill_approved';

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
            JournalLineDraft::debit($accounts->code('gr_ir_accrual'), $money('work_value'), $project, memo: 'Pelunasan akrual GR/IR (barang ditagih)'),
            JournalLineDraft::debit($accounts->code('ppn_input'), $money('ppn_input'), $project, memo: 'PPN Masukan'),
            JournalLineDraft::credit($accounts->code('accounts_payable'), $money('net_payable'), $project, memo: 'Utang vendor (neto)'),
            JournalLineDraft::credit($accounts->code('retention_payable'), $money('retention'), $project, memo: 'Retensi ditahan'),
        ];

        // Drop zero-value lines (non-PKP vendor → no input VAT; no retention on a
        // goods supply) while the GR/IR ↔ AP backbone always remains.
        $lines = array_values(array_filter(
            $lines,
            static fn (JournalLineDraft $l) => ! $l->debit->isZero() || ! $l->credit->isZero(),
        ));

        return new JournalDraft(
            description: 'Tagihan material '.($payload['bill_id'] ?? ''),
            lines: $lines,
            factType: self::FACT_TYPE,
            sourceReference: $payload['bill_id'] ?? null,
        );
    }
}
