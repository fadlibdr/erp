<?php

declare(strict_types=1);

namespace Modules\Receivables\Events;

use Modules\Platform\Domain\DomainEvent;

/**
 * Outbox envelope for a retention release at final hand-over. Receivables publishes
 * it; Finance posts Dr Bank / Cr Retention Receivable.
 */
final class RetentionReleased implements DomainEvent
{
    public function __construct(
        private readonly string $companyId,
        private readonly string $retentionId,
        private readonly ?string $projectId,
        private readonly string $currency,
        private readonly int $amountMinor,
    ) {}

    public function type(): string
    {
        return 'receivables.retention_released';
    }

    /** @return array<string, mixed> */
    public function payload(): array
    {
        return [
            'retention_id' => $this->retentionId,
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
        return 'retention_release:'.$this->retentionId;
    }
}
