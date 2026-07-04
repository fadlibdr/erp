<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\Ledger;

use RuntimeException;

/**
 * Maps a logical posting role (e.g. "accounts_receivable") to a concrete GL
 * account code. In the running system these mappings live in `fin_posting_rules`
 * and are per-company configuration — so onboarding a customer with a different
 * chart of accounts is data entry, not a code change. The engine only ever asks
 * this map for a role; it never hardcodes an account number.
 */
final class AccountMap
{
    /**
     * @param  array<string, string>  $roles  role => account code
     */
    public function __construct(
        private readonly array $roles,
    ) {}

    public function code(string $role): string
    {
        if (! isset($this->roles[$role])) {
            throw new RuntimeException("No account mapped for posting role '{$role}'.");
        }

        return $this->roles[$role];
    }
}
