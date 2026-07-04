<?php

declare(strict_types=1);

namespace Modules\Platform\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $entity
 * @property string $key
 * @property string $type
 */
final class CustomFieldDef extends Model
{
    use HasUuids;

    protected $fillable = ['company_id', 'entity', 'key', 'label', 'type', 'options', 'required', 'sort', 'promoted_at'];

    protected $casts = [
        'options' => 'array',
        'required' => 'boolean',
        'sort' => 'integer',
        'promoted_at' => 'datetime',
    ];
}
