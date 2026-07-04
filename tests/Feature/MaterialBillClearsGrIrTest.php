<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Modules\Finance\Models\Journal;
use Modules\Finance\Models\JournalLine;
use Modules\Finance\Services\OutboxRelay;
use Modules\Payables\Actions\ApproveMaterialBill;
use Modules\Payables\Models\VendorBill;
use Modules\Platform\Models\NumberingSeries;
use Modules\Procurement\Actions\ApprovePurchaseOrder;
use Modules\Procurement\Actions\ReceiveGoods;

uses(RefreshDatabase::class);

/**
 * The commitment loop closed, end to end: PO → commitment → goods receipt (raises
 * the GR/IR accrual) → PO-linked material bill (clears it under three-way match).
 * The proof is that the GR/IR account nets to zero once the received PO is billed.
 */
it('clears GR/IR to zero when a PO-linked material bill is approved', function () {
    [$company, $project, $wbs, $vendor] = karyaCostControlFixture();
    NumberingSeries::create([
        'company_id' => $company->id, 'key' => 'vendor_bill',
        'format' => 'BILL-{YYYY}-{#####}', 'next' => 1, 'period_scope' => 'year',
    ]);

    // PO for 1 unit @ Rp 800.000 → approve → commitment.
    $po = karyaPo($company, $project, $wbs, $vendor, 800_000);
    app(ApprovePurchaseOrder::class)->execute($po);
    app(OutboxRelay::class)->drain();

    // Receive the full quantity (1 unit) → GR/IR accrual credited 800.000.
    $po->refresh();
    $line = $po->lines()->firstOrFail();
    app(ReceiveGoods::class)->execute($po, [[
        'purchase_order_line_id' => $line->id,
        'item_id' => (string) Str::uuid(),
        'warehouse_id' => (string) Str::uuid(),
        'qty_milli' => 1_000,          // 1 unit, matching the PO
        'amount_minor' => 800_000,
    ]], '2026-07-05');
    app(OutboxRelay::class)->drain();

    // The material bill for those goods (vendor is PKP → 11% input VAT, no PPh).
    $bill = VendorBill::create([
        'company_id' => $company->id, 'vendor_id' => $vendor->id, 'project_id' => $project->id,
        'purchase_order_id' => $po->id, 'bill_date' => '2026-07-06', 'status' => 'draft',
        'service_class' => 'construction_work', 'retention_percent' => 0, 'cost_code' => 'MAT',
        'work_value_minor' => 800_000, 'currency' => 'IDR',
    ]);

    app(ApproveMaterialBill::class)->execute($bill);

    $bill->refresh();
    expect($bill->status)->toBe('approved')
        ->and($bill->match_status)->toBe('matched')
        ->and($bill->ppn_input_minor)->toBe(88_000)
        ->and($bill->pph_withheld_minor)->toBe(0)
        ->and($bill->net_payable_minor)->toBe(888_000);

    expect(app(OutboxRelay::class)->drain())->toBe(1);

    $journal = Journal::with('lines')->where('fact_type', 'payables.material_bill_approved')->firstOrFail();
    expect($journal->lines->sum('debit_minor'))->toBe($journal->lines->sum('credit_minor'))
        ->and($journal->lines->firstWhere('account_code', '2109')->debit_minor)->toBe(800_000)   // clears GR/IR
        ->and($journal->lines->firstWhere('account_code', '2101')->credit_minor)->toBe(888_000);  // AP net

    // GR/IR (2109) nets to zero: credited 800.000 at receipt, debited 800.000 at bill.
    $grIrBalance = JournalLine::where('company_id', $company->id)->where('account_code', '2109')
        ->get()->sum(fn (JournalLine $l): int => $l->credit_minor - $l->debit_minor);
    expect($grIrBalance)->toBe(0);
});

it('blocks a material bill that fails three-way match', function () {
    [$company, $project, $wbs, $vendor] = karyaCostControlFixture();
    NumberingSeries::create([
        'company_id' => $company->id, 'key' => 'vendor_bill',
        'format' => 'BILL-{YYYY}-{#####}', 'next' => 1, 'period_scope' => 'year',
    ]);

    $po = karyaPo($company, $project, $wbs, $vendor, 800_000);
    app(ApprovePurchaseOrder::class)->execute($po);
    app(OutboxRelay::class)->drain();

    $po->refresh();
    $line = $po->lines()->firstOrFail();
    app(ReceiveGoods::class)->execute($po, [[
        'purchase_order_line_id' => $line->id, 'item_id' => (string) Str::uuid(),
        'warehouse_id' => (string) Str::uuid(), 'qty_milli' => 1_000, 'amount_minor' => 800_000,
    ]], '2026-07-05');
    app(OutboxRelay::class)->drain();

    // Bill for Rp 900.000 — 12.5% over the PO price, past the 1% tolerance.
    $bill = VendorBill::create([
        'company_id' => $company->id, 'vendor_id' => $vendor->id, 'project_id' => $project->id,
        'purchase_order_id' => $po->id, 'bill_date' => '2026-07-06', 'status' => 'draft',
        'service_class' => 'construction_work', 'retention_percent' => 0, 'cost_code' => 'MAT',
        'work_value_minor' => 900_000, 'currency' => 'IDR',
    ]);

    expect(fn () => app(ApproveMaterialBill::class)->execute($bill))->toThrow(RuntimeException::class);
    expect($bill->refresh()->status)->toBe('draft');
});
