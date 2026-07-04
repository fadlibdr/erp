<?php

declare(strict_types=1);

namespace Modules\Finance\Actions;

use Modules\Finance\Models\FiscalPeriod;
use Modules\Platform\Actions\Action;
use RuntimeException;

/**
 * Reopens a closed period so a correction can post into it. Deliberately minimal
 * and auditable — reopening a closed month is a controlled act (owen-it auditing
 * captures who/when); it does not reverse the recognition already booked, it only
 * lifts the posting lock. A subsequent re-close re-recognises against the new state.
 */
final class ReopenFiscalPeriod extends Action
{
    public function execute(string $companyId, string $periodLabel): FiscalPeriod
    {
        /** @var FiscalPeriod|null $period */
        $period = FiscalPeriod::query()
            ->where('company_id', $companyId)
            ->where('label', $periodLabel)
            ->first();

        if ($period === null) {
            throw new RuntimeException("Fiscal period {$periodLabel} does not exist for this company.");
        }
        if (! $period->isClosed()) {
            throw new RuntimeException("Fiscal period {$periodLabel} is already open.");
        }

        $period->update(['status' => 'open', 'closed_at' => null]);

        return $period;
    }
}
