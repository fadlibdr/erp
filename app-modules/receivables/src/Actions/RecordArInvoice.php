<?php

declare(strict_types=1);

namespace Modules\Receivables\Actions;

use Modules\Platform\Models\OutboxEvent;
use Modules\Platform\Support\OutboxConsumer;
use Modules\Receivables\Models\ArInvoice;
use Modules\Receivables\Models\ArRetention;

/**
 * Builds the AR sub-ledger from a termin fact. It consumes Billing's
 * progress_invoice_issued fact (named as a local string, so Receivables depends only
 * on Platform) and records the customer invoice plus its held retention — the rows a
 * cash receipt and a retention release later settle against.
 *
 * Idempotent on the source claim: a fact relayed twice records the invoice once.
 */
final class RecordArInvoice implements OutboxConsumer
{
    private const PROGRESS_INVOICE_ISSUED = 'billing.progress_invoice_issued';

    public function handles(string $factType): bool
    {
        return $factType === self::PROGRESS_INVOICE_ISSUED;
    }

    public function consume(OutboxEvent $event): void
    {
        $p = $event->payload;

        if (ArInvoice::query()->where('source_claim_id', $p['claim_id'])->exists()) {
            return;
        }

        ArInvoice::create([
            'company_id' => $event->company_id,
            'project_id' => $p['project_id'] ?? null,
            'source_claim_id' => $p['claim_id'],
            'invoice_date' => now()->format('Y-m-d'),
            'status' => 'open',
            'gross_minor' => (int) $p['work_value'] + (int) $p['ppn_output'],
            'net_minor' => (int) $p['net_receivable'],
            'currency' => $p['currency'],
        ]);

        if ((int) $p['retention'] > 0) {
            ArRetention::create([
                'company_id' => $event->company_id,
                'project_id' => $p['project_id'] ?? null,
                'source_claim_id' => $p['claim_id'],
                'amount_minor' => (int) $p['retention'],
                'currency' => $p['currency'],
                'status' => 'held',
            ]);
        }
    }
}
