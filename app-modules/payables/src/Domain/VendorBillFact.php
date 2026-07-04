<?php

declare(strict_types=1);

namespace Modules\Payables\Domain;

use Modules\Platform\Domain\Money;

/**
 * The typed fact Payables publishes to the outbox when a subcontractor bill is
 * approved. Like Billing, Payables never writes journal lines — it states *what
 * happened* (the decomposed money components, the project/WBS/cost-code the cost
 * belongs to) and Finance's posting engine decides how that becomes a balanced
 * accrual. This is the same seam that keeps all GL logic in one module.
 */
final class VendorBillFact
{
    public const TYPE = 'payables.vendor_bill_approved';

    public function __construct(
        public readonly string $billId,
        public readonly ?string $projectId,
        public readonly ?string $wbsId,
        public readonly string $costCode,
        public readonly Money $workValue,
        public readonly Money $ppnInput,
        public readonly Money $retention,
        public readonly Money $pphFinal,
        public readonly Money $netPayable,
        public readonly int $pphRateNumerator,
        public readonly string $pphRegulationRef,
    ) {}

    public static function fromResult(
        string $billId,
        ?string $projectId,
        ?string $wbsId,
        string $costCode,
        SubcontractBillResult $r,
    ): self {
        return new self(
            billId: $billId,
            projectId: $projectId,
            wbsId: $wbsId,
            costCode: $costCode,
            workValue: $r->workValue,
            ppnInput: $r->ppnInput,
            retention: $r->retention,
            pphFinal: $r->pphFinal,
            netPayable: $r->netPayable,
            pphRateNumerator: $r->pphRateNumerator,
            pphRegulationRef: $r->pphRegulationRef,
        );
    }

    /** @return array<string, mixed> stable payload persisted in the outbox row */
    public function toPayload(): array
    {
        return [
            'bill_id' => $this->billId,
            'project_id' => $this->projectId,
            'wbs_id' => $this->wbsId,
            'cost_code' => $this->costCode,
            'currency' => $this->workValue->currency->value,
            'work_value' => $this->workValue->minor,
            'ppn_input' => $this->ppnInput->minor,
            'retention' => $this->retention->minor,
            'pph_final' => $this->pphFinal->minor,
            'net_payable' => $this->netPayable->minor,
            'pph_rate_numerator' => $this->pphRateNumerator,
            'pph_regulation_ref' => $this->pphRegulationRef,
        ];
    }
}
