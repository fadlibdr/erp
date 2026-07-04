<?php

declare(strict_types=1);

namespace Modules\Finance\Services;

use Illuminate\Support\Facades\DB;
use Modules\Finance\Domain\Ledger\GrnPostingRule;
use Modules\Finance\Models\Commitment;
use Modules\Platform\Models\OutboxEvent;
use Modules\Platform\Support\OutboxConsumer;

/**
 * Maintains the commitment projection from procurement facts. It produces no
 * journal — a commitment is an encumbrance, not double-entry — so it is an outbox
 * *consumer*, not a posting rule. On approval it raises one row per budget bucket;
 * on receipt it consumes the matching bucket. It runs inside the relay's
 * per-event transaction, so the projection and the processed stamp commit together.
 *
 * Like every Finance reactor, it names the upstream fact types as local strings
 * rather than importing the publishing module's classes — that is what keeps the
 * dependency arrow pointing down (Procurement → Finance, never the reverse).
 */
final class CommitmentProjector implements OutboxConsumer
{
    /** Mirror of Procurement\Domain\PurchaseOrderFact::TYPE (kept local by design). */
    private const PO_APPROVED = 'procurement.purchase_order_approved';

    private const GOODS_RECEIVED = GrnPostingRule::FACT_TYPE; // 'procurement.goods_received'

    public function handles(string $factType): bool
    {
        return $factType === self::PO_APPROVED || $factType === self::GOODS_RECEIVED;
    }

    public function consume(OutboxEvent $event): void
    {
        $payload = $event->payload;

        if ($event->type === self::PO_APPROVED) {
            $this->raise($event->company_id, $payload);

            return;
        }

        $this->reduce($event->company_id, $payload);
    }

    /** @param array<string, mixed> $payload */
    private function raise(string $companyId, array $payload): void
    {
        foreach ($payload['buckets'] as $bucket) {
            Commitment::create([
                'company_id' => $companyId,
                'project_id' => $payload['project_id'],
                'wbs_id' => $bucket['wbs_id'] ?? null,
                'cost_code' => $bucket['cost_code'] ?? null,
                'source_type' => 'purchase_order',
                'source_id' => $payload['purchase_order_id'],
                'committed_minor' => (int) $bucket['amount'],
                'consumed_minor' => 0,
                'currency' => $payload['currency'],
            ]);
        }
    }

    /** @param array<string, mixed> $payload */
    private function reduce(string $companyId, array $payload): void
    {
        foreach ($payload['buckets'] as $bucket) {
            $query = Commitment::query()
                ->where('company_id', $companyId)
                ->where('source_type', 'purchase_order')
                ->where('source_id', $payload['purchase_order_id']);

            ($bucket['wbs_id'] ?? null) === null
                ? $query->whereNull('wbs_id')
                : $query->where('wbs_id', $bucket['wbs_id']);
            ($bucket['cost_code'] ?? null) === null
                ? $query->whereNull('cost_code')
                : $query->where('cost_code', $bucket['cost_code']);

            $query->update(['consumed_minor' => DB::raw('consumed_minor + '.(int) $bucket['amount'])]);
        }
    }
}
