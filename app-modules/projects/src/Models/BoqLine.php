<?php

declare(strict_types=1);

namespace Modules\Projects\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * One priced line of a BOQ version, mapped to a WBS node. Progress is later
 * measured against these lines, weighted by amount.
 *
 * @property string $id
 * @property string $boq_version_id
 * @property string|null $wbs_id
 * @property int $amount_minor
 */
final class BoqLine extends Model
{
    use HasUuids;

    protected $table = 'prj_boq_lines';

    protected $fillable = [
        'boq_version_id', 'project_id', 'wbs_id', 'item_code', 'description',
        'unit', 'quantity', 'unit_rate_minor', 'amount_minor', 'sort',
    ];

    protected $casts = [
        'quantity' => 'decimal:3',
        'unit_rate_minor' => 'integer',
        'amount_minor' => 'integer',
        'sort' => 'integer',
    ];
}
