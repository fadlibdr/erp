<?php

declare(strict_types=1);

namespace Modules\Payroll\Domain;

/**
 * The fact Payroll publishes when a run is approved. It states the run's totals and
 * the project/WBS the labor belongs to; Finance's PayrollPostingRule turns them into
 * the balanced labor-cost entry (wages + employer BPJS vs net + PPh 21 + BPJS payable).
 */
final class PayrollRunFact
{
    public const TYPE = 'payroll.run_approved';

    public function __construct(
        public readonly string $runId,
        public readonly ?string $projectId,
        public readonly ?string $wbsId,
        public readonly string $costCode,
        public readonly string $currency,
        public readonly int $grossMinor,
        public readonly int $pph21Minor,
        public readonly int $bpjsEmployeeMinor,
        public readonly int $bpjsEmployerMinor,
        public readonly int $netMinor,
    ) {}

    /** @return array<string, mixed> */
    public function toPayload(): array
    {
        return [
            'run_id' => $this->runId,
            'project_id' => $this->projectId,
            'wbs_id' => $this->wbsId,
            'cost_code' => $this->costCode,
            'currency' => $this->currency,
            'labor' => $this->grossMinor,
            'pph21' => $this->pph21Minor,
            'bpjs_employee' => $this->bpjsEmployeeMinor,
            'bpjs_employer' => $this->bpjsEmployerMinor,
            'net' => $this->netMinor,
        ];
    }
}
