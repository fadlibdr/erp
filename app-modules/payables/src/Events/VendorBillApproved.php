<?php

declare(strict_types=1);

namespace Modules\Payables\Events;

use Modules\Payables\Domain\VendorBillFact;
use Modules\Platform\Domain\DomainEvent;

/**
 * The outbox envelope for an approved subcontractor bill. Payables publishes this;
 * the Finance posting engine consumes it. Payables knows nothing about accounts.
 */
final class VendorBillApproved implements DomainEvent
{
    public function __construct(
        private readonly string $companyId,
        private readonly VendorBillFact $fact,
    ) {}

    public function type(): string
    {
        return VendorBillFact::TYPE;
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
        return 'vendor_bill:'.$this->fact->billId;
    }
}
