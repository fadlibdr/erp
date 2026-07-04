<?php

declare(strict_types=1);

namespace Modules\Payroll\Domain;

use Modules\Platform\Domain\Money;

/**
 * One employee's fully-decomposed monthly pay: gross, the PPh 21 withheld, the BPJS
 * split, and the net take-home. net = gross − PPh 21 − BPJS employee.
 */
final class PayrollResult
{
    public function __construct(
        public readonly Money $gross,
        public readonly Money $pph21,
        public readonly Money $bpjsEmployee,
        public readonly Money $bpjsEmployer,
        public readonly Money $net,
    ) {}
}
