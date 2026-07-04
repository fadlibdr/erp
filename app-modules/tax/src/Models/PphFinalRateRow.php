<?php

declare(strict_types=1);

namespace Modules\Tax\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Eloquent row for a stored PPh-final rate. The domain resolver operates on the
 * pure PphFinalRate value objects; this model is just the persistence + seed
 * surface, loaded into a PphFinalRateTable by the repository.
 *
 * @property string $service_class
 * @property string $sbu_class
 * @property int $rate_numerator
 * @property Carbon $effective_from
 * @property Carbon|null $effective_to
 * @property string $regulation_ref
 */
final class PphFinalRateRow extends Model
{
    use HasUuids;

    protected $table = 'tax_pph_final_rates';

    protected $fillable = ['service_class', 'sbu_class', 'rate_numerator', 'effective_from', 'effective_to', 'regulation_ref'];

    protected $casts = [
        'rate_numerator' => 'integer',
        'effective_from' => 'date',
        'effective_to' => 'date',
    ];
}
