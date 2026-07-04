<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\Ledger;

/**
 * A posting rule turns one typed fact into one balanced JournalDraft.
 *
 * The set of *roles* a fact touches (and the debit/credit shape) is code — that
 * is accounting logic and belongs in typed, tested classes, never in runtime
 * configuration. The *account each role maps to* is data (AccountMap). That split
 * is deliberate: it keeps the double-entry invariant in code while letting each
 * customer keep their own chart of accounts.
 */
interface PostingRule
{
    /** The fact type this rule handles, e.g. ProgressInvoiceFact::TYPE. */
    public function factType(): string;

    /**
     * @param  array<string, mixed>  $payload  the fact's persisted payload
     */
    public function toJournal(array $payload, AccountMap $accounts): JournalDraft;
}
