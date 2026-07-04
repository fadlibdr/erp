<?php

declare(strict_types=1);

namespace Modules\Payables\Events;

use Modules\Payables\Domain\PaymentMadeFact;
use Modules\Platform\Domain\DomainEvent;

/**
 * Outbox envelope for a vendor payment batch. Payables publishes it; Finance posts
 * the Dr AP / Cr Bank settlement.
 */
final class PaymentMade implements DomainEvent
{
    public function __construct(
        private readonly string $companyId,
        private readonly PaymentMadeFact $fact,
    ) {}

    public function type(): string
    {
        return PaymentMadeFact::TYPE;
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
        return 'payment_made:'.$this->fact->batchId;
    }
}
