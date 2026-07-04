<?php

declare(strict_types=1);

namespace Modules\Procurement\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Platform\Models\Company;

/**
 * A goods receipt note (penerimaan barang) against a PO. Receiving consumes the
 * PO's commitment, books the GR/IR accrual in the GL, and moves stock into a
 * warehouse at moving-average cost — all downstream of the outbox fact it publishes.
 *
 * @property string $id
 * @property string $company_id
 * @property string $purchase_order_id
 * @property string|null $number
 * @property int $total_minor
 * @property string $currency
 */
final class Grn extends Model
{
    use HasUuids;

    protected $table = 'proc_grns';

    protected $fillable = [
        'company_id', 'purchase_order_id', 'number', 'received_date', 'total_minor', 'currency',
    ];

    protected $casts = [
        'received_date' => 'date',
        'total_minor' => 'integer',
    ];

    /** @return HasMany<GrnLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(GrnLine::class, 'grn_id');
    }

    /** @return BelongsTo<Company, $this> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
