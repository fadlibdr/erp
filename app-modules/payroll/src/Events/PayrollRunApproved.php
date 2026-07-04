<?php

declare(strict_types=1);

namespace Modules\Payroll\Events;

use Modules\Payroll\Domain\PayrollRunFact;
use Modules\Platform\Domain\DomainEvent;

/**
 * Outbox envelope for an approved payroll run. Payroll publishes it; Finance posts
 * the labor-cost journal. Payroll knows nothing about accounts.
 */
final class PayrollRunApproved implements DomainEvent
{
    public function __construct(
        private readonly string $companyId,
        private readonly PayrollRunFact $fact,
    ) {}

    public function type(): string
    {
        return PayrollRunFact::TYPE;
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
        return 'payroll_run:'.$this->fact->runId;
    }
}
