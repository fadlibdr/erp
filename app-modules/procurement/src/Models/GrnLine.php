<?php

declare(strict_types=1);

namespace Modules\Procurement\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * One received item on a GRN: the item/warehouse it lands in, and the WBS/cost-code
 * bucket whose commitment it consumes.
 *
 * @property string $id
 * @property string $grn_id
 * @property string|null $purchase_order_line_id
 * @property string|null $item_id
 * @property string|null $warehouse_id
 * @property string|null $wbs_id
 * @property string|null $cost_code
 * @property string $quantity
 * @property int $amount_minor
 */
final class GrnLine extends Model
{
    use HasUuids;

    protected $table = 'proc_grn_lines';

    protected $fillable = [
        'grn_id', 'purchase_order_line_id', 'item_id', 'warehouse_id',
        'wbs_id', 'cost_code', 'quantity', 'amount_minor',
    ];

    protected $casts = [
        'amount_minor' => 'integer',
    ];
}
