<?php

declare(strict_types=1);

namespace Modules\Payables\Domain;

use Modules\Platform\Domain\Money;
use Modules\Tax\Domain\PphFinalRate;
use Modules\Tax\Domain\PpnCalculator;

/**
 * Computes a single subcontractor bill from the certified subcontract work value —
 * the payables mirror of the TerminCalculator.
 *
 * When a main contractor pays a subcontractor for jasa konstruksi it is, from the
 * tax office's view, the same event as its own customer paying it: the payer
 * withholds PPh-final konstruksi at the PP 9/2022 rate keyed on the *payee's* SBU
 * class. So this reuses the very same PphFinalRate the billing side uses, only now
 * the contractor is the withholder rather than the withheld.
 *
 *   PPN input      = workValue × ppnRate       (creditable — only if the vendor is PKP)
 *   retensi        = workValue × retentionRate (withheld → later payable to the sub)
 *   PPh final      = workValue × pphRate        (withheld by us, remitted to the state)
 *   net payable    = workValue + PPN − retensi − PPh
 *
 * Every rate is an exact integer ratio with banker's rounding, so the components
 * always reconcile to the bill with no stray rupiah.
 */
final class SubcontractBillCalculator
{
    public function __construct(
        private readonly PpnCalculator $ppn,
    ) {}

    public function calculate(
        Money $workValue,
        int $retentionRatePercent, // e.g. 5 → 5%
        PphFinalRate $pphRate,
        bool $vendorIsPkp,         // non-PKP vendors issue no faktur → no creditable input VAT
    ): SubcontractBillResult {
        $ppnInput = $vendorIsPkp ? $this->ppn->on($workValue) : Money::zero($workValue->currency);
        $retention = $workValue->applyRate($retentionRatePercent, 100);
        $pphFinal = $workValue->applyRate($pphRate->rateNumerator, PphFinalRate::DENOMINATOR);

        $netPayable = $workValue
            ->add($ppnInput)
            ->subtract($retention)
            ->subtract($pphFinal);

        return new SubcontractBillResult(
            workValue: $workValue,
            ppnInput: $ppnInput,
            retention: $retention,
            pphFinal: $pphFinal,
            netPayable: $netPayable,
            pphRateNumerator: $pphRate->rateNumerator,
            pphRegulationRef: $pphRate->regulationRef,
        );
    }
}
