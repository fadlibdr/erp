<?php

declare(strict_types=1);

namespace Modules\Finance\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Modules\Finance\Domain\Account\AccountType;

/**
 * @property string $id
 * @property string $company_id
 * @property string $code
 * @property AccountType $type
 * @property bool $is_postable
 */
final class Account extends Model
{
    use HasUuids;

    protected $table = 'fin_accounts';

    protected $fillable = ['company_id', 'code', 'name', 'type', 'parent_id', 'is_postable', 'currency', 'active'];

    protected $casts = [
        'type' => AccountType::class,
        'is_postable' => 'boolean',
        'active' => 'boolean',
    ];
}
