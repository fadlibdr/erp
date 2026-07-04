<?php

declare(strict_types=1);

namespace Modules\Payables\Events;

use Modules\Payables\Domain\MaterialBillFact;
use Modules\Platform\Domain\DomainEvent;

/**
 * Outbox envelope for an approved material bill. Payables publishes it; the Finance
 * posting engine consumes it and clears GR/IR against accounts payable.
 */
final class MaterialBillApproved implements DomainEvent
{
    public function __construct(
        private readonly string $companyId,
        private readonly MaterialBillFact $fact,
    ) {}

    public function type(): string
    {
        return MaterialBillFact::TYPE;
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
        return 'material_bill:'.$this->fact->billId;
    }
}
