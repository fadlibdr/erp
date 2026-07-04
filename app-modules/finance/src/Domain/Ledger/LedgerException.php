<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\Ledger;

use RuntimeException;

final class LedgerException extends RuntimeException
{
    public static function unbalanced(int $debitMinor, int $creditMinor): self
    {
        return new self(sprintf(
            'Journal does not balance: debits=%d, credits=%d, difference=%d.',
            $debitMinor,
            $creditMinor,
            $debitMinor - $creditMinor,
        ));
    }

    public static function empty(): self
    {
        return new self('A journal must have at least two lines.');
    }

    public static function mixedCurrency(): self
    {
        return new self('All lines of a journal must share one currency.');
    }

    public static function lineNotSingleSided(): self
    {
        return new self('A journal line must be either a debit or a credit, never both or neither.');
    }
}
