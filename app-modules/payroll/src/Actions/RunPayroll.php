<?php

declare(strict_types=1);

namespace Modules\Payroll\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Payroll\Domain\PayrollCalculator;
use Modules\Payroll\Domain\PayrollRunFact;
use Modules\Payroll\Events\PayrollRunApproved;
use Modules\Payroll\Models\Employee;
use Modules\Payroll\Models\PayRun;
use Modules\Payroll\Models\PayRunLine;
use Modules\Platform\Actions\Action;
use Modules\Platform\Domain\Currency;
use Modules\Platform\Domain\Money;
use Modules\Platform\Support\Outbox;
use RuntimeException;

/**
 * Runs monthly payroll for a set of employees, charged to a project/WBS. Each
 * employee's pay is decomposed (PPh 21 TER + BPJS) by the PayrollCalculator, the
 * run's totals are frozen on the run + its lines, and one fact is published for
 * Finance to post the labor-cost journal. The Action never writes a journal.
 */
final class RunPayroll extends Action
{
    public function __construct(
        private readonly PayrollCalculator $calculator,
        private readonly Outbox $outbox,
    ) {}

    /**
     * @param  list<string>  $employeeIds
     */
    public function execute(
        string $companyId,
        ?string $projectId,
        ?string $wbsId,
        string $costCode,
        string $period,
        array $employeeIds,
        string $currencyCode = 'IDR',
    ): PayRun {
        if ($employeeIds === []) {
            throw new RuntimeException('A payroll run needs at least one employee.');
        }
        $currency = Currency::from($currencyCode);

        $employees = Employee::query()->whereIn('id', $employeeIds)->where('company_id', $companyId)->get();

        return DB::transaction(function () use ($companyId, $projectId, $wbsId, $costCode, $period, $employees, $currency): PayRun {
            $run = PayRun::create([
                'company_id' => $companyId, 'project_id' => $projectId, 'wbs_id' => $wbsId,
                'cost_code' => $costCode, 'period' => $period, 'status' => 'approved', 'currency' => $currency->value,
            ]);

            $gross = 0;
            $pph21 = 0;
            $bpjsE = 0;
            $bpjsR = 0;
            $net = 0;

            foreach ($employees as $employee) {
                $r = $this->calculator->calculate(
                    Money::ofMinor((int) $employee->monthly_gross_minor, $currency),
                    $employee->ptkp(),
                );
                PayRunLine::create([
                    'pay_run_id' => $run->id, 'employee_id' => $employee->id,
                    'gross_minor' => $r->gross->minor, 'pph21_minor' => $r->pph21->minor,
                    'bpjs_employee_minor' => $r->bpjsEmployee->minor, 'bpjs_employer_minor' => $r->bpjsEmployer->minor,
                    'net_minor' => $r->net->minor,
                ]);
                $gross += $r->gross->minor;
                $pph21 += $r->pph21->minor;
                $bpjsE += $r->bpjsEmployee->minor;
                $bpjsR += $r->bpjsEmployer->minor;
                $net += $r->net->minor;
            }

            $run->update([
                'gross_minor' => $gross, 'pph21_minor' => $pph21,
                'bpjs_employee_minor' => $bpjsE, 'bpjs_employer_minor' => $bpjsR, 'net_minor' => $net,
            ]);

            $fact = new PayrollRunFact($run->id, $projectId, $wbsId, $costCode, $currency->value, $gross, $pph21, $bpjsE, $bpjsR, $net);
            $event = new PayrollRunApproved($companyId, $fact);
            $this->outbox->publish($event, $event->dedupKey());

            return $run;
        });
    }
}
