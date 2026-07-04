<?php

declare(strict_types=1);

namespace Modules\Procurement\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * A supplier or subcontractor. Two fields drive downstream tax behaviour and are
 * the reason Payables reads this model at all:
 *   - sbu_class: the subcontractor's business qualification, which selects the
 *     PPh-final konstruksi rate the main contractor withholds from its payment;
 *   - is_pkp: whether the vendor is a taxable entrepreneur that issues a faktur,
 *     which is what makes the PPN on its bill creditable input VAT.
 *
 * @property string $id
 * @property string $company_id
 * @property string $code
 * @property string $name
 * @property string|null $npwp
 * @property string|null $sbu_class
 * @property bool $is_pkp
 */
final class Vendor extends Model
{
    use HasUuids;

    protected $table = 'proc_vendors';

    protected $fillable = [
        'company_id', 'code', 'name', 'npwp', 'sbu_class', 'is_pkp',
    ];

    protected $casts = [
        'is_pkp' => 'boolean',
    ];
}
