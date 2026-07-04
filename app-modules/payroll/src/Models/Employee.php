<?php

declare(strict_types=1);

namespace Modules\Payroll\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Modules\Tax\Domain\PtkpStatus;

/**
 * A payrolled employee. PTKP status drives the monthly PPh 21 TER category.
 *
 * @property string $id
 * @property string $company_id
 * @property string $code
 * @property string $name
 * @property string $ptkp_status
 * @property int $monthly_gross_minor
 */
final class Employee extends Model
{
    use HasUuids;

    protected $table = 'pay_employees';

    protected $fillable = ['company_id', 'code', 'name', 'npwp', 'ptkp_status', 'monthly_gross_minor'];

    protected $casts = ['monthly_gross_minor' => 'integer'];

    public function ptkp(): PtkpStatus
    {
        return PtkpStatus::from($this->ptkp_status);
    }
}
