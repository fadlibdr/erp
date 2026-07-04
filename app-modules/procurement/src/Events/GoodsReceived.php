<?php

declare(strict_types=1);

namespace Modules\Procurement\Events;

use Modules\Platform\Domain\DomainEvent;
use Modules\Procurement\Domain\GoodsReceivedFact;

/**
 * Outbox envelope for a goods receipt. Fans out to the Finance posting engine
 * (GR/IR accrual), the Finance commitment projector (consume), and the Inventory
 * stock ledger — all downstream of this one published fact.
 */
final class GoodsReceived implements DomainEvent
{
    public function __construct(
        private readonly string $companyId,
        private readonly GoodsReceivedFact $fact,
    ) {}

    public function type(): string
    {
        return GoodsReceivedFact::TYPE;
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
        return 'goods_received:'.$this->fact->grnId;
    }
}
