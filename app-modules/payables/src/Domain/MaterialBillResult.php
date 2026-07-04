<?php

declare(strict_types=1);

namespace Modules\Payables\Domain;

use Modules\Platform\Domain\Money;

/**
 * The decomposed result of a material vendor bill — the goods mirror of a
 * SubcontractBillResult, but with no PPh-final withholding (a supply of goods is
 * not jasa konstruksi). The work value here clears the GR/IR accrual the goods
 * receipt raised, rather than booking fresh cost.
 */
final class MaterialBillResult
{
    public function __construct(
        public readonly Money $workValue,  // goods value billed (the DPP; = what GR/IR accrued)
        public readonly Money $ppnInput,   // PPN Masukan — creditable input VAT (zero if vendor not PKP)
        public readonly Money $retention,  // retensi withheld, if the supply carries it (usually zero)
        public readonly Money $netPayable, // cash owed to the vendor this bill
    ) {}

    /** The gross bill face value (work + PPN) before any retention. */
    public function grossBill(): Money
    {
        return $this->workValue->add($this->ppnInput);
    }
}
