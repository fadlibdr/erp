<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\Ledger;

use Modules\Platform\Domain\Currency;
use Modules\Platform\Domain\Money;

/**
 * Posting rule for a goods receipt (procurement.goods_received).
 *
 *   Dr Inventory / WIP        value of goods received onto the project
 *       Cr GR/IR accrual      the "goods received, invoice not received" liability
 *
 * This is the accrual half of three-way matching: the receipt books the asset/cost
 * and a clearing liability the moment goods arrive, independent of when the vendor
 * bills. The later VendorBill posting (payables) DEBITS the same GR/IR account to
 * clear it, so an unbilled-receipts report is just the GR/IR balance. The cost line
 * carries the project / WBS / cost-code dimensions so committed-vs-actual ties back
 * to the same budget line the PO checked.
 */
final class GrnPostingRule implements PostingRule
{
    public const FACT_TYPE = 'procurement.goods_received';

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
            JournalLineDraft::debit($accounts->code('inventory_wip'), $amount, $project, $wbs, $costCode, memo: 'Penerimaan barang (GRN)'),
            JournalLineDraft::credit($accounts->code('gr_ir_accrual'), $amount, $project, memo: 'Akrual GR/IR (barang diterima, tagihan belum)'),
        ];

        return new JournalDraft(
            description: 'Penerimaan barang '.($payload['grn_id'] ?? ''),
            lines: $lines,
            factType: self::FACT_TYPE,
            sourceReference: $payload['grn_id'] ?? null,
        );
    }
}
