<?php

declare(strict_types=1);

namespace Modules\Finance\Services;

use Illuminate\Support\Facades\DB;
use Modules\Finance\Domain\Ledger\JournalDraft;
use Modules\Finance\Domain\Ledger\JournalLineDraft;
use Modules\Finance\Models\Journal;
use Modules\Finance\Models\JournalLine;
use Modules\Platform\Domain\Currency;
use Modules\Platform\Domain\Money;
use Modules\Platform\Support\NumberingService;

/**
 * The single point at which a validated JournalDraft becomes rows in the ledger.
 * Nothing else in the codebase inserts into fin_journals / fin_journal_lines —
 * that is what the deptrac rules and arch tests protect, and it is why the
 * double-entry invariant only has to be guarded here (plus the DB trigger).
 *
 * The draft is already balanced by construction; this class only persists it,
 * inside a transaction, refusing to post into a closed fiscal period.
 */
final class LedgerPosting
{
    public function __construct(
        private readonly NumberingService $numbering,
        private readonly FiscalPeriodGuard $periods,
    ) {
    }

    public function post(string $companyId, JournalDraft $draft, string $date): Journal
    {
        return DB::transaction(function () use ($companyId, $draft, $date) {
            $this->periods->assertOpen($companyId, $date);

            $journal = Journal::create([
                'company_id' => $companyId,
                'number' => $this->numbering->next($companyId, 'journal'),
                'date' => $date,
                'description' => $draft->description,
                'fact_type' => $draft->factType ?: null,
                'source_reference' => $draft->sourceReference,
                'currency' => $draft->currency->value,
                'total_minor' => $draft->total()->minor,
            ]);

            foreach ($draft->lines as $line) {
                JournalLine::create([
                    'journal_id' => $journal->id,
                    'company_id' => $companyId,
                    'account_code' => $line->accountCode,
                    'debit_minor' => $line->debit->minor,
                    'credit_minor' => $line->credit->minor,
                    'currency' => $line->currency()->value,
                    'project_id' => $line->projectId,
                    'wbs_id' => $line->wbsId,
                    'cost_code' => $line->costCode,
                    'memo' => $line->memo,
                ]);
            }

            return $journal;
        });
    }

    /** Post the correcting reversal of an existing journal. */
    public function reverse(Journal $journal, string $reason, string $date): Journal
    {
        $original = $this->rebuildDraft($journal);
        $reversal = $original->reverse($reason);
        $posted = $this->post($journal->company_id, $reversal, $date);
        $posted->update(['reverses_journal_id' => $journal->id]);

        return $posted;
    }

    private function rebuildDraft(Journal $journal): JournalDraft
    {
        // Reconstruct a draft from stored lines so reverse() can flip it. Kept
        // private: callers reverse whole journals, they never hand-edit lines.
        $currency = Currency::from($journal->currency);

        $lines = $journal->lines->map(function (JournalLine $line) use ($currency): JournalLineDraft {
            $money = static fn (int $minor) => Money::ofMinor($minor, $currency);

            return $line->debit_minor > 0
                ? JournalLineDraft::debit($line->account_code, $money($line->debit_minor), $line->project_id, $line->wbs_id, $line->cost_code, $line->memo)
                : JournalLineDraft::credit($line->account_code, $money($line->credit_minor), $line->project_id, $line->wbs_id, $line->cost_code, $line->memo);
        })->all();

        return new JournalDraft(
            description: $journal->description,
            lines: array_values($lines),
            factType: (string) $journal->fact_type,
            sourceReference: $journal->source_reference,
        );
    }
}
