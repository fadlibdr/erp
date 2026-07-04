<?php

declare(strict_types=1);

namespace Modules\Receivables\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * The retention withheld by a customer on a termin — held until final hand-over
 * (FHO / BAST-II), then released and paid. The sub-ledger Accurate users work around.
 *
 * @property string $id
 * @property string $company_id
 * @property string $project_id
 * @property string|null $source_claim_id
 * @property int $amount_minor
 * @property string $currency
 * @property string $status
 */
final class ArRetention extends Model
{
    use HasUuids;

    protected $table = 'ar_retentions';

    protected $fillable = [
        'company_id', 'project_id', 'source_claim_id', 'amount_minor',
        'currency', 'status', 'expected_release_date', 'released_at',
    ];

    protected $casts = [
        'amount_minor' => 'integer',
        'expected_release_date' => 'date',
        'released_at' => 'datetime',
    ];
}
