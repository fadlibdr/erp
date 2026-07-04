<?php

declare(strict_types=1);

namespace Modules\Payroll\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * One employee's decomposed pay within a run.
 *
 * @property string $id
 * @property string $pay_run_id
 * @property string $employee_id
 * @property int $gross_minor
 * @property int $pph21_minor
 * @property int $bpjs_employee_minor
 * @property int $bpjs_employer_minor
 * @property int $net_minor
 */
final class PayRunLine extends Model
{
    use HasUuids;

    protected $table = 'pay_run_lines';

    protected $fillable = [
        'pay_run_id', 'employee_id', 'gross_minor', 'pph21_minor',
        'bpjs_employee_minor', 'bpjs_employer_minor', 'net_minor',
    ];

    protected $casts = [
        'gross_minor' => 'integer',
        'pph21_minor' => 'integer',
        'bpjs_employee_minor' => 'integer',
        'bpjs_employer_minor' => 'integer',
        'net_minor' => 'integer',
    ];
}
