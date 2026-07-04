<?php

declare(strict_types=1);

namespace Modules\Procurement\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Platform\Support\NumberingService;
use Modules\Platform\Support\Outbox;
use Modules\Procurement\Domain\GoodsReceivedFact;
use Modules\Procurement\Events\GoodsReceived;
use Modules\Procurement\Models\Grn;
use Modules\Procurement\Models\GrnLine;
use Modules\Procurement\Models\PurchaseOrder;
use Modules\Procurement\Models\PurchaseOrderLine;
use RuntimeException;

/**
 * Records a goods receipt against a PO. It writes the GRN header + lines, then
 * publishes one fact that fans out to three consumers (GR/IR accrual, commitment
 * consumption, stock movement). Each received line inherits its WBS / cost code
 * from the PO line it fulfils, so the receipt consumes the very commitment bucket
 * the approval raised.
 *
 * @phpstan-type Receipt array{purchase_order_line_id: string, item_id: ?string, warehouse_id: ?string, qty_milli: int, amount_minor: int}
 */
final class ReceiveGoods extends Action
{
    public function __construct(
        private readonly Outbox $outbox,
        private readonly NumberingService $numbering,
    ) {}

    /**
     * @param  list<Receipt>  $receipts
     */
    public function execute(PurchaseOrder $po, array $receipts, string $receivedDate): Grn
    {
        if ($receipts === []) {
            throw new RuntimeException('A goods receipt needs at least one line.');
        }
        if ($po->project_id === null) {
            throw new RuntimeException('Cannot receive goods for a PO with no project.');
        }

        // Resolve each receipt's WBS/cost code from its PO line.
        $poLines = PurchaseOrderLine::query()
            ->whereIn('id', array_column($receipts, 'purchase_order_line_id'))
            ->get()
            ->keyBy('id');

        $total = 0;
        $buckets = [];
        $factLines = [];
        $rowsToInsert = [];

        foreach ($receipts as $r) {
            $poLine = $poLines->get($r['purchase_order_line_id']);
            if ($poLine === null) {
                throw new RuntimeException("Receipt references PO line {$r['purchase_order_line_id']} not on this PO.");
            }
            $amount = (int) $r['amount_minor'];
            $total += $amount;

            $key = ($poLine->wbs_id ?? '∅').'|'.($poLine->cost_code ?? '∅');
            if (! isset($buckets[$key])) {
                $buckets[$key] = ['wbs_id' => $poLine->wbs_id, 'cost_code' => $poLine->cost_code, 'amount' => 0];
            }
            $buckets[$key]['amount'] += $amount;

            $factLines[] = [
                'item_id' => $r['item_id'] ?? null,
                'warehouse_id' => $r['warehouse_id'] ?? null,
                'wbs_id' => $poLine->wbs_id,
                'cost_code' => $poLine->cost_code,
                'qty_milli' => (int) $r['qty_milli'],
                'value_minor' => $amount,
            ];

            $rowsToInsert[] = [
                'purchase_order_line_id' => $poLine->id,
                'item_id' => $r['item_id'] ?? null,
                'warehouse_id' => $r['warehouse_id'] ?? null,
                'wbs_id' => $poLine->wbs_id,
                'cost_code' => $poLine->cost_code,
                'quantity' => $r['qty_milli'] / 1000,
                'amount_minor' => $amount,
            ];
        }

        return DB::transaction(function () use ($po, $total, $buckets, $factLines, $rowsToInsert, $receivedDate) {
            $grn = Grn::create([
                'company_id' => $po->company_id,
                'purchase_order_id' => $po->id,
                'number' => $this->numbering->next($po->company_id, 'grn'),
                'received_date' => $receivedDate,
                'total_minor' => $total,
                'currency' => $po->currency,
            ]);

            foreach ($rowsToInsert as $row) {
                GrnLine::create(['grn_id' => $grn->id] + $row);
            }

            $po->update(['status' => 'received']);

            $fact = new GoodsReceivedFact(
                grnId: $grn->id,
                purchaseOrderId: $po->id,
                projectId: $po->project_id,
                currency: $po->currency,
                amount: $total,
                buckets: array_values($buckets),
                lines: $factLines,
            );
            $event = new GoodsReceived($po->company_id, $fact);
            $this->outbox->publish($event, $event->dedupKey());

            return $grn;
        });
    }
}
