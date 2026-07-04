<?php

declare(strict_types=1);

namespace Modules\Finance\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * One auditable PSAK 72 recognition run — a project's percentage-of-completion,
 * recognized-to-date and billed-to-date, and the resulting balance-sheet position,
 * stamped for one fiscal period. Unique per (project, period) so a re-close cannot
 * double-recognize.
 *
 * @property string $id
 * @property string $company_id
 * @property string $project_id
 * @property string $fiscal_period_id
 * @property int $poc_ratio_ppm
 * @property int $recognized_to_date_minor
 * @property int $billed_to_date_minor
 * @property int $contract_asset_minor
 * @property int $contract_liability_minor
 * @property string|null $journal_id
 * @property string $currency
 */
final class RevrecRun extends Model
{
    use HasUuids;

    protected $table = 'fin_revrec_runs';

    protected $fillable = [
        'company_id', 'project_id', 'fiscal_period_id', 'poc_ratio_ppm',
        'recognized_to_date_minor', 'billed_to_date_minor',
        'contract_asset_minor', 'contract_liability_minor', 'journal_id', 'currency',
    ];

    protected $casts = [
        'poc_ratio_ppm' => 'integer',
        'recognized_to_date_minor' => 'integer',
        'billed_to_date_minor' => 'integer',
        'contract_asset_minor' => 'integer',
        'contract_liability_minor' => 'integer',
    ];
}
