<?php

declare(strict_types=1);

namespace Modules\Payables\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Payables\Domain\MaterialBillCalculator;
use Modules\Payables\Domain\MaterialBillFact;
use Modules\Payables\Events\MaterialBillApproved;
use Modules\Payables\Models\VendorBill;
use Modules\Platform\Actions\Action;
use Modules\Platform\Domain\Currency;
use Modules\Platform\Domain\Money;
use Modules\Platform\Support\NumberingService;
use Modules\Platform\Support\Outbox;
use Modules\Procurement\Domain\MatchVerdict;
use Modules\Procurement\Domain\ThreeWayMatch;
use Modules\Procurement\Models\Grn;
use Modules\Procurement\Models\GrnLine;
use Modules\Procurement\Models\PurchaseOrder;
use Modules\Procurement\Models\PurchaseOrderLine;
use Modules\Procurement\Models\Vendor;
use RuntimeException;

/**
 * Approves a PO-linked material bill — the last hop of the commitment loop. Unlike a
 * subcontractor bill it withholds no PPh-final (goods are not jasa konstruksi), and
 * its posting *clears* the GR/IR accrual the goods receipt raised rather than booking
 * fresh cost.
 *
 * Approval is gated by three-way match: the quantity received (GRNs) must match the
 * quantity ordered (PO), and the amount billed must match the amount ordered, each
 * within ThreeWayMatch's tolerance. A variance blocks the bill for a buyer's review;
 * a clean match freezes the figures, records the verdict, and publishes the fact.
 */
final class ApproveMaterialBill extends Action
{
    public function __construct(
        private readonly MaterialBillCalculator $calculator,
        private readonly ThreeWayMatch $match,
        private readonly Outbox $outbox,
        private readonly NumberingService $numbering,
    ) {}

    public function execute(VendorBill $bill): VendorBill
    {
        if ($bill->status === 'approved') {
            throw new RuntimeException("Bill {$bill->id} is already approved.");
        }
        if ($bill->purchase_order_id === null) {
            throw new RuntimeException('A material bill must reference a purchase order for three-way match; use the subcontractor path for service bills.');
        }

        $po = PurchaseOrder::query()->findOrFail($bill->purchase_order_id);
        $vendor = Vendor::query()->findOrFail($bill->vendor_id);
        $currency = Currency::from($bill->currency);

        $verdict = $this->match->match(
            orderedQtyMilli: $this->orderedQtyMilli($po->id),
            receivedQtyMilli: $this->receivedQtyMilli($po->id),
            orderedAmountMinor: (int) $po->total_minor,
            billedAmountMinor: (int) $bill->work_value_minor,
        );

        if ($verdict !== MatchVerdict::Matched) {
            throw new RuntimeException("Three-way match failed ({$verdict->value}); bill held for review against PO {$po->id}.");
        }

        $result = $this->calculator->calculate(
            workValue: Money::ofMinor((int) $bill->work_value_minor, $currency),
            retentionRatePercent: (int) $bill->retention_percent,
            vendorIsPkp: (bool) $vendor->is_pkp,
        );

        return DB::transaction(function () use ($bill, $result, $verdict) {
            $bill->fill([
                'number' => $bill->number ?? $this->numbering->next($bill->company_id, 'vendor_bill'),
                'status' => 'approved',
                'match_status' => $verdict->value,
                'ppn_input_minor' => $result->ppnInput->minor,
                'gross_minor' => $result->grossBill()->minor,
                'retention_minor' => $result->retention->minor,
                'pph_withheld_minor' => 0,
                'net_payable_minor' => $result->netPayable->minor,
            ]);
            $bill->save();

            $fact = MaterialBillFact::fromResult($bill->id, $bill->project_id, $bill->cost_code ?? 'MAT', $result);
            $event = new MaterialBillApproved($bill->company_id, $fact);
            $this->outbox->publish($event, $event->dedupKey());

            return $bill;
        });
    }

    private function orderedQtyMilli(string $poId): int
    {
        $qty = (float) PurchaseOrderLine::query()->where('purchase_order_id', $poId)->sum('quantity');

        return (int) round($qty * 1000);
    }

    private function receivedQtyMilli(string $poId): int
    {
        $grnIds = Grn::query()->where('purchase_order_id', $poId)->pluck('id');
        $qty = (float) GrnLine::query()->whereIn('grn_id', $grnIds)->sum('quantity');

        return (int) round($qty * 1000);
    }
}
