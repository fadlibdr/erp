<?php

declare(strict_types=1);

namespace Modules\Platform\Domain;

/**
 * A fact a module publishes about something that happened. Events are written to
 * the outbox in the same database transaction as the state change that produced
 * them, then relayed to handlers (e.g. the Finance posting engine) afterwards —
 * so a fact is never lost and never fires for a change that rolled back.
 */
interface DomainEvent
{
    /** Stable machine name, e.g. "billing.progress_invoice_issued". */
    public function type(): string;

    /** JSON-serialisable payload persisted verbatim in the outbox row. */
    public function payload(): array;

    /** The company the event belongs to (multi-tenant / KSO scoping). */
    public function companyId(): string;
}
