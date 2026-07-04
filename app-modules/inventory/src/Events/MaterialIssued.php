<?php

declare(strict_types=1);

namespace Modules\Inventory\Events;

use Modules\Inventory\Domain\MaterialIssuedFact;
use Modules\Platform\Domain\DomainEvent;

/**
 * Outbox envelope for a material issue. Inventory publishes it; the Finance posting
 * engine turns it into the project material cost entry.
 */
final class MaterialIssued implements DomainEvent
{
    public function __construct(
        private readonly string $companyId,
        private readonly MaterialIssuedFact $fact,
    ) {}

    public function type(): string
    {
        return MaterialIssuedFact::TYPE;
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
        return 'material_issued:'.$this->fact->issueId;
    }
}
