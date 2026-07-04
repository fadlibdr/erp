<?php

declare(strict_types=1);

namespace Modules\Procurement\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Finance\Domain\BudgetControlPolicy;
use Modules\Finance\Domain\BudgetVerdict;
use Modules\Finance\Services\CommitmentRepository;
use Modules\Platform\Actions\Action;
use Modules\Platform\Support\NumberingService;
use Modules\Platform\Support\Outbox;
use Modules\Procurement\Domain\PurchaseOrderFact;
use Modules\Procurement\Events\PurchaseOrderApproved;
use Modules\Procurement\Models\PurchaseOrder;
use Modules\Projects\Models\BudgetLine;
use RuntimeException;

/**
 * Approves a PO against the project control budget — the head of the commitment
 * loop, and the "C" (cost control) of EPC.
 *
 * The PO's lines are grouped into (WBS × cost code) budget buckets. For each bucket
 * the action reads the budget ceiling (Projects) and the current exposure — open
 * commitments + booked actuals (Finance) — and asks BudgetControlPolicy to decide.
 * A BLOCK on any bucket refuses the whole PO; a WARN flags it but lets it through.
 * On success it freezes the PO approved and publishes the fact that raises the
 * commitment. The action never writes fin_commitments itself — that is Finance's
 * projection, reached only through the outbox.
 *
 * A bucket with no budget line defined is treated as unconstrained (no ceiling to
 * enforce yet); defining the budget later is what turns the gate on.
 */
final class ApprovePurchaseOrder extends Action
{
    public function __construct(
        private readonly BudgetControlPolicy $policy,
        private readonly CommitmentRepository $commitments,
        private readonly Outbox $outbox,
        private readonly NumberingService $numbering,
    ) {}

    public function execute(PurchaseOrder $po): PurchaseOrder
    {
        if ($po->status === 'approved') {
            throw new RuntimeException("Purchase order {$po->id} is already approved.");
        }
        if ($po->project_id === null) {
            throw new RuntimeException('A purchase order must belong to a project to check its budget.');
        }

        $buckets = $this->bucketize($po);
        $worst = BudgetVerdict::Ok;

        foreach ($buckets as $bucket) {
            $budget = $this->budgetFor($po->project_id, $bucket['wbs_id'], $bucket['cost_code']);
            if ($budget === null) {
                continue; // no ceiling defined for this bucket — nothing to enforce yet
            }

            $exposure = $this->commitments->exposureFor($po->company_id, $po->project_id, $bucket['wbs_id'], $bucket['cost_code']);
            $decision = $this->policy->decide($budget, $exposure['open'], $exposure['actual'], $bucket['amount']);

            if ($decision->verdict === BudgetVerdict::Block) {
                throw new RuntimeException(sprintf(
                    'Budget exceeded for %s/%s: request %d would overrun the budget by %d.',
                    $bucket['wbs_id'] ?? '—',
                    $bucket['cost_code'] ?? '—',
                    $bucket['amount'],
                    $decision->overspendMinor,
                ));
            }
            if ($decision->verdict === BudgetVerdict::Warn) {
                $worst = BudgetVerdict::Warn;
            }
        }

        return DB::transaction(function () use ($po, $buckets, $worst) {
            $po->fill([
                'number' => $po->number ?? $this->numbering->next($po->company_id, 'purchase_order'),
                'status' => 'approved',
                'budget_status' => $worst->value,
            ]);
            $po->save();

            $fact = new PurchaseOrderFact(
                purchaseOrderId: $po->id,
                projectId: (string) $po->project_id,
                currency: $po->currency,
                buckets: array_values($buckets),
            );
            $event = new PurchaseOrderApproved($po->company_id, $fact);
            $this->outbox->publish($event, $event->dedupKey());

            return $po;
        });
    }

    /**
     * Sum the PO lines into (WBS × cost code) buckets.
     *
     * @return array<string, array{wbs_id: ?string, cost_code: ?string, amount: int}>
     */
    private function bucketize(PurchaseOrder $po): array
    {
        $buckets = [];
        foreach ($po->lines as $line) {
            $key = ($line->wbs_id ?? '∅').'|'.($line->cost_code ?? '∅');
            if (! isset($buckets[$key])) {
                $buckets[$key] = ['wbs_id' => $line->wbs_id, 'cost_code' => $line->cost_code, 'amount' => 0];
            }
            $buckets[$key]['amount'] += (int) $line->amount_minor;
        }

        return $buckets;
    }

    private function budgetFor(string $projectId, ?string $wbsId, ?string $costCode): ?int
    {
        $budget = BudgetLine::query()
            ->where('project_id', $projectId)
            ->where('wbs_id', $wbsId)
            ->where('cost_code', $costCode)
            ->value('budget_minor');

        return $budget === null ? null : (int) $budget;
    }
}
