<?php

declare(strict_types=1);

namespace Modules\Payables\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A payment batch — one bank run settling a set of approved vendor bills. Posting
 * moves only the net (Dr AP / Cr Bank); the withheld PPh/retensi payables persist.
 *
 * @property string $id
 * @property string $company_id
 * @property string|null $number
 * @property int $total_minor
 * @property string $currency
 */
final class PaymentBatch extends Model
{
    use HasUuids;

    protected $table = 'ap_payment_batches';

    protected $fillable = ['company_id', 'number', 'payment_date', 'bank', 'total_minor', 'currency'];

    protected $casts = ['payment_date' => 'date', 'total_minor' => 'integer'];

    /** @return HasMany<PaymentBatchLine> */
    public function lines(): HasMany
    {
        return $this->hasMany(PaymentBatchLine::class);
    }
}
