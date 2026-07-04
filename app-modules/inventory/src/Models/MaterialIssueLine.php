<?php

declare(strict_types=1);

namespace Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * One item leaving a warehouse on a material issue, valued at moving average.
 *
 * @property string $id
 * @property string $material_issue_id
 * @property string $item_id
 * @property string $warehouse_id
 * @property string|null $wbs_id
 * @property string|null $cost_code
 * @property string $quantity
 * @property int $amount_minor
 */
final class MaterialIssueLine extends Model
{
    use HasUuids;

    protected $table = 'inv_issue_lines';

    protected $fillable = [
        'material_issue_id', 'item_id', 'warehouse_id', 'wbs_id', 'cost_code', 'quantity', 'amount_minor',
    ];

    protected $casts = ['amount_minor' => 'integer'];
}
