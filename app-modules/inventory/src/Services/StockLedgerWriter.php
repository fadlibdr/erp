<?php

declare(strict_types=1);

namespace Modules\Inventory\Services;

use Modules\Platform\Domain\Currency;
use Modules\Platform\Domain\Money;
use Modules\Platform\Models\OutboxEvent;
use Modules\Platform\Support\OutboxConsumer;

/**
 * Turns a goods-received fact into append-only stock movements, one per received
 * line that lands in a warehouse — each blended into the item×warehouse pool at
 * moving average via the shared StockLedger. Lines without an item/warehouse — a
 * direct-to-site service or expense receipt — carry no stock and are skipped.
 *
 * It names the upstream fact type as a local string, depending only on Platform
 * and its own module — never on Procurement or Finance — so the arrow stays down.
 */
final class StockLedgerWriter implements OutboxConsumer
{
    private const GOODS_RECEIVED = 'procurement.goods_received';

    public function __construct(
        private readonly StockLedger $ledger,
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

            $this->ledger->recordReceipt(
                companyId: $event->company_id,
                itemId: $line['item_id'],
                warehouseId: $line['warehouse_id'],
                movementType: 'grn',
                sourceId: $payload['grn_id'],
                qtyMilli: (int) $line['qty_milli'],
                inValue: Money::ofMinor((int) $line['value_minor'], $currency),
            );
        }
    }
}
