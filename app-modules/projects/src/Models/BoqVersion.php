<?php

declare(strict_types=1);

namespace Modules\Projects\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A versioned Bill of Quantities: tender -> contract -> variation orders. Locking
 * a version freezes its lines so a contract BOQ can't drift after award.
 *
 * @property string $id
 * @property string $label
 * @property int $revision
 * @property string $status
 */
final class BoqVersion extends Model
{
    use HasUuids;

    protected $table = 'prj_boq_versions';

    protected $fillable = ['project_id', 'label', 'revision', 'status', 'total_minor', 'currency', 'locked_at'];

    protected $casts = ['revision' => 'integer', 'total_minor' => 'integer', 'locked_at' => 'datetime'];

    public function isLocked(): bool
    {
        return $this->status === 'locked';
    }

    /** @return HasMany<BoqLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(BoqLine::class, 'boq_version_id');
    }
}
