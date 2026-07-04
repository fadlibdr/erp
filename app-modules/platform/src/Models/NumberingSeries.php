<?php

declare(strict_types=1);

namespace Modules\Platform\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $company_id
 * @property string $key
 * @property string $format
 * @property int $next
 * @property string $period_scope
 * @property string|null $current_period
 */
final class NumberingSeries extends Model
{
    use HasUuids;

    protected $table = 'numbering_series';

    protected $fillable = ['company_id', 'key', 'format', 'next', 'period_scope', 'current_period'];

    protected $casts = ['next' => 'integer'];
}
