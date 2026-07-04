<?php

declare(strict_types=1);

namespace Modules\Projects\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * A locked control-budget ceiling for one WBS node and cost code. Procurement
 * checks committed + actual against this before letting a PO through.
 *
 * @property string $project_id
 * @property string $wbs_id
 * @property string $cost_code
 * @property int $budget_minor
 */
final class BudgetLine extends Model
{
    use HasUuids;

    protected $table = 'prj_budget_lines';

    protected $fillable = ['project_id', 'wbs_id', 'cost_code', 'budget_minor', 'currency'];

    protected $casts = ['budget_minor' => 'integer'];
}
