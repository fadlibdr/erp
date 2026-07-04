<?php

declare(strict_types=1);

namespace Modules\Platform\Support;

use Modules\Platform\Models\OutboxEvent;

/**
 * A handler the outbox relay hands a claimed event to.
 *
 * The Finance posting engine is one such consumer (facts → journals), but not the
 * only one: a fact can also update a projection (the commitment ledger) or a
 * downstream module's read model (the stock ledger) without producing a journal.
 * Each owning module registers its own consumer via the container tag
 * `outbox.consumers`; the relay runs every consumer that `handles()` the fact,
 * all inside the one transaction that marks the event processed — so a projection
 * and its triggering fact commit together or not at all, exactly like the outbox
 * guarantee on the publishing side.
 *
 * A consumer depends only on Platform (this interface + OutboxEvent), never on the
 * relay, so the dependency law is never inverted: Finance/Inventory implement it;
 * the relay just iterates the interface.
 */
interface OutboxConsumer
{
    public function handles(string $factType): bool;

    public function consume(OutboxEvent $event): void;
}
