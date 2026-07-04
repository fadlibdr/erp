<?php

declare(strict_types=1);

namespace Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Platform\Models\Company;

/**
 * A stock item — material tracked through the moving-average stock ledger.
 *
 * @property string $id
 * @property string $company_id
 * @property string $code
 * @property string $name
 * @property string $unit
 */
final class Item extends Model
{
    use HasUuids;

    protected $table = 'inv_items';

    protected $fillable = ['company_id', 'code', 'name', 'unit'];

    /** @return BelongsTo<Company, $this> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
