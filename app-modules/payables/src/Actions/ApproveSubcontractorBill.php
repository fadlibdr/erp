<?php

declare(strict_types=1);

namespace Modules\Payables\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Payables\Domain\SubcontractBillCalculator;
use Modules\Payables\Domain\VendorBillFact;
use Modules\Payables\Events\VendorBillApproved;
use Modules\Payables\Models\VendorBill;
use Modules\Platform\Actions\Action;
use Modules\Platform\Domain\Currency;
use Modules\Platform\Domain\Money;
use Modules\Platform\Support\NumberingService;
use Modules\Platform\Support\Outbox;
use Modules\Procurement\Models\Vendor;
use Modules\Tax\Domain\SbuClass;
use Modules\Tax\Domain\ServiceClass;
use Modules\Tax\Services\PphFinalRateRepository;
use RuntimeException;

/**
 * Approves a subcontractor bill — the procure-to-pay money path, the mirror of
 * IssueTerminInvoice.
 *
 *  1. resolve the PPh-final rate from the bill's service class, the SUBCONTRACTOR's
 *     SBU class, and the regime date (the subcontract's own contract date if set,
 *     else the bill date — the transitional rule keys on the contract regime, and a
 *     subcontract carries its own terms rather than inheriting the head contract's);
 *  2. compute the bill (work + PPN input − retensi − PPh withheld);
 *  3. in one transaction: freeze the figures on the bill, mark it approved, and
 *     publish VendorBillApproved to the outbox.
 *
 * The Action never writes a journal. The outbox event, committed in the same
 * transaction, is what the Finance posting engine later turns into a balanced
 * accrual. That decoupling is why Payables has no dependency on Finance's internals.
 */
final class ApproveSubcontractorBill extends Action
{
    public function __construct(
        private readonly SubcontractBillCalculator $calculator,
        private readonly PphFinalRateRepository $rates,
        private readonly Outbox $outbox,
        private readonly NumberingService $numbering,
    ) {}

    public function execute(VendorBill $bill): VendorBill
    {
        if ($bill->status === 'approved') {
            throw new RuntimeException("Bill {$bill->id} is already approved.");
        }

        $vendor = Vendor::query()->findOrFail($bill->vendor_id);
        $currency = Currency::from($bill->currency);

        $regimeDate = optional($bill->contract_date)->format('Y-m-d')
            ?? $bill->bill_date->format('Y-m-d');

        $rate = $this->rates->resolver()->resolve(
            ServiceClass::from($bill->service_class),
            SbuClass::from($vendor->sbu_class ?? SbuClass::None->value),
            $regimeDate,
        );

        $result = $this->calculator->calculate(
            workValue: Money::ofMinor($bill->work_value_minor, $currency),
            retentionRatePercent: $bill->retention_percent,
            pphRate: $rate,
            vendorIsPkp: (bool) $vendor->is_pkp,
        );

        return DB::transaction(function () use ($bill, $result) {
            $bill->fill([
                'number' => $bill->number ?? $this->numbering->next($bill->company_id, 'vendor_bill'),
                'status' => 'approved',
                'ppn_input_minor' => $result->ppnInput->minor,
                'gross_minor' => $result->grossBill()->minor,
                'retention_minor' => $result->retention->minor,
                'pph_withheld_minor' => $result->pphFinal->minor,
                'net_payable_minor' => $result->netPayable->minor,
                'pph_rate_numerator' => $result->pphRateNumerator,
                'pph_regulation_ref' => $result->pphRegulationRef,
            ]);
            $bill->save();

            $fact = VendorBillFact::fromResult(
                $bill->id,
                $bill->project_id,
                $bill->wbs_id,
                $bill->cost_code ?? 'SUB',
                $result,
            );
            $event = new VendorBillApproved($bill->company_id, $fact);
            $this->outbox->publish($event, $event->dedupKey());

            return $bill;
        });
    }
}
