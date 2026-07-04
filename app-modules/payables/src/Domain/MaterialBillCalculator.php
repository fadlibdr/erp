<?php

declare(strict_types=1);

namespace Modules\Payables\Domain;

use Modules\Platform\Domain\Money;
use Modules\Tax\Domain\PpnCalculator;

/**
 * Computes a material vendor bill from the billed goods value — the mirror of
 * SubcontractBillCalculator, but for a supply of goods rather than jasa konstruksi.
 *
 * The one difference that matters: goods carry **no PPh-final konstruksi**, so
 * there is no withholding line. The decomposition is simply
 *
 *   PPN input   = workValue × ppnRate       (creditable — only if the vendor is PKP)
 *   retensi     = workValue × retentionRate (usually zero for a material supply)
 *   net payable = workValue + PPN − retensi
 *
 * The work value is what the goods receipt already accrued to GR/IR; approving the
 * bill clears that accrual (see MaterialBillPostingRule) instead of re-booking cost.
 */
final class MaterialBillCalculator
{
    public function __construct(
        private readonly PpnCalculator $ppn,
    ) {}

    public function calculate(
        Money $workValue,
        int $retentionRatePercent, // usually 0 for goods
        bool $vendorIsPkp,         // non-PKP vendors issue no faktur → no creditable input VAT
    ): MaterialBillResult {
        $ppnInput = $vendorIsPkp ? $this->ppn->on($workValue) : Money::zero($workValue->currency);
        $retention = $workValue->applyRate($retentionRatePercent, 100);

        $netPayable = $workValue
            ->add($ppnInput)
            ->subtract($retention);

        return new MaterialBillResult(
            workValue: $workValue,
            ppnInput: $ppnInput,
            retention: $retention,
            netPayable: $netPayable,
        );
    }
}
