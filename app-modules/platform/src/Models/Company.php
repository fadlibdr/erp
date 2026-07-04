<?php

declare(strict_types=1);

namespace Modules\Platform\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $id
 * @property string $code
 * @property string $name
 * @property bool $is_kso
 * @property string $base_currency
 */
final class Company extends Model
{
    use HasUuids;
    use SoftDeletes;

    protected $fillable = [
        'code', 'name', 'legal_name', 'npwp', 'is_pkp', 'base_currency',
        'is_kso', 'kso_lead_company_id', 'sbu_class', 'sbu_valid_until', 'custom_fields',
    ];

    protected $casts = [
        'is_pkp' => 'boolean',
        'is_kso' => 'boolean',
        'sbu_valid_until' => 'date',
        'custom_fields' => 'array',
    ];

    /** @return HasMany<Branch> */
    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class);
    }
}
