<?php

declare(strict_types=1);

namespace Modules\Inventory\Services;

use Modules\Inventory\Domain\MovingAverageValuation;
use Modules\Inventory\Domain\StockBalance;
use Modules\Inventory\Models\StockLedgerEntry;
use Modules\Platform\Domain\Currency;
use Modules\Platform\Domain\Money;
use Modules\Platform\Models\OutboxEvent;
use Modules\Platform\Support\OutboxConsumer;

/**
 * Turns a goods-received fact into append-only stock movements, one per received
 * line that lands in a warehouse. Each movement is valued by blending into the
 * item×warehouse pool at moving average, and stores the resulting balance so the
 * ledger is self-describing (like the GL). Lines without an item/warehouse — a
 * direct-to-site service or expense receipt — carry no stock and are skipped.
 *
 * It names the upstream fact type as a local string, depending only on Platform
 * and its own module — never on Procurement or Finance — so the arrow stays down.
 */
final class StockLedgerWriter implements OutboxConsumer
{
    private const GOODS_RECEIVED = 'procurement.goods_received';

    public function __construct(
        private readonly MovingAverageValuation $valuation,
    ) {}

    public function handles(string $factType): bool
    {
        return $factType === self::GOODS_RECEIVED;
    }

    public function consume(OutboxEvent $event): void
    {
        $payload = $event->payload;
        $currency = Currency::from($payload['currency']);

        foreach ($payload['lines'] as $line) {
            if (($line['item_id'] ?? null) === null || ($line['warehouse_id'] ?? null) === null) {
                continue; // non-stock receipt
            }

            $current = $this->currentBalance($event->company_id, $line['item_id'], $line['warehouse_id'], $currency);
            $inValue = Money::ofMinor((int) $line['value_minor'], $currency);
            $new = $this->valuation->receive($current, (int) $line['qty_milli'], $inValue);

            StockLedgerEntry::create([
                'company_id' => $event->company_id,
                'item_id' => $line['item_id'],
                'warehouse_id' => $line['warehouse_id'],
                'movement_type' => 'grn',
                'source_id' => $payload['grn_id'],
                'qty_delta' => ((int) $line['qty_milli']) / 1000,
                'value_delta_minor' => $inValue->minor,
                'balance_qty' => $new->qtyMilli / 1000,
                'balance_value_minor' => $new->value->minor,
            ]);
        }
    }

    private function currentBalance(string $companyId, string $itemId, string $warehouseId, Currency $currency): StockBalance
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
}
