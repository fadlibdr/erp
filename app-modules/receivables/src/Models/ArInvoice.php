<?php

declare(strict_types=1);

namespace Modules\Receivables\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Platform\Models\Company;

/**
 * A customer AR invoice, born from a termin (billing) fact. Settled by cash receipts;
 * its retention rides a separate sub-ledger (ArRetention).
 *
 * @property string $id
 * @property string $company_id
 * @property string|null $project_id
 * @property string|null $source_claim_id
 * @property string $status
 * @property int $gross_minor
 * @property int $net_minor
 * @property string $currency
 */
final class ArInvoice extends Model
{
    use HasUuids;

    protected $table = 'ar_invoices';

    protected $fillable = [
        'company_id', 'project_id', 'customer_id', 'source_claim_id', 'number',
        'invoice_date', 'status', 'gross_minor', 'net_minor', 'currency',
    ];

    protected $casts = ['invoice_date' => 'date', 'gross_minor' => 'integer', 'net_minor' => 'integer'];

    /** @return BelongsTo<Company, $this> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
