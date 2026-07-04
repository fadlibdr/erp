<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Finance\Models\Commitment;
use Modules\Finance\Services\OutboxRelay;
use Modules\Procurement\Actions\ApprovePurchaseOrder;

uses(RefreshDatabase::class);

/**
 * The head of the cost-control loop: a PO is gated against the control budget, and
 * on approval raises a commitment (via the outbox → Finance projection). A second
 * PO that would breach the same budget bucket is refused. The shared fixture lives
 * in tests/Pest.php (karyaCostControlFixture / karyaPo).
 */
it('approves a PO within budget and raises a commitment through the outbox', function () {
    [$company, $project, $wbs, $vendor] = karyaCostControlFixture();

    $po = karyaPo($company, $project, $wbs, $vendor, 800_000);
    app(ApprovePurchaseOrder::class)->execute($po);

    expect($po->refresh()->status)->toBe('approved')
        ->and($po->budget_status)->toBe('ok');

    // Relaying the fact raises the commitment (a projection, not a journal).
    expect(app(OutboxRelay::class)->drain())->toBe(1);

    $commitment = Commitment::where('source_id', $po->id)->firstOrFail();
    expect($commitment->committed_minor)->toBe(800_000)
        ->and($commitment->consumed_minor)->toBe(0)
        ->and($commitment->cost_code)->toBe('MAT');
});

it('refuses a PO that would breach the control budget', function () {
    [$company, $project, $wbs, $vendor] = karyaCostControlFixture();

    app(ApprovePurchaseOrder::class)->execute(karyaPo($company, $project, $wbs, $vendor, 800_000));
    app(OutboxRelay::class)->drain(); // commitment now 800k of the 1.000.000 budget

    // A second PO for 300k would take committed to 1.1M > 1M — blocked.
    $po2 = karyaPo($company, $project, $wbs, $vendor, 300_000);
    expect(fn () => app(ApprovePurchaseOrder::class)->execute($po2))
        ->toThrow(RuntimeException::class);

    expect($po2->refresh()->status)->toBe('draft');
});
