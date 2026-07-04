<?php

declare(strict_types=1);

namespace Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Platform\Models\Company;

/**
 * A material issue from a warehouse to a WBS/cost code — the moment stored stock
 * becomes an actual project cost. The issue is valued at moving average and, via
 * the outbox, posts Dr project material cost / Cr inventory.
 *
 * @property string $id
 * @property string $company_id
 * @property string $project_id
 * @property string|null $wbs_id
 * @property string $cost_code
 * @property string $warehouse_id
 * @property int $total_minor
 */
final class MaterialIssue extends Model
{
    use HasUuids;

    protected $table = 'inv_issues';

    protected $fillable = [
        'company_id', 'project_id', 'wbs_id', 'cost_code', 'warehouse_id', 'issue_date', 'total_minor',
    ];

    protected $casts = [
        'issue_date' => 'date',
        'total_minor' => 'integer',
    ];

    /** @return BelongsTo<Company, $this> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /** @return HasMany<MaterialIssueLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(MaterialIssueLine::class);
    }
}
