<?php

declare(strict_types=1);

namespace Modules\Finance\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $company_id
 * @property string $account_code
 * @property int $debit_minor
 * @property int $credit_minor
 * @property string $currency
 * @property string|null $project_id
 * @property string|null $wbs_id
 * @property string|null $cost_code
 * @property string|null $memo
 */
final class JournalLine extends Model
{
    use HasUuids;

    protected $table = 'fin_journal_lines';

    protected $fillable = [
        'journal_id', 'company_id', 'account_code', 'debit_minor', 'credit_minor',
        'currency', 'project_id', 'wbs_id', 'cost_code', 'memo',
    ];

    protected $casts = ['debit_minor' => 'integer', 'credit_minor' => 'integer'];

    /** @return BelongsTo<Journal, $this> */
    public function journal(): BelongsTo
    {
        return $this->belongsTo(Journal::class);
    }
}
