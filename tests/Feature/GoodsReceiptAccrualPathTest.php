<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Modules\Finance\Models\Commitment;
use Modules\Finance\Models\Journal;
use Modules\Finance\Services\OutboxRelay;
use Modules\Inventory\Models\StockLedgerEntry;
use Modules\Procurement\Actions\ApprovePurchaseOrder;
use Modules\Procurement\Actions\ReceiveGoods;
use Modules\Procurement\Models\PurchaseOrder;

uses(RefreshDatabase::class);

/**
 * The tail of the cost-control loop: receiving goods against an approved PO consumes
 * the commitment, books a balanced GR/IR accrual in the GL, and moves stock into a
 * warehouse at moving-average cost — all from the one goods-received fact.
 */
it('receives goods and fans out to accrual, commitment consumption and the stock ledger', function () {
    [$company, $project, $wbs, $vendor] = karyaCostControlFixture();

    $po = karyaPo($company, $project, $wbs, $vendor, 800_000);
    app(ApprovePurchaseOrder::class)->execute($po);
    app(OutboxRelay::class)->drain(); // commitment raised

    /** @var PurchaseOrder $po */
    $po->refresh();
    $line = $po->lines()->firstOrFail();
    $itemId = (string) Str::uuid();
    $warehouseId = (string) Str::uuid();

    app(ReceiveGoods::class)->execute($po, [[
        'purchase_order_line_id' => $line->id,
        'item_id' => $itemId,
        'warehouse_id' => $warehouseId,
        'qty_milli' => 100_000,       // 100 units
        'amount_minor' => 800_000,
    ]], '2026-07-05');

    expect($po->refresh()->status)->toBe('received');

    expect(app(OutboxRelay::class)->drain())->toBe(1);

    // 1) Balanced GR/IR accrual: Dr Inventory (1301) / Cr GR/IR (2109).
    $journal = Journal::with('lines')->where('fact_type', 'procurement.goods_received')->firstOrFail();
    expect($journal->lines->sum('debit_minor'))->toBe($journal->lines->sum('credit_minor'))
        ->and($journal->lines->sum('debit_minor'))->toBe(800_000);
    expect($journal->lines->firstWhere('account_code', '1301')->debit_minor)->toBe(800_000)
        ->and($journal->lines->firstWhere('account_code', '2109')->credit_minor)->toBe(800_000);

    // 2) Commitment consumed.
    expect(Commitment::where('source_id', $po->id)->firstOrFail()->consumed_minor)->toBe(800_000);

    // 3) Stock moved in at moving-average value.
    $entry = StockLedgerEntry::where('item_id', $itemId)->firstOrFail();
    expect($entry->movement_type)->toBe('grn')
        ->and($entry->balance_value_minor)->toBe(800_000)
        ->and((float) $entry->balance_qty)->toBe(100.0);
});
