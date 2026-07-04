<?php

declare(strict_types=1);

namespace Modules\Platform\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $company_id
 * @property string $type
 */
final class Branch extends Model
{
    use HasUuids;

    protected $fillable = ['company_id', 'code', 'name', 'type', 'custom_fields'];

    protected $casts = ['custom_fields' => 'array'];

    /** @return BelongsTo<Company, Branch> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
