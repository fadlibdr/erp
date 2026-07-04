<?php

declare(strict_types=1);

namespace Modules\Procurement\Events;

use Modules\Platform\Domain\DomainEvent;
use Modules\Procurement\Domain\PurchaseOrderFact;

/**
 * Outbox envelope for an approved PO. Procurement publishes it; the Finance
 * commitment projector consumes it. Procurement knows nothing about the ledger.
 */
final class PurchaseOrderApproved implements DomainEvent
{
    public function __construct(
        private readonly string $companyId,
        private readonly PurchaseOrderFact $fact,
    ) {}

    public function type(): string
    {
        return PurchaseOrderFact::TYPE;
    }

    /** @return array<string, mixed> */
    public function payload(): array
    {
        return $this->fact->toPayload();
    }

    public function companyId(): string
    {
        return $this->companyId;
    }

    public function dedupKey(): string
    {
        return 'po_approved:'.$this->fact->purchaseOrderId;
    }
}
