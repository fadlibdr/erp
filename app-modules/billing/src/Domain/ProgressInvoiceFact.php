<?php

declare(strict_types=1);

namespace Modules\Billing\Domain;

use Modules\Platform\Domain\Money;

/**
 * The typed fact Billing publishes to the outbox when a termin invoice is issued.
 *
 * Billing never writes journal lines. It states *what happened* — the decomposed
 * money components and the project it belongs to — and Finance's posting engine
 * decides how that becomes a balanced journal. This is the seam that keeps all
 * GL logic in one module.
 */
final class ProgressInvoiceFact
{
    public const TYPE = 'billing.progress_invoice_issued';

    public function __construct(
        public readonly string $claimId,
        public readonly string $projectId,
        public readonly Money $workValue,
        public readonly Money $ppnOutput,
        public readonly Money $retention,
        public readonly Money $uangMukaRecovery,
        public readonly Money $pphFinal,
        public readonly Money $netReceivable,
        public readonly int $pphRateNumerator,
        public readonly string $pphRegulationRef,
    ) {
    }

    public static function fromTermin(string $claimId, string $projectId, TerminResult $t): self
    {
        return new self(
            claimId: $claimId,
            projectId: $projectId,
            workValue: $t->workValue,
            ppnOutput: $t->ppnOutput,
            retention: $t->retention,
            uangMukaRecovery: $t->uangMukaRecovery,
            pphFinal: $t->pphFinal,
            netReceivable: $t->netReceivable,
            pphRateNumerator: $t->pphRateNumerator,
            pphRegulationRef: $t->pphRegulationRef,
        );
    }

    /** @return array<string, mixed> stable payload persisted in the outbox row */
    public function toPayload(): array
    {
        return [
            'claim_id' => $this->claimId,
            'project_id' => $this->projectId,
            'currency' => $this->workValue->currency->value,
            'work_value' => $this->workValue->minor,
            'ppn_output' => $this->ppnOutput->minor,
            'retention' => $this->retention->minor,
            'uang_muka_recovery' => $this->uangMukaRecovery->minor,
            'pph_final' => $this->pphFinal->minor,
            'net_receivable' => $this->netReceivable->minor,
            'pph_rate_numerator' => $this->pphRateNumerator,
            'pph_regulation_ref' => $this->pphRegulationRef,
        ];
    }
}
