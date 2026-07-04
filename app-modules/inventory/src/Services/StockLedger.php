<?php

declare(strict_types=1);

namespace Modules\Inventory\Services;

use Modules\Inventory\Domain\MovingAverageValuation;
use Modules\Inventory\Domain\StockBalance;
use Modules\Inventory\Domain\StockIssue;
use Modules\Inventory\Models\StockLedgerEntry;
use Modules\Platform\Domain\Currency;
use Modules\Platform\Domain\Money;

/**
 * The append-only stock ledger — one source of truth for reading an item×warehouse
 * moving-average balance and writing the next movement. Both sides use it: the GRN
 * consumer (receipts) and the material-issue action (consumption), so the valuation
 * and the "reconstruct balance from the last row" logic live in exactly one place.
 *
 * Quantities are integer thousandths (qtyMilli) in the domain; the ledger column is
 * decimal(18,3), so we divide by 1000 on the way out and round back on the way in.
 */
final class StockLedger
{
    public function __construct(
        private readonly MovingAverageValuation $valuation,
    ) {}

    public function currentBalance(string $companyId, string $itemId, string $warehouseId, Currency $currency): StockBalance
    {
        /** @var StockLedgerEntry|null $last */
        $last = StockLedgerEntry::query()
            ->where('company_id', $companyId)
            ->where('item_id', $itemId)
            ->where('warehouse_id', $warehouseId)
            ->orderByDesc('posted_at')
            ->orderByDesc('id')
            ->first();

        if ($last === null) {
            return StockBalance::opening(Money::zero($currency));
        }

        return new StockBalance(
            qtyMilli: (int) round(((float) $last->balance_qty) * 1000),
            value: Money::ofMinor((int) $last->balance_value_minor, $currency),
        );
    }

    /** Blend a receipt into the pool at moving average and append the movement. */
    public function recordReceipt(string $companyId, string $itemId, string $warehouseId, string $movementType, ?string $sourceId, int $qtyMilli, Money $inValue): StockBalance
    {
        $current = $this->currentBalance($companyId, $itemId, $warehouseId, $inValue->currency);
        $new = $this->valuation->receive($current, $qtyMilli, $inValue);

        $this->append($companyId, $itemId, $warehouseId, $movementType, $sourceId, $qtyMilli, $inValue->minor, $new);

        return $new;
    }

    /** Value an issue at the current moving average and append the (negative) movement. */
    public function recordIssue(string $companyId, string $itemId, string $warehouseId, string $movementType, ?string $sourceId, int $qtyMilli, Currency $currency): StockIssue
    {
        $current = $this->currentBalance($companyId, $itemId, $warehouseId, $currency);
        $issue = $this->valuation->issue($current, $qtyMilli);

        $this->append($companyId, $itemId, $warehouseId, $movementType, $sourceId, -$qtyMilli, -$issue->issuedValue->minor, $issue->remaining);

        return $issue;
    }

    private function append(string $companyId, string $itemId, string $warehouseId, string $movementType, ?string $sourceId, int $qtyDeltaMilli, int $valueDeltaMinor, StockBalance $resulting): void
    {
        StockLedgerEntry::create([
            'company_id' => $companyId,
            'item_id' => $itemId,
            'warehouse_id' => $warehouseId,
            'movement_type' => $movementType,
            'source_id' => $sourceId,
            'qty_delta' => $qtyDeltaMilli / 1000,
            'value_delta_minor' => $valueDeltaMinor,
            'balance_qty' => $resulting->qtyMilli / 1000,
            'balance_value_minor' => $resulting->value->minor,
        ]);
    }
}
