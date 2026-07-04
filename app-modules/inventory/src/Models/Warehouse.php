<?php

declare(strict_types=1);

namespace Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Platform\Models\Company;

/**
 * A warehouse. project_id null = central store; set = gudang proyek (site store).
 *
 * @property string $id
 * @property string $company_id
 * @property string|null $project_id
 * @property string $code
 * @property string $name
 */
final class Warehouse extends Model
{
    use HasUuids;

    protected $table = 'inv_warehouses';

    protected $fillable = ['company_id', 'project_id', 'code', 'name'];

    /** @return BelongsTo<Company, $this> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
