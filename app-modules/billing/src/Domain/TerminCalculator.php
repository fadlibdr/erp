<?php

declare(strict_types=1);

namespace Modules\Billing\Domain;

use Modules\Platform\Domain\Money;
use Modules\Tax\Domain\PphFinalRate;
use Modules\Tax\Domain\PpnCalculator;

/**
 * Computes a single termin (progress) claim from the certified work value.
 *
 * The Indonesian termin formula, made exact:
 *
 *   PPN output        = workValue × ppnRate
 *   retensi           = workValue × retentionRate        (withheld → later receivable)
 *   uang muka recovery= workValue × recoveryRate          (repays the advance)
 *   PPh final         = workValue × pphRate               (withheld by the payer)
 *   net receivable    = workValue + PPN − retensi − uangMukaRecovery − PPh
 *
 * Every rate is applied as an integer ratio with banker's rounding, so the
 * components always reconcile to the invoice with no stray rupiah.
 */
final class TerminCalculator
{
    public function __construct(
        private readonly PpnCalculator $ppn,
    ) {}

    public function calculate(
        Money $workValue,
        int $retentionRatePercent,     // e.g. 5  → 5%
        int $uangMukaRecoveryPercent,  // e.g. 20 → recover 20% of this termin against the advance
        PphFinalRate $pphRate,
    ): TerminResult {
        $ppnOutput = $this->ppn->on($workValue);
        $retention = $workValue->applyRate($retentionRatePercent, 100);
        $uangMukaRecovery = $workValue->applyRate($uangMukaRecoveryPercent, 100);
        $pphFinal = $workValue->applyRate($pphRate->rateNumerator, PphFinalRate::DENOMINATOR);

        $netReceivable = $workValue
            ->add($ppnOutput)
            ->subtract($retention)
            ->subtract($uangMukaRecovery)
            ->subtract($pphFinal);

        return new TerminResult(
            workValue: $workValue,
            ppnOutput: $ppnOutput,
            retention: $retention,
            uangMukaRecovery: $uangMukaRecovery,
            pphFinal: $pphFinal,
            netReceivable: $netReceivable,
            pphRateNumerator: $pphRate->rateNumerator,
            pphRegulationRef: $pphRate->regulationRef,
        );
    }
}
