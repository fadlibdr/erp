<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\Account;

/**
 * The five statement classes, and which side increases each. The normal balance
 * is what lets reports know whether a debit raises or lowers an account without
 * per-account configuration.
 */
enum AccountType: string
{
    case Asset = 'asset';
    case Liability = 'liability';
    case Equity = 'equity';
    case Revenue = 'revenue';
    case Expense = 'expense';

    /** 'debit' or 'credit' — the side on which this account normally increases. */
    public function normalBalance(): string
    {
        return match ($this) {
            self::Asset, self::Expense => 'debit',
            self::Liability, self::Equity, self::Revenue => 'credit',
        };
    }

    public function isDebitNormal(): bool
    {
        return $this->normalBalance() === 'debit';
    }
}
