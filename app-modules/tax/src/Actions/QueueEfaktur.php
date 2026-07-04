<?php

declare(strict_types=1);

namespace Modules\Tax\Actions;

use Modules\Platform\Models\OutboxEvent;
use Modules\Platform\Support\OutboxConsumer;
use Modules\Tax\Domain\EfakturSubmissionStatus;
use Modules\Tax\Models\EfakturSubmission;

/**
 * Queues an e-Faktur the moment a termin invoice is issued. It consumes Billing's
 * progress_invoice_issued fact (named as a local string, so Tax depends only on
 * Platform) and creates a Queued submission the Coretax channel later transmits.
 *
 * Idempotent on the source claim via the unique dedup key: a fact relayed twice
 * enqueues the invoice once. An invoice with no output VAT (below-threshold or a
 * non-VAT transaction) needs no tax invoice and is skipped.
 */
final class QueueEfaktur implements OutboxConsumer
{
    private const PROGRESS_INVOICE_ISSUED = 'billing.progress_invoice_issued';

    public function handles(string $factType): bool
    {
        return $factType === self::PROGRESS_INVOICE_ISSUED;
    }

    public function consume(OutboxEvent $event): void
    {
        $payload = $event->payload;

        if ((int) ($payload['ppn_output'] ?? 0) <= 0) {
            return; // nothing to register with the tax authority
        }

        $dedupKey = 'efaktur:'.$payload['claim_id'];

        if (EfakturSubmission::query()->where('dedup_key', $dedupKey)->exists()) {
            return;
        }

        EfakturSubmission::create([
            'company_id' => $event->company_id,
            'source_type' => 'progress_claim',
            'source_id' => $payload['claim_id'],
            'status' => EfakturSubmissionStatus::Queued,
            'dedup_key' => $dedupKey,
            'request_payload' => [
                'currency' => $payload['currency'],
                'dpp_minor' => (int) $payload['work_value'],
                'ppn_minor' => (int) $payload['ppn_output'],
                'reference' => $payload['claim_id'],
            ],
            'attempts' => 0,
        ]);
    }
}
