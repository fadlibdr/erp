<?php

declare(strict_types=1);

namespace Modules\Billing\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Platform\Models\Company;

/**
 * @property string $id
 * @property string $company_id
 * @property string $project_id
 * @property string $number
 * @property int $sequence
 * @property string $status
 * @property int $work_value_minor
 * @property string $currency
 */
final class ProgressClaim extends Model
{
    use HasUuids;

    protected $table = 'bil_claims';

    protected $fillable = [
        'company_id', 'project_id', 'number', 'sequence', 'claim_date', 'status',
        'work_value_minor', 'ppn_output_minor', 'retention_minor', 'uang_muka_recovery_minor',
        'pph_final_minor', 'net_receivable_minor', 'pph_rate_numerator', 'pph_regulation_ref', 'currency',
    ];

    protected $casts = [
        'claim_date' => 'date',
        'sequence' => 'integer',
        'work_value_minor' => 'integer',
        'ppn_output_minor' => 'integer',
        'retention_minor' => 'integer',
        'uang_muka_recovery_minor' => 'integer',
        'pph_final_minor' => 'integer',
        'net_receivable_minor' => 'integer',
        'pph_rate_numerator' => 'integer',
    ];

    // Tenant ownership: Filament scopes this resource to the current company.
    /** @return BelongsTo<Company, $this> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
