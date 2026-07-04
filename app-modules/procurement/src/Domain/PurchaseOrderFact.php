<?php

declare(strict_types=1);

namespace Modules\Procurement\Domain;

/**
 * The fact Procurement publishes when a PO is approved. It does not touch the GL —
 * a commitment is a memo/encumbrance, not a journal — so this drives the Finance
 * commitment *projection*, not a posting rule. The lines are pre-bucketed by
 * (WBS × cost code) so Finance inserts one commitment row per budget bucket.
 */
final class PurchaseOrderFact
{
    public const TYPE = 'procurement.purchase_order_approved';

    /**
     * @param  list<array{wbs_id: ?string, cost_code: ?string, amount: int}>  $buckets
     */
    public function __construct(
        public readonly string $purchaseOrderId,
        public readonly string $projectId,
        public readonly string $currency,
        public readonly array $buckets,
    ) {}

    /** @return array<string, mixed> */
    public function toPayload(): array
    {
        return [
            'purchase_order_id' => $this->purchaseOrderId,
            'project_id' => $this->projectId,
            'currency' => $this->currency,
            'buckets' => $this->buckets,
        ];
    }
}
