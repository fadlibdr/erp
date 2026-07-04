<?php

declare(strict_types=1);

namespace Modules\Projects\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Platform\Domain\Currency;
use Modules\Platform\Domain\Money;

/**
 * @property string $id
 * @property string $code
 * @property int $contract_value_minor
 * @property string $currency
 * @property int $retention_percent
 * @property int $uang_muka_percent
 * @property string $service_class
 * @property \Illuminate\Support\Carbon|null $contract_date
 */
final class Project extends Model
{
    use HasUuids;
    use SoftDeletes;

    protected $table = 'prj_projects';

    protected $fillable = [
        'company_id', 'code', 'name', 'customer_id', 'contract_number', 'contract_date',
        'service_class', 'contract_value_minor', 'currency', 'retention_percent',
        'uang_muka_percent', 'status', 'pho_date', 'fho_date', 'custom_fields',
    ];

    protected $casts = [
        'contract_date' => 'date',
        'pho_date' => 'date',
        'fho_date' => 'date',
        'contract_value_minor' => 'integer',
        'retention_percent' => 'integer',
        'uang_muka_percent' => 'integer',
        'custom_fields' => 'array',
    ];

    public function contractValue(): Money
    {
        return Money::ofMinor($this->contract_value_minor, Currency::from($this->currency));
    }

    /** @return HasMany<Wbs> */
    public function wbs(): HasMany
    {
        return $this->hasMany(Wbs::class);
    }

    /** @return HasMany<BudgetLine> */
    public function budgetLines(): HasMany
    {
        return $this->hasMany(BudgetLine::class);
    }
}
