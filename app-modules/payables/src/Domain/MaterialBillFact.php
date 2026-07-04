<?php

declare(strict_types=1);

namespace Modules\Payables\Domain;

/**
 * The fact Payables publishes when a PO-linked material bill is approved. Like the
 * subcontract fact it states the decomposed money components; Finance's
 * MaterialBillPostingRule turns them into a balanced entry that *clears* the GR/IR
 * accrual the goods receipt raised — no PPh, no fresh cost.
 */
final class MaterialBillFact
{
    public const TYPE = 'payables.material_bill_approved';

    public function __construct(
        public readonly string $billId,
        public readonly ?string $projectId,
        public readonly string $costCode,
        public readonly string $currency,
        public readonly int $workValueMinor,
        public readonly int $ppnInputMinor,
        public readonly int $retentionMinor,
        public readonly int $netPayableMinor,
    ) {}

    public static function fromResult(string $billId, ?string $projectId, string $costCode, MaterialBillResult $r): self
    {
        return new self(
            billId: $billId,
            projectId: $projectId,
            costCode: $costCode,
            currency: $r->workValue->currency->value,
            workValueMinor: $r->workValue->minor,
            ppnInputMinor: $r->ppnInput->minor,
            retentionMinor: $r->retention->minor,
            netPayableMinor: $r->netPayable->minor,
        );
    }

    /** @return array<string, mixed> */
    public function toPayload(): array
    {
        return [
            'bill_id' => $this->billId,
            'project_id' => $this->projectId,
            'cost_code' => $this->costCode,
            'currency' => $this->currency,
            'work_value' => $this->workValueMinor,
            'ppn_input' => $this->ppnInputMinor,
            'retention' => $this->retentionMinor,
            'net_payable' => $this->netPayableMinor,
        ];
    }
}
