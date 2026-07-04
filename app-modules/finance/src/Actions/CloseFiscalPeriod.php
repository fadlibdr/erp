<?php

declare(strict_types=1);

namespace Modules\Finance\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Finance\Domain\Ledger\Psak72PostingRule;
use Modules\Finance\Domain\Psak72Calculator;
use Modules\Finance\Models\FiscalPeriod;
use Modules\Finance\Models\RevrecRun;
use Modules\Finance\Services\PostingRuleEngine;
use Modules\Platform\Actions\Action;
use Modules\Platform\Domain\Currency;
use Modules\Platform\Domain\Money;
use RuntimeException;

/**
 * Month-end close — the highest-risk workflow in the product (a period-close bug at
 * a pilot is existential). It recognises PSAK 72 revenue across the company's active
 * projects, then hard-locks the period so nothing else posts into it.
 *
 * The caller supplies each project's commercials (contract value, estimated total
 * cost, billed-to-date) — those live in Projects/Billing, above Finance in the
 * dependency law, so Finance is *given* them rather than reaching up for them. What
 * Finance owns it computes itself: cost-to-date is the net of the project's expense
 * postings in its own ledger.
 *
 * Order matters: recognition is posted **while the period is still open** (through
 * the same engine every other fact uses, so FiscalPeriodGuard lets it through),
 * the run is recorded, and only then is the period locked. Everything runs in one
 * transaction, so a failure mid-close leaves the period open and unrecognised.
 *
 * @phpstan-type ProjectClose array{project_id: string, contract_value_minor: int, estimated_total_cost_minor: int, billed_to_date_minor: int}
 */
final class CloseFiscalPeriod extends Action
{
    public function __construct(
        private readonly Psak72Calculator $psak72,
        private readonly PostingRuleEngine $engine,
    ) {}

    /**
     * @param  list<ProjectClose>  $projects
     * @return list<RevrecRun>
     */
    public function execute(string $companyId, string $periodLabel, string $closeDate, array $projects, string $currencyCode = 'IDR'): array
    {
        $currency = Currency::from($currencyCode);

        /** @var FiscalPeriod|null $period */
        $period = FiscalPeriod::query()
            ->where('company_id', $companyId)
            ->where('label', $periodLabel)
            ->first();

        if ($period === null) {
            throw new RuntimeException("Fiscal period {$periodLabel} does not exist for this company; create it before closing.");
        }
        if ($period->isClosed()) {
            throw new RuntimeException("Fiscal period {$periodLabel} is already closed.");
        }

        return DB::transaction(function () use ($companyId, $period, $closeDate, $projects, $currency): array {
            $runs = [];

            foreach ($projects as $p) {
                $runs[] = $this->recognizeProject($companyId, $period, $closeDate, $p, $currency);
            }

            $period->update(['status' => 'closed', 'closed_at' => now()]);

            return $runs;
        });
    }

    /**
     * @param  array{project_id: string, contract_value_minor: int, estimated_total_cost_minor: int, billed_to_date_minor: int}  $p
     */
    private function recognizeProject(string $companyId, FiscalPeriod $period, string $closeDate, array $p, Currency $currency): RevrecRun
    {
        $prior = $this->priorRun($companyId, $p['project_id'], $period->id);
        $priorRecognized = Money::ofMinor($prior['recognized'], $currency);
        $priorBilled = $prior['billed'];

        $costToDate = Money::ofMinor($this->costToDate($companyId, $p['project_id']), $currency);
        $billedToDate = Money::ofMinor((int) $p['billed_to_date_minor'], $currency);
        $contractValue = Money::ofMinor((int) $p['contract_value_minor'], $currency);
        $estTotalCost = Money::ofMinor((int) $p['estimated_total_cost_minor'], $currency);

        $poc = $this->psak72->pocRatioPpm($costToDate, $estTotalCost);
        $result = $this->psak72->recognize($contractValue, $poc, $priorRecognized, $billedToDate);

        $billedThisPeriod = (int) $p['billed_to_date_minor'] - $priorBilled;
        $trueUp = $result->periodRecognition->minor - $billedThisPeriod;

        $run = new RevrecRun([
            'company_id' => $companyId,
            'project_id' => $p['project_id'],
            'fiscal_period_id' => $period->id,
            'poc_ratio_ppm' => $poc,
            'recognized_to_date_minor' => $result->recognizedToDate->minor,
            'billed_to_date_minor' => $billedToDate->minor,
            'contract_asset_minor' => $result->contractAsset->minor,
            'contract_liability_minor' => $result->contractLiability->minor,
            'currency' => $currency->value,
        ]);
        $run->save();

        if ($trueUp !== 0) {
            $journal = $this->engine->post(
                $companyId,
                Psak72PostingRule::FACT_TYPE,
                [
                    'project_id' => $p['project_id'],
                    'currency' => $currency->value,
                    'true_up' => $trueUp,
                    'revrec_run_id' => $run->id,
                ],
                $closeDate,
            );
            $run->update(['journal_id' => $journal?->id]);
        }

        return $run;
    }

    /** Net of the project's expense postings — the cost-to-date the POC ratio needs. */
    private function costToDate(string $companyId, string $projectId): int
    {
        return (int) DB::table('fin_journal_lines as l')
            ->join('fin_accounts as a', function ($join) {
                $join->on('a.code', '=', 'l.account_code')->on('a.company_id', '=', 'l.company_id');
            })
            ->where('l.company_id', $companyId)
            ->where('l.project_id', $projectId)
            ->where('a.type', 'expense')
            ->sum(DB::raw('l.debit_minor - l.credit_minor'));
    }

    /**
     * The most recent recognition run for this project in an earlier period, so the
     * close recognises only the incremental movement.
     *
     * @return array{recognized: int, billed: int}
     */
    private function priorRun(string $companyId, string $projectId, string $currentPeriodId): array
    {
        /** @var RevrecRun|null $prior */
        $prior = RevrecRun::query()
            ->where('company_id', $companyId)
            ->where('project_id', $projectId)
            ->where('fiscal_period_id', '!=', $currentPeriodId)
            ->orderByDesc('created_at')
            ->first();

        return [
            'recognized' => (int) ($prior->recognized_to_date_minor ?? 0),
            'billed' => (int) ($prior->billed_to_date_minor ?? 0),
        ];
    }
}
