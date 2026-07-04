<?php

declare(strict_types=1);

namespace Modules\Payables\Domain;

use Modules\Platform\Domain\Money;

/**
 * The fully-decomposed result of a subcontractor bill — the payables mirror of a
 * TerminResult. Every statutory and contractual component is broken out so the
 * bill, the accrual journal, and the PPh withholding certificate (bukti potong)
 * all read from one source of truth.
 */
final class SubcontractBillResult
{
    public function __construct(
        public readonly Money $workValue,      // certified subcontract work (the DPP / accrued cost)
        public readonly Money $ppnInput,       // PPN Masukan — creditable input VAT (zero if vendor not PKP)
        public readonly Money $retention,      // retensi withheld from the sub (becomes a payable held to release)
        public readonly Money $pphFinal,       // PPh final konstruksi withheld from the sub, owed to the state
        public readonly Money $netPayable,     // cash actually paid to the subcontractor this bill
        public readonly int $pphRateNumerator, // provenance of the withholding figure (over 10_000)
        public readonly string $pphRegulationRef,
    ) {}

    /** The gross bill face value (work + PPN) before withholdings. */
    public function grossBill(): Money
    {
        return $this->workValue->add($this->ppnInput);
    }
}
