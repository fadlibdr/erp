<?php

declare(strict_types=1);

namespace Modules\Receivables\Events;

use Modules\Platform\Domain\DomainEvent;

/**
 * Outbox envelope for a customer cash receipt. Receivables publishes it; Finance
 * posts Dr Bank / Cr Accounts Receivable.
 */
final class ReceiptReceived implements DomainEvent
{
    public function __construct(
        private readonly string $companyId,
        private readonly string $receiptId,
        private readonly ?string $projectId,
        private readonly string $currency,
        private readonly int $amountMinor,
    ) {}

    public function type(): string
    {
        return 'receivables.receipt_received';
    }

    /** @return array<string, mixed> */
    public function payload(): array
    {
        return [
            'receipt_id' => $this->receiptId,
            'project_id' => $this->projectId,
            'currency' => $this->currency,
            'amount' => $this->amountMinor,
        ];
    }

    public function companyId(): string
    {
        return $this->companyId;
    }

    public function dedupKey(): string
    {
        return 'ar_receipt:'.$this->receiptId;
    }
}
