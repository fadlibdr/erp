<?php

declare(strict_types=1);

namespace Modules\Finance\Services;

use Illuminate\Support\Facades\DB;
use Modules\Platform\Models\OutboxEvent;
use Modules\Platform\Support\Outbox;
use Modules\Platform\Support\OutboxConsumer;
use Throwable;

/**
 * Drains the outbox into the posting engine and any registered projections.
 *
 * For each unprocessed event the relay, in one transaction: posts a journal if the
 * engine handles the fact, runs every OutboxConsumer that handles it (the
 * commitment projector, the stock ledger, …), then stamps the event processed. The
 * single transaction is the point — a projection, its journal, and the processed
 * mark commit together or not at all, so a failure leaves the event for a clean
 * retry rather than half-applying it. Events nobody handles (non-financial facts)
 * are simply marked processed. Run by a queue worker (Horizon) or a scheduler tick.
 *
 * Consumers are injected as the Platform interface (resolved from the container tag
 * `outbox.consumers`), so the relay never statically depends on the modules that
 * own them — the dependency arrow stays pointing at Finance, never away from it.
 */
final class OutboxRelay
{
    /** @var list<OutboxConsumer> */
    private readonly array $consumers;

    /**
     * @param  iterable<OutboxConsumer>  $consumers
     */
    public function __construct(
        private readonly Outbox $outbox,
        private readonly PostingRuleEngine $engine,
        iterable $consumers = [],
    ) {
        $this->consumers = is_array($consumers) ? array_values($consumers) : iterator_to_array($consumers, false);
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
                    foreach ($this->consumers as $consumer) {
                        if ($consumer->handles($event->type)) {
                            $consumer->consume($event);
                        }
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
