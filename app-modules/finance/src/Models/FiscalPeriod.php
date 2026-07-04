<?php

declare(strict_types=1);

namespace Modules\Finance\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * An accounting period. Posting is refused into a `closed` period by
 * FiscalPeriodGuard; CloseFiscalPeriod flips the flag after the month-end
 * recognition run, and ReopenFiscalPeriod flips it back for a correction.
 *
 * @property string $id
 * @property string $company_id
 * @property string $label
 * @property Carbon $starts_on
 * @property Carbon $ends_on
 * @property string $status
 * @property Carbon|null $closed_at
 */
final class FiscalPeriod extends Model
{
    use HasUuids;

    protected $table = 'fin_fiscal_periods';

    protected $fillable = ['company_id', 'label', 'starts_on', 'ends_on', 'status', 'closed_at'];

    protected $casts = [
        'starts_on' => 'date',
        'ends_on' => 'date',
        'closed_at' => 'datetime',
    ];

    public function isClosed(): bool
    {
        return $this->status === 'closed';
    }
}
