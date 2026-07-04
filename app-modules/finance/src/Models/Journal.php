<?php

declare(strict_types=1);

namespace Modules\Finance\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An immutable posted journal. There is intentionally no update path; the only
 * lifecycle operation is posting a reversal that points back via
 * reverses_journal_id.
 *
 * @property string $id
 * @property string $number
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

    /** @return HasMany<JournalLine> */
    public function lines(): HasMany
    {
        return $this->hasMany(JournalLine::class);
    }
}
