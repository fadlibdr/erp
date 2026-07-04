<?php

declare(strict_types=1);

namespace Modules\Payroll\Domain;

use Modules\Platform\Domain\Money;
use Modules\Tax\Domain\Pph21TerCalculator;
use Modules\Tax\Domain\PtkpStatus;

/**
 * Computes one employee's monthly pay: withhold PPh 21 (the TER engine in Tax, the
 * same shape as the PPh-final resolver) and BPJS employee share from the gross to get
 * net take-home; the BPJS employer share is a separate company cost the run also
 * books. Every figure is exact integer money — no floats in payroll.
 */
final class PayrollCalculator
{
    public function __construct(
        private readonly Pph21TerCalculator $pph21,
        private readonly BpjsCalculator $bpjs,
    ) {}

    public function calculate(Money $gross, PtkpStatus $status): PayrollResult
    {
        $pph21 = $this->pph21->monthlyWithholding($gross, $status);
        $bpjs = $this->bpjs->on($gross);

        $net = $gross->subtract($pph21)->subtract($bpjs->employee);

        return new PayrollResult(
            gross: $gross,
            pph21: $pph21,
            bpjsEmployee: $bpjs->employee,
            bpjsEmployer: $bpjs->employer,
            net: $net,
        );
    }
}
