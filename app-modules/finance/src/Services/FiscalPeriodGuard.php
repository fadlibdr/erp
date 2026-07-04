<?php

declare(strict_types=1);

namespace Modules\Finance\Services;

use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Refuses to post into a closed fiscal period. Extracted behind an interface-like
 * seam so tests can post freely and month-end close can lock a period hard.
 */
final class FiscalPeriodGuard
{
    public function assertOpen(string $companyId, string $date): void
    {
        $label = substr($date, 0, 7); // "YYYY-MM"

        $closed = DB::table('fin_fiscal_periods')
            ->where('company_id', $companyId)
            ->where('label', $label)
            ->where('status', 'closed')
            ->exists();

        if ($closed) {
            throw new RuntimeException("Fiscal period {$label} is closed; post to an open period or reopen it.");
        }
    }
}
