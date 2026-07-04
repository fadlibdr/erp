<?php

declare(strict_types=1);

namespace Modules\Payables\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * One bill settled within a payment batch, for a given amount.
 *
 * @property string $id
 * @property string $payment_batch_id
 * @property string $vendor_bill_id
 * @property int $amount_minor
 */
final class PaymentBatchLine extends Model
{
    use HasUuids;

    protected $table = 'ap_payment_batch_lines';

    protected $fillable = ['payment_batch_id', 'vendor_bill_id', 'amount_minor'];

    protected $casts = ['amount_minor' => 'integer'];
}
