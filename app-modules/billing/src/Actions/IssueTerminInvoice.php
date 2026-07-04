<?php

declare(strict_types=1);

namespace Modules\Billing\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Billing\Domain\ProgressInvoiceFact;
use Modules\Billing\Domain\TerminCalculator;
use Modules\Billing\Events\ProgressInvoiceIssued;
use Modules\Billing\Models\ProgressClaim;
use Modules\Platform\Actions\Action;
use Modules\Platform\Domain\Currency;
use Modules\Platform\Domain\Money;
use Modules\Platform\Models\Company;
use Modules\Platform\Support\NumberingService;
use Modules\Platform\Support\Outbox;
use Modules\Projects\Models\Project;
use Modules\Tax\Domain\SbuClass;
use Modules\Tax\Domain\ServiceClass;
use Modules\Tax\Services\PphFinalRateRepository;
use RuntimeException;

/**
 * Issues the termin invoice for a certified progress claim — the money path.
 *
 *  1. resolve the PPh-final rate from the project's service class, the
 *     contractor's SBU class, and the CONTRACT date (transitional rule);
 *  2. compute the termin (work − uang muka recovery − retensi + PPN, PPh withheld);
 *  3. in one transaction: freeze the figures on the claim, mark it invoiced, and
 *     publish ProgressInvoiceIssued to the outbox.
 *
 * The Action never writes a journal. The outbox event, committed in the same
 * transaction, is what the Finance posting engine later turns into a balanced
 * entry. That decoupling is why Billing has no dependency on Finance.
 */
final class IssueTerminInvoice extends Action
{
    public function __construct(
        private readonly TerminCalculator $termin,
        private readonly PphFinalRateRepository $rates,
        private readonly Outbox $outbox,
        private readonly NumberingService $numbering,
    ) {
    }

    public function execute(ProgressClaim $claim): ProgressClaim
    {
        if ($claim->status === 'invoiced') {
            throw new RuntimeException("Claim {$claim->id} is already invoiced.");
        }

        $project = Project::query()->findOrFail($claim->project_id);
        $company = Company::query()->findOrFail($claim->company_id);
        $currency = Currency::from($claim->currency);

        $rate = $this->rates->resolver()->resolve(
            ServiceClass::from($project->service_class),
            SbuClass::from($company->sbu_class ?? SbuClass::None->value),
            optional($project->contract_date)->format('Y-m-d') ?? now()->format('Y-m-d'),
        );

        $result = $this->termin->calculate(
            workValue: Money::ofMinor($claim->work_value_minor, $currency),
            retentionRatePercent: $project->retention_percent,
            uangMukaRecoveryPercent: $project->uang_muka_percent,
            pphRate: $rate,
        );

        return DB::transaction(function () use ($claim, $project, $result, $currency) {
            $claim->fill([
                'number' => $claim->number ?? $this->numbering->next($claim->company_id, 'progress_claim'),
                'status' => 'invoiced',
                'ppn_output_minor' => $result->ppnOutput->minor,
                'retention_minor' => $result->retention->minor,
                'uang_muka_recovery_minor' => $result->uangMukaRecovery->minor,
                'pph_final_minor' => $result->pphFinal->minor,
                'net_receivable_minor' => $result->netReceivable->minor,
                'pph_rate_numerator' => $result->pphRateNumerator,
                'pph_regulation_ref' => $result->pphRegulationRef,
            ]);
            $claim->save();

            $fact = ProgressInvoiceFact::fromTermin($claim->id, $project->id, $result);
            $event = new ProgressInvoiceIssued($claim->company_id, $fact);
            $this->outbox->publish($event, $event->dedupKey());

            return $claim;
        });
    }
}
