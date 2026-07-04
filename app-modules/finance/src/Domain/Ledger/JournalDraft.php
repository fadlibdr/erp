<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\Ledger;

use Modules\Platform\Domain\Currency;
use Modules\Platform\Domain\Money;

/**
 * A balanced set of journal lines, validated before it can be persisted.
 *
 * This is the in-memory aggregate that guards the double-entry invariant:
 * a JournalDraft cannot be constructed unless debits equal credits, every line
 * is single-sided, and all lines share one currency. The persistence layer
 * (Journal / JournalLine Eloquent models) only ever writes an already-validated
 * draft, and a Postgres deferred-constraint trigger backstops the same rule at
 * the database — two independent guards on the one invariant that must never break.
 *
 * Journals are immutable: there is no edit. A wrong journal is corrected by
 * posting its reverse() and then the right one.
 */
final class JournalDraft
{
    /** @var list<JournalLineDraft> */
    public readonly array $lines;

    public readonly Currency $currency;

    /**
     * @param  list<JournalLineDraft>  $lines
     */
    public function __construct(
        public readonly string $description,
        array $lines,
        public readonly string $factType = '',
        public readonly ?string $sourceReference = null,
    ) {
        if (count($lines) < 2) {
            throw LedgerException::empty();
        }

        $currency = $lines[0]->currency();
        $debit = 0;
        $credit = 0;

        foreach ($lines as $line) {
            if (! $line->isSingleSided()) {
                throw LedgerException::lineNotSingleSided();
            }
            if ($line->currency() !== $currency) {
                throw LedgerException::mixedCurrency();
            }
            $debit += $line->debit->minor;
            $credit += $line->credit->minor;
        }

        if ($debit !== $credit) {
            throw LedgerException::unbalanced($debit, $credit);
        }

        $this->lines = array_values($lines);
        $this->currency = $currency;
    }

    public function total(): Money
    {
        $sum = 0;
        foreach ($this->lines as $line) {
            $sum += $line->debit->minor;
        }

        return Money::ofMinor($sum, $this->currency);
    }

    /** The correcting entry: a new balanced draft with every line flipped. */
    public function reverse(string $reason): self
    {
        return new self(
            sprintf('REVERSAL: %s (%s)', $this->description, $reason),
            array_map(fn (JournalLineDraft $l) => $l->reversed(), $this->lines),
            $this->factType,
            $this->sourceReference,
        );
    }
}
