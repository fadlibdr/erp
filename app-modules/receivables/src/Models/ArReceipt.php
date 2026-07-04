<?php

declare(strict_types=1);

namespace Modules\Receivables\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * A customer cash receipt against an AR invoice.
 *
 * @property string $id
 * @property string $company_id
 * @property string|null $project_id
 * @property string $ar_invoice_id
 * @property int $amount_minor
 * @property string $currency
 */
final class ArReceipt extends Model
{
    use HasUuids;

    protected $table = 'ar_receipts';

    protected $fillable = ['company_id', 'project_id', 'ar_invoice_id', 'receipt_date', 'amount_minor', 'currency'];

    protected $casts = ['receipt_date' => 'date', 'amount_minor' => 'integer'];
}
