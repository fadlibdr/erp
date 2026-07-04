<?php

declare(strict_types=1);

namespace Modules\Finance\Services;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Reads the two Finance-owned numbers the control-budget gate needs for one
 * (project × WBS × cost code) bucket: the open commitment (from fin_commitments)
 * and the actuals booked (net debits on fin_journal_lines for that dimension).
 *
 * It only *reads* Finance tables, so a domain module (Procurement) asking for
 * exposure before approving a PO stays within the dependency law — no module
 * reaches into another's writes. The budget ceiling itself is a Projects concern
 * and is read there; this service deliberately does not know it.
 */
final class CommitmentRepository
{
    /**
     * @return array{open: int, actual: int}
     */
    public function exposureFor(string $companyId, string $projectId, ?string $wbsId, ?string $costCode): array
    {
        $open = (int) $this->dimension(
            DB::table('fin_commitments')->where('company_id', $companyId)->where('project_id', $projectId),
            $wbsId,
            $costCode,
        )->sum(DB::raw('committed_minor - consumed_minor'));

        $actual = (int) $this->dimension(
            DB::table('fin_journal_lines')->where('company_id', $companyId)->where('project_id', $projectId),
            $wbsId,
            $costCode,
        )->sum(DB::raw('debit_minor - credit_minor'));

        return ['open' => $open, 'actual' => $actual];
    }

    /** Apply the nullable WBS / cost-code equality filters (NULL matches NULL). */
    private function dimension(Builder $query, ?string $wbsId, ?string $costCode): Builder
    {
        $wbsId === null ? $query->whereNull('wbs_id') : $query->where('wbs_id', $wbsId);
        $costCode === null ? $query->whereNull('cost_code') : $query->where('cost_code', $costCode);

        return $query;
    }
}
