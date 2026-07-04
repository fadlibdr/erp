<?php

declare(strict_types=1);

namespace Modules\Payroll\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Platform\Models\Company;

/**
 * A monthly payroll run for a project/WBS — the frozen totals and the fact that
 * posts labor cost to the GL.
 *
 * @property string $id
 * @property string $company_id
 * @property string|null $project_id
 * @property string|null $wbs_id
 * @property string $cost_code
 * @property string $period
 * @property string $status
 * @property int $gross_minor
 * @property int $pph21_minor
 * @property int $bpjs_employee_minor
 * @property int $bpjs_employer_minor
 * @property int $net_minor
 * @property string $currency
 */
final class PayRun extends Model
{
    use HasUuids;

    protected $table = 'pay_runs';

    protected $fillable = [
        'company_id', 'project_id', 'wbs_id', 'cost_code', 'period', 'status',
        'gross_minor', 'pph21_minor', 'bpjs_employee_minor', 'bpjs_employer_minor', 'net_minor', 'currency',
    ];

    protected $casts = [
        'gross_minor' => 'integer',
        'pph21_minor' => 'integer',
        'bpjs_employee_minor' => 'integer',
        'bpjs_employer_minor' => 'integer',
        'net_minor' => 'integer',
    ];

    /** @return HasMany<PayRunLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(PayRunLine::class);
    }

    /** @return BelongsTo<Company, $this> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
