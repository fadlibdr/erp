<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Billing\Actions\IssueTerminInvoice;
use Modules\Billing\Models\ProgressClaim;
use Modules\Finance\Database\Seeders\ConstructionLedgerSeeder;
use Modules\Finance\Services\OutboxRelay;
use Modules\Platform\Models\Company;
use Modules\Platform\Models\NumberingSeries;
use Modules\Projects\Models\Project;
use Modules\Tax\Domain\EfakturSubmissionStatus;
use Modules\Tax\Models\EfakturSubmission;

uses(RefreshDatabase::class);

/**
 * Issuing a termin invoice both posts the AR journal (Pass 1) and, through the same
 * outbox drain, queues an e-Faktur for Coretax (Pass 3). Queuing is idempotent on
 * the source claim.
 */
it('queues an e-Faktur when a termin invoice is issued', function () {
    $company = Company::create([
        'code' => 'KON', 'name' => 'PT Kontraktor Uji', 'npwp' => '01.234.567.8-901.000',
        'is_pkp' => true, 'base_currency' => 'IDR', 'sbu_class' => 'medium_large_spec',
    ]);
    foreach (['journal', 'progress_claim'] as $key) {
        NumberingSeries::create([
            'company_id' => $company->id, 'key' => $key,
            'format' => strtoupper(substr($key, 0, 3)).'-{YYYY}-{#####}', 'next' => 1, 'period_scope' => 'year',
        ]);
    }
    (new ConstructionLedgerSeeder)->seedForCompany($company->id);

    $project = Project::create([
        'company_id' => $company->id, 'code' => 'PRJ-001', 'name' => 'Pabrik Uji',
        'contract_number' => 'C-001', 'contract_date' => '2026-03-01',
        'service_class' => 'integrated_work', 'contract_value_minor' => 10_000_000_000,
        'currency' => 'IDR', 'retention_percent' => 5, 'uang_muka_percent' => 20, 'status' => 'active',
    ]);
    $claim = ProgressClaim::create([
        'company_id' => $company->id, 'project_id' => $project->id, 'sequence' => 1,
        'claim_date' => '2026-07-04', 'status' => 'bapp', 'work_value_minor' => 1_000_000, 'currency' => 'IDR',
    ]);

    app(IssueTerminInvoice::class)->execute($claim);
    app(OutboxRelay::class)->drain();

    $submission = EfakturSubmission::where('source_id', $claim->id)->firstOrFail();
    expect($submission->status)->toBe(EfakturSubmissionStatus::Queued)
        ->and($submission->source_type)->toBe('progress_claim')
        ->and($submission->dedup_key)->toBe('efaktur:'.$claim->id)
        ->and($submission->request_payload['dpp_minor'])->toBe(1_000_000)
        ->and($submission->request_payload['ppn_minor'])->toBe(110_000);

    // Draining again enqueues nothing new (dedup + processed stamp).
    app(OutboxRelay::class)->drain();
    expect(EfakturSubmission::where('source_id', $claim->id)->count())->toBe(1);
});
