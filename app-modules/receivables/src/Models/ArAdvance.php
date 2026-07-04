<?php

declare(strict_types=1);

namespace Modules\Receivables\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Uang muka received from a customer and its running recovery (amortized as termins
 * recover it). Amortization schedule is a later pass; this is the sub-ledger it hangs on.
 *
 * @property string $id
 * @property string $company_id
 * @property string $project_id
 * @property int $received_minor
 * @property int $recovered_minor
 * @property string $currency
 */
final class ArAdvance extends Model
{
    use HasUuids;

    protected $table = 'ar_advances';

    protected $fillable = ['company_id', 'project_id', 'received_minor', 'recovered_minor', 'currency'];

    protected $casts = ['received_minor' => 'integer', 'recovered_minor' => 'integer'];
}
