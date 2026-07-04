<?php

declare(strict_types=1);

namespace Modules\Tax\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Modules\Tax\Domain\EfakturSubmissionStatus;

/**
 * One e-Faktur submission — the outbox to Coretax. `dedup_key` is unique so a fact
 * relayed twice never files a tax invoice twice; `status` is driven only through the
 * EfakturSubmissionStatus guard. Request/response payloads are kept for audit and
 * replay.
 *
 * @property string $id
 * @property string $company_id
 * @property string $source_type
 * @property string $source_id
 * @property EfakturSubmissionStatus $status
 * @property string|null $channel
 * @property string|null $dedup_key
 * @property string|null $nsfp
 * @property string|null $approval_code
 * @property array<string, mixed>|null $request_payload
 * @property array<string, mixed>|null $response_payload
 * @property int $attempts
 * @property string|null $last_error
 */
final class EfakturSubmission extends Model
{
    use HasUuids;

    protected $table = 'tax_efaktur_submissions';

    protected $fillable = [
        'company_id', 'source_type', 'source_id', 'status', 'channel', 'dedup_key',
        'nsfp', 'approval_code', 'request_payload', 'response_payload', 'attempts', 'last_error', 'acked_at',
    ];

    protected $casts = [
        'status' => EfakturSubmissionStatus::class,
        'request_payload' => 'array',
        'response_payload' => 'array',
        'attempts' => 'integer',
        'acked_at' => 'datetime',
    ];
}
