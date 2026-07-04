<?php

declare(strict_types=1);

namespace Modules\Procurement\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Platform\Models\Company;
use Modules\Projects\Models\Project;

/**
 * A purchase order. On approval it raises a commitment against the project control
 * budget (via the outbox → Finance commitment ledger); as goods arrive against it
 * that commitment is consumed and turns into an actual accrual.
 *
 * @property string $id
 * @property string $company_id
 * @property string|null $project_id
 * @property string $vendor_id
 * @property string|null $number
 * @property string $status
 * @property int $total_minor
 * @property string $currency
 * @property string $budget_status
 */
final class PurchaseOrder extends Model
{
    use HasUuids;

    protected $table = 'proc_purchase_orders';

    protected $fillable = [
        'company_id', 'project_id', 'vendor_id', 'number', 'po_date',
        'status', 'total_minor', 'currency', 'budget_status',
    ];

    protected $casts = [
        'po_date' => 'date',
        'total_minor' => 'integer',
    ];

    /** @return HasMany<PurchaseOrderLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(PurchaseOrderLine::class);
    }

    // Tenant ownership: Filament scopes this resource to the current company.
    /** @return BelongsTo<Company, $this> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /** @return BelongsTo<Vendor, $this> */
    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
