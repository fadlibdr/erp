<?php

declare(strict_types=1);

namespace Modules\Finance\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * A projection, not a journal. One row per (source document × WBS × cost code):
 * `committed_minor` is raised when a PO/subcontract is approved and `consumed_minor`
 * climbs as goods/work arrive. The open commitment (committed − consumed) plus GL
 * actuals is what the control-budget gate weighs against the budget ceiling.
 *
 * @property string $id
 * @property string $company_id
 * @property string $project_id
 * @property string|null $wbs_id
 * @property string|null $cost_code
 * @property string $source_type
 * @property string $source_id
 * @property int $committed_minor
 * @property int $consumed_minor
 * @property string $currency
 */
final class Commitment extends Model
{
    use HasUuids;

    protected $table = 'fin_commitments';

    protected $fillable = [
        'company_id', 'project_id', 'wbs_id', 'cost_code',
        'source_type', 'source_id', 'committed_minor', 'consumed_minor', 'currency',
    ];

    protected $casts = [
        'committed_minor' => 'integer',
        'consumed_minor' => 'integer',
    ];
}
