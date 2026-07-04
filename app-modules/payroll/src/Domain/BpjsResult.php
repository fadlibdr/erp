<?php

declare(strict_types=1);

namespace Modules\Payroll\Domain;

use Modules\Platform\Domain\Money;

/**
 * BPJS contributions split by who bears them: the employee share (deducted from
 * take-home pay) and the employer share (a company cost). Both are remitted to BPJS.
 */
final class BpjsResult
{
    public function __construct(
        public readonly Money $employee,
        public readonly Money $employer,
    ) {}

    /** Total remitted to BPJS (employee + employer) — the payable the run books. */
    public function total(): Money
    {
        return $this->employee->add($this->employer);
    }
}
