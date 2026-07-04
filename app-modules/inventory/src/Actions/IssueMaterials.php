<?php

declare(strict_types=1);

namespace Modules\Inventory\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Inventory\Domain\MaterialIssuedFact;
use Modules\Inventory\Events\MaterialIssued;
use Modules\Inventory\Models\MaterialIssue;
use Modules\Inventory\Models\MaterialIssueLine;
use Modules\Inventory\Services\StockLedger;
use Modules\Platform\Actions\Action;
use Modules\Platform\Domain\Currency;
use Modules\Platform\Support\Outbox;
use RuntimeException;

/**
 * Issues stored materials from a warehouse to a WBS/cost code — the moment inventory
 * becomes an actual project cost. Each line is valued at moving average (the shared
 * StockLedger, same engine the GRN receipt used), a negative stock movement is
 * appended, and the summed value is published for Finance to post Dr project cost /
 * Cr inventory. The Action never writes a journal.
 *
 * @phpstan-type IssueLine array{item_id: string, qty_milli: int}
 */
final class IssueMaterials extends Action
{
    public function __construct(
        private readonly StockLedger $ledger,
        private readonly Outbox $outbox,
    ) {}

    /**
     * @param  list<IssueLine>  $lines
     */
    public function execute(
        string $companyId,
        string $projectId,
        ?string $wbsId,
        string $costCode,
        string $warehouseId,
        string $issueDate,
        array $lines,
        string $currencyCode = 'IDR',
    ): MaterialIssue {
        if ($lines === []) {
            throw new RuntimeException('A material issue needs at least one line.');
        }
        $currency = Currency::from($currencyCode);

        return DB::transaction(function () use ($companyId, $projectId, $wbsId, $costCode, $warehouseId, $issueDate, $lines, $currency): MaterialIssue {
            $issue = MaterialIssue::create([
                'company_id' => $companyId,
                'project_id' => $projectId,
                'wbs_id' => $wbsId,
                'cost_code' => $costCode,
                'warehouse_id' => $warehouseId,
                'issue_date' => $issueDate,
                'total_minor' => 0,
            ]);

            $total = 0;
            foreach ($lines as $line) {
                $stockIssue = $this->ledger->recordIssue(
                    companyId: $companyId,
                    itemId: $line['item_id'],
                    warehouseId: $warehouseId,
                    movementType: 'issue',
                    sourceId: $issue->id,
                    qtyMilli: (int) $line['qty_milli'],
                    currency: $currency,
                );
                $value = $stockIssue->issuedValue->minor;
                $total += $value;

                MaterialIssueLine::create([
                    'material_issue_id' => $issue->id,
                    'item_id' => $line['item_id'],
                    'warehouse_id' => $warehouseId,
                    'wbs_id' => $wbsId,
                    'cost_code' => $costCode,
                    'quantity' => ((int) $line['qty_milli']) / 1000,
                    'amount_minor' => $value,
                ]);
            }

            $issue->update(['total_minor' => $total]);

            $fact = new MaterialIssuedFact($issue->id, $projectId, $wbsId, $costCode, $currency->value, $total);
            $event = new MaterialIssued($companyId, $fact);
            $this->outbox->publish($event, $event->dedupKey());

            return $issue;
        });
    }
}
