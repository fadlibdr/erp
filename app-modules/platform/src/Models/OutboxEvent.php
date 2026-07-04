<?php

declare(strict_types=1);

namespace Modules\Platform\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $company_id
 * @property string $type
 * @property array $payload
 * @property string|null $dedup_key
 * @property \Illuminate\Support\Carbon|null $processed_at
 */
final class OutboxEvent extends Model
{
    use HasUuids;

    protected $table = 'event_outbox';

    protected $fillable = ['company_id', 'type', 'payload', 'dedup_key', 'available_at', 'processed_at', 'attempts', 'last_error'];

    protected $casts = [
        'payload' => 'array',
        'available_at' => 'datetime',
        'processed_at' => 'datetime',
        'attempts' => 'integer',
    ];
}
