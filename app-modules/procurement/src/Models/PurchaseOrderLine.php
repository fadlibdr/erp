<?php

declare(strict_types=1);

namespace Modules\Procurement\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * One ordered item on a PO, tagged with the WBS node and cost code it will burden.
 * Those two dimensions are what the commitment is bucketed by, so it lands against
 * the same (WBS × cost code) budget line the control budget is drawn on.
 *
 * @property string $id
 * @property string $purchase_order_id
 * @property string|null $wbs_id
 * @property string|null $cost_code
 * @property string $description
 * @property string $quantity
 * @property int $unit_rate_minor
 * @property int $amount_minor
 */
final class PurchaseOrderLine extends Model
{
    use HasUuids;

    protected $table = 'proc_purchase_order_lines';

    protected $fillable = [
        'purchase_order_id', 'wbs_id', 'cost_code', 'description',
        'quantity', 'unit_rate_minor', 'amount_minor',
    ];

    protected $casts = [
        'unit_rate_minor' => 'integer',
        'amount_minor' => 'integer',
    ];
}
