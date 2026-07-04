<?php

declare(strict_types=1);

namespace Modules\Procurement\Domain;

/**
 * The fact Procurement publishes when goods are received against a PO. It fans out
 * to three consumers, each blind to the others:
 *  - Finance GrnPostingRule → the balanced Inventory/WIP ↔ GR/IR accrual (uses `amount`);
 *  - Finance commitment projector → consumes the PO commitment per (WBS × cost code) bucket;
 *  - Inventory stock-ledger writer → one moving-average movement per received line.
 */
final class GoodsReceivedFact
{
    public const TYPE = 'procurement.goods_received';

    /**
     * @param  list<array{wbs_id: ?string, cost_code: ?string, amount: int}>  $buckets
     * @param  list<array{item_id: ?string, warehouse_id: ?string, wbs_id: ?string, cost_code: ?string, qty_milli: int, value_minor: int}>  $lines
     */
    public function __construct(
        public readonly string $grnId,
        public readonly string $purchaseOrderId,
        public readonly string $projectId,
        public readonly string $currency,
        public readonly int $amount,
        public readonly array $buckets,
        public readonly array $lines,
    ) {}

    /** @return array<string, mixed> */
    public function toPayload(): array
    {
        return [
            'grn_id' => $this->grnId,
            'purchase_order_id' => $this->purchaseOrderId,
            'project_id' => $this->projectId,
            'currency' => $this->currency,
            'amount' => $this->amount,
            'buckets' => $this->buckets,
            'lines' => $this->lines,
        ];
    }
}
