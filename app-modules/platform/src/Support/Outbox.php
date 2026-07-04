<?php

declare(strict_types=1);

namespace Modules\Platform\Support;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Platform\Domain\DomainEvent;
use Modules\Platform\Models\OutboxEvent;

/**
 * Writes domain events into the transactional outbox.
 *
 * Call this *inside* the same DB transaction as the state change it describes.
 * The event and the change then commit together or not at all — the outbox
 * guarantee. A separate relay (RelayOutbox job) later reads unprocessed rows and
 * hands them to registered handlers such as the Finance posting engine.
 */
final class Outbox
{
    public function publish(DomainEvent $event, ?string $dedupKey = null): OutboxEvent
    {
        return OutboxEvent::create([
            'company_id' => $event->companyId(),
            'type' => $event->type(),
            'payload' => $event->payload(),
            'dedup_key' => $dedupKey,
            // Available from the start of this second. The column is timestamp(0),
            // so relying on the DB default (CURRENT_TIMESTAMP) would *round* the
            // sub-second part and could push availability into the next second —
            // making a just-published event briefly unclaimable. Flooring to the
            // second here keeps `available_at <= now()` true for an immediate relay.
            'available_at' => now()->startOfSecond(),
        ]);
    }

    /**
     * Claim a batch of unprocessed events for relaying, skipping rows another
     * worker already holds (SKIP LOCKED). Returns them still unprocessed; the
     * caller marks each processed once its handlers succeed.
     *
     * @return Collection<int, OutboxEvent>
     */
    public function claim(int $limit = 100)
    {
        return DB::transaction(function () use ($limit) {
            $events = OutboxEvent::query()
                ->whereNull('processed_at')
                ->where('available_at', '<=', now())
                ->orderBy('available_at')
                ->limit($limit)
                ->lockForUpdate()
                ->get();

            return $events;
        });
    }
}
