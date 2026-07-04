<?php

declare(strict_types=1);

namespace Modules\Billing\Domain;

use Modules\Platform\Domain\Money;

/**
 * The fully-decomposed result of a termin (progress) claim: every statutory and
 * contractual component broken out, so the invoice, the journal, and the
 * withholding certificate all read from one source of truth.
 */
final class TerminResult
{
    public function __construct(
        public readonly Money $workValue,          // certified work this period (the DPP / revenue base)
        public readonly Money $ppnOutput,          // PPN Keluaran added on top
        public readonly Money $retention,          // retensi withheld by the customer (becomes a receivable)
        public readonly Money $uangMukaRecovery,   // advance repaid this termin (reduces the advance liability)
        public readonly Money $pphFinal,           // PPh final withheld by the customer
        public readonly Money $netReceivable,      // cash the contractor actually collects this termin
        public readonly int $pphRateNumerator,     // provenance of the tax figure (over 10_000)
        public readonly string $pphRegulationRef,
    ) {
    }

    /** The gross invoice face value (work + PPN) before withholdings. */
    public function grossInvoice(): Money
    {
        return $this->workValue->add($this->ppnOutput);
    }
}
