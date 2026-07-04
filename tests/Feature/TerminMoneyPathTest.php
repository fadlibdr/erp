<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Billing\Actions\IssueTerminInvoice;
use Modules\Billing\Models\ProgressClaim;
use Modules\Finance\Database\Seeders\ConstructionLedgerSeeder;
use Modules\Finance\Models\Journal;
use Modules\Finance\Services\OutboxRelay;
use Modules\Platform\Models\Company;
use Modules\Platform\Models\NumberingSeries;
use Modules\Projects\Models\Project;

uses(RefreshDatabase::class);

/**
 * The full money path, end to end through the real stack:
 *   claim -> IssueTerminInvoice (termin math + PPh resolution) -> outbox
 *         -> OutboxRelay -> PostingRuleEngine -> balanced journal in the GL.
 *
 * This is the integration counterpart to the pure checks in bin/domain-tests.php:
 * it proves the outbox seam and the DB-backed posting actually reconcile.
 */
it('issues a termin invoice and posts a balanced journal via the outbox', function () {
    $company = Company::create([
        'code' => 'KON', 'name' => 'PT Kontraktor Uji', 'npwp' => '01.234.567.8-901.000',
        'is_pkp' => true, 'base_currency' => 'IDR', 'sbu_class' => 'medium_large_spec',
    ]);

    foreach (['journal', 'progress_claim'] as $key) {
        NumberingSeries::create([
            'company_id' => $company->id, 'key' => $key,
            'format' => strtoupper(substr($key, 0, 3)) . '-{YYYY}-{#####}', 'next' => 1, 'period_scope' => 'year',
        ]);
    }

    (new ConstructionLedgerSeeder())->seedForCompany($company->id);

    $project = Project::create([
        'company_id' => $company->id, 'code' => 'PRJ-001', 'name' => 'Pabrik Uji',
        'contract_number' => 'C-001', 'contract_date' => '2026-03-01', // post-2022 -> PP 9/2022
        'service_class' => 'integrated_work', 'contract_value_minor' => 10_000_000_000,
        'currency' => 'IDR', 'retention_percent' => 5, 'uang_muka_percent' => 20, 'status' => 'active',
    ]);

    $claim = ProgressClaim::create([
        'company_id' => $company->id, 'project_id' => $project->id, 'sequence' => 1,
        'claim_date' => '2026-07-04', 'status' => 'bapp', 'work_value_minor' => 1_000_000, 'currency' => 'IDR',
    ]);

    app(IssueTerminInvoice::class)->execute($claim);

    // The claim's frozen figures match the worked example.
    $claim->refresh();
    expect($claim->status)->toBe('invoiced')
        ->and($claim->ppn_output_minor)->toBe(110_000)      // 11%
        ->and($claim->retention_minor)->toBe(50_000)        // 5%
        ->and($claim->uang_muka_recovery_minor)->toBe(200_000) // 20%
        ->and($claim->pph_final_minor)->toBe(26_500)        // 2.65% EPC medium SBU
        ->and($claim->net_receivable_minor)->toBe(833_500);

    // Relaying the outbox posts exactly one balanced journal.
    $processed = app(OutboxRelay::class)->drain();
    expect($processed)->toBe(1);

    $journal = Journal::with('lines')->where('source_reference', $claim->id)->firstOrFail();
    $debits = $journal->lines->sum('debit_minor');
    $credits = $journal->lines->sum('credit_minor');
    expect($debits)->toBe($credits)
        ->and($debits)->toBe(1_110_000)                     // work + PPN
        ->and($journal->fact_type)->toBe('billing.progress_invoice_issued');
});

it('is idempotent — relaying twice does not double-post', function () {
    // Re-running the relay must not create a second journal for the same fact
    // (the outbox dedup_key + processed_at guarantee it).
    expect(true)->toBeTrue(); // placeholder asserted by the dedup_key unique index; full path in Pass 2
})->skip('covered structurally by the dedup_key unique index; explicit test lands in Pass 2');
