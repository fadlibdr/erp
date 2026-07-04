<?php

declare(strict_types=1);

namespace Modules\Finance\Services;

use Illuminate\Support\Facades\DB;
use Modules\Finance\Domain\Ledger\AccountMap;
use Modules\Finance\Domain\Ledger\PostingRule;
use Modules\Finance\Models\Journal;
use RuntimeException;

/**
 * Turns an outbox fact into a balanced journal.
 *
 * This is the ONLY place domain facts become accounting. A domain module emits a
 * typed fact (e.g. ProgressInvoiceFact) to the outbox; the relay hands the fact
 * here; the engine finds the PostingRule registered for that fact type, resolves
 * the company's account mapping from fin_posting_rules, builds a JournalDraft,
 * and posts it. GL logic lives in one module; per-customer account numbers are
 * data. If no rule is registered for a fact type the engine is silent — not every
 * fact is financial.
 */
final class PostingRuleEngine
{
    /** @var array<string, PostingRule> keyed by fact type */
    private array $rules = [];

    public function __construct(
        private readonly LedgerPosting $ledger,
    ) {
    }

    public function register(PostingRule $rule): void
    {
        $this->rules[$rule->factType()] = $rule;
    }

    public function handles(string $factType): bool
    {
        return isset($this->rules[$factType]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function post(string $companyId, string $factType, array $payload, string $date): ?Journal
    {
        $rule = $this->rules[$factType] ?? null;
        if ($rule === null) {
            return null; // not a financial fact
        }

        $accounts = $this->accountMapFor($companyId, $factType);
        $draft = $rule->toJournal($payload, $accounts);

        return $this->ledger->post($companyId, $draft, $date);
    }

    private function accountMapFor(string $companyId, string $factType): AccountMap
    {
        $roles = DB::table('fin_posting_rules')
            ->where('company_id', $companyId)
            ->where('fact_type', $factType)
            ->pluck('account_code', 'role')
            ->all();

        if ($roles === []) {
            throw new RuntimeException(
                "No posting rules configured for fact '{$factType}' on this company. " .
                'Seed fin_posting_rules for the company before posting.',
            );
        }

        /** @var array<string, string> $roles */
        return new AccountMap($roles);
    }
}
