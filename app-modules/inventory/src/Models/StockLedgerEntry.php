<?php

declare(strict_types=1);

namespace Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * An append-only stock movement, like a GL line for goods. Each row records the
 * delta (qty and moving-average value) and the resulting balance, so any past
 * valuation is reproducible. Written by the stock-ledger writer, never edited.
 *
 * @property string $id
 * @property string $company_id
 * @property string $item_id
 * @property string $warehouse_id
 * @property string $movement_type
 * @property string|null $source_id
 * @property string $qty_delta
 * @property int $value_delta_minor
 * @property string $balance_qty
 * @property int $balance_value_minor
 */
final class StockLedgerEntry extends Model
{
    use HasUuids;

    public $timestamps = true;

    protected $table = 'inv_stock_ledger';

    protected $fillable = [
        'company_id', 'item_id', 'warehouse_id', 'movement_type', 'source_id',
        'qty_delta', 'value_delta_minor', 'balance_qty', 'balance_value_minor', 'posted_at',
    ];

    protected $casts = [
        'value_delta_minor' => 'integer',
        'balance_value_minor' => 'integer',
        'posted_at' => 'datetime',
    ];
}
