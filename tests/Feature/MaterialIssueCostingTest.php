<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Modules\Finance\Models\Journal;
use Modules\Finance\Models\JournalLine;
use Modules\Finance\Services\OutboxRelay;
use Modules\Inventory\Actions\IssueMaterials;
use Modules\Inventory\Models\StockLedgerEntry;
use Modules\Procurement\Actions\ApprovePurchaseOrder;
use Modules\Procurement\Actions\ReceiveGoods;
use Modules\Procurement\Models\PurchaseOrder;

uses(RefreshDatabase::class);

/**
 * Material issue → project cost: goods received into a warehouse become an actual
 * project cost when issued to a WBS, valued at moving average. The GL expense this
 * books is exactly the cost-to-date the month-end close and budget actuals read.
 */
it('issues material at moving average and posts Dr project cost / Cr inventory', function () {
    [$company, $project, $wbs, $vendor] = karyaCostControlFixture();

    // Receive 100 units @ Rp 8.000 = Rp 800.000 into a warehouse.
    $po = karyaPo($company, $project, $wbs, $vendor, 800_000);
    app(ApprovePurchaseOrder::class)->execute($po);
    app(OutboxRelay::class)->drain();

    $po->refresh();
    $line = $po->lines()->firstOrFail();
    $itemId = (string) Str::uuid();
    $warehouseId = (string) Str::uuid();
    app(ReceiveGoods::class)->execute($po, [[
        'purchase_order_line_id' => $line->id, 'item_id' => $itemId, 'warehouse_id' => $warehouseId,
        'qty_milli' => 100_000, 'amount_minor' => 800_000,
    ]], '2026-07-05');
    app(OutboxRelay::class)->drain();

    // Issue 50 units → 50 × Rp 8.000 = Rp 400.000 at the moving average.
    $issue = app(IssueMaterials::class)->execute(
        companyId: $company->id,
        projectId: $project->id,
        wbsId: $wbs->id,
        costCode: 'MAT',
        warehouseId: $warehouseId,
        issueDate: '2026-07-06',
        lines: [['item_id' => $itemId, 'qty_milli' => 50_000]],
    );

    expect((int) $issue->total_minor)->toBe(400_000);

    // The stock ledger recorded a negative 'issue' movement; 50 units / Rp 400.000 remain.
    $issueRow = StockLedgerEntry::where('item_id', $itemId)->where('movement_type', 'issue')->firstOrFail();
    expect((float) $issueRow->qty_delta)->toBe(-50.0)
        ->and($issueRow->value_delta_minor)->toBe(-400_000)
        ->and($issueRow->balance_value_minor)->toBe(400_000);

    // Relaying posts the balanced project-cost entry.
    expect(app(OutboxRelay::class)->drain())->toBe(1);
    $journal = Journal::with('lines')->where('fact_type', 'inventory.material_issued')->firstOrFail();
    expect($journal->lines->sum('debit_minor'))->toBe($journal->lines->sum('credit_minor'))
        ->and($journal->lines->firstWhere('account_code', '5102')->debit_minor)->toBe(400_000)
        ->and($journal->lines->firstWhere('account_code', '1301')->credit_minor)->toBe(400_000);

    // Project cost-to-date (GL expense for the project) now reflects the issue.
    $projectExpense = JournalLine::where('company_id', $company->id)->where('project_id', $project->id)
        ->where('account_code', '5102')->sum('debit_minor');
    expect((int) $projectExpense)->toBe(400_000);
});
