<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\Ledger;

use Modules\Platform\Domain\Currency;
use Modules\Platform\Domain\Money;

/**
 * One side of a journal entry. Exactly one of debit/credit is non-zero. The
 * project / WBS / cost-code dimensions ride on every line so the whole ledger
 * is sliceable by project without a separate cost system.
 */
final class JournalLineDraft
{
    private function __construct(
        public readonly string $accountCode,
        public readonly Money $debit,
        public readonly Money $credit,
        public readonly ?string $projectId = null,
        public readonly ?string $wbsId = null,
        public readonly ?string $costCode = null,
        public readonly ?string $memo = null,
    ) {}

    public static function debit(
        string $accountCode,
        Money $amount,
        ?string $projectId = null,
        ?string $wbsId = null,
        ?string $costCode = null,
        ?string $memo = null,
    ): self {
        return new self($accountCode, $amount, Money::zero($amount->currency), $projectId, $wbsId, $costCode, $memo);
    }

    public static function credit(
        string $accountCode,
        Money $amount,
        ?string $projectId = null,
        ?string $wbsId = null,
        ?string $costCode = null,
        ?string $memo = null,
    ): self {
        return new self($accountCode, Money::zero($amount->currency), $amount, $projectId, $wbsId, $costCode, $memo);
    }

    public function currency(): Currency
    {
        return $this->debit->currency;
    }

    public function isSingleSided(): bool
    {
        return $this->debit->isZero() !== $this->credit->isZero();
    }

    public function reversed(): self
    {
        return new self(
            $this->accountCode,
            $this->credit,
            $this->debit,
            $this->projectId,
            $this->wbsId,
            $this->costCode,
            $this->memo,
        );
    }
}
