<?php

declare(strict_types=1);

namespace Modules\Billing\Events;

use Modules\Billing\Domain\ProgressInvoiceFact;
use Modules\Platform\Domain\DomainEvent;

/**
 * The outbox envelope for a termin invoice. Billing publishes this; the Finance
 * posting engine consumes it. Billing knows nothing about accounts.
 */
final class ProgressInvoiceIssued implements DomainEvent
{
    public function __construct(
        private readonly string $companyId,
        private readonly ProgressInvoiceFact $fact,
    ) {
    }

    public function type(): string
    {
        return ProgressInvoiceFact::TYPE;
    }

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
        return 'progress_invoice:' . $this->fact->claimId;
    }
}
