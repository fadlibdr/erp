<?php

declare(strict_types=1);

namespace Modules\Projects\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $project_id
 * @property string $code
 * @property string $path
 * @property int $depth
 */
final class Wbs extends Model
{
    use HasUuids;

    protected $table = 'prj_wbs';

    protected $fillable = ['project_id', 'parent_id', 'code', 'path', 'name', 'depth', 'weight_ppm'];

    protected $casts = ['depth' => 'integer', 'weight_ppm' => 'integer'];

    /** @return BelongsTo<Project, Wbs> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsTo<Wbs, Wbs> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }
}
