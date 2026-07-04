<?php

declare(strict_types=1);

namespace Modules\Finance\Services;

use Illuminate\Support\Facades\DB;
use Modules\Platform\Models\OutboxEvent;
use Modules\Platform\Support\Outbox;
use Throwable;

/**
 * Drains the outbox into the posting engine.
 *
 * For each unprocessed event whose fact type the engine handles, it posts the
 * journal and stamps the event processed — the whole thing in one transaction so
 * a failure leaves the event unprocessed for a later retry rather than posting a
 * journal it can't acknowledge. Events the engine doesn't handle (non-financial
 * facts) are marked processed and skipped. Run continuously by a queue worker
 * (Horizon) or on a scheduler tick.
 */
final class OutboxRelay
{
    public function __construct(
        private readonly Outbox $outbox,
        private readonly PostingRuleEngine $engine,
    ) {
    }

    /** @return int number of events processed */
    public function drain(int $batch = 100): int
    {
        $events = $this->outbox->claim($batch);
        $processed = 0;

        foreach ($events as $event) {
            try {
                DB::transaction(function () use ($event) {
                    if ($this->engine->handles($event->type)) {
                        $this->engine->post(
                            companyId: $event->company_id,
                            factType: $event->type,
                            payload: $event->payload,
                            date: now()->format('Y-m-d'),
                        );
                    }
                    $this->markProcessed($event);
                });
                $processed++;
            } catch (Throwable $e) {
                $event->update([
                    'attempts' => $event->attempts + 1,
                    'last_error' => $e->getMessage(),
                    'available_at' => now()->addSeconds(min(3600, 30 * ($event->attempts + 1))),
                ]);
            }
        }

        return $processed;
    }

    private function markProcessed(OutboxEvent $event): void
    {
        $event->update(['processed_at' => now()]);
    }
}
