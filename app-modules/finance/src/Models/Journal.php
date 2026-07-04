<?php

declare(strict_types=1);

namespace Modules\Finance\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * An immutable posted journal. There is intentionally no update path; the only
 * lifecycle operation is posting a reversal that points back via
 * reverses_journal_id.
 *
 * @property string $id
 * @property string $company_id
 * @property string $number
 * @property Carbon $date
 * @property string $description
 * @property string|null $fact_type
 * @property string|null $source_reference
 * @property string|null $reverses_journal_id
 * @property string $currency
 * @property int $total_minor
 */
final class Journal extends Model
{
    use HasUuids;

    protected $table = 'fin_journals';

    protected $fillable = [
        'company_id', 'number', 'date', 'description', 'fact_type',
        'source_reference', 'reverses_journal_id', 'currency', 'total_minor',
    ];

    protected $casts = ['date' => 'date', 'total_minor' => 'integer'];

    /** @return HasMany<JournalLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(JournalLine::class);
    }

    // Tenant ownership: Filament scopes the GL viewer to the current company.
    public function company(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\Modules\Platform\Models\Company::class);
    }
}
