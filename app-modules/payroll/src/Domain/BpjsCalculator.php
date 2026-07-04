<?php

declare(strict_types=1);

namespace Modules\Payroll\Domain;

use Modules\Platform\Domain\Money;

/**
 * BPJS contributions on a monthly gross — Ketenagakerjaan (JHT, JP, JKK, JKM) and
 * Kesehatan — split into employee and employer shares.
 *
 *   JHT  employee 2%   · employer 3.70%
 *   JP   employee 1%   · employer 2%     (on a salary ceiling)
 *   JKK  employer 0.24% (lowest risk class)
 *   JKM  employer 0.30%
 *   Kes  employee 1%   · employer 4%     (on a salary ceiling)
 *
 * Rates are held as integer numerators over 10 000 so the math stays exact with
 * banker's rounding (Money::applyRate). The JP and Kesehatan ceilings are the
 * statutory caps a contribution base is clamped to; they are the figures the team
 * reconciles yearly.
 */
final class BpjsCalculator
{
    private const D = 10_000;

    // Contribution rates (over 10 000).
    private const JHT_EMPLOYEE = 200;

    private const JHT_EMPLOYER = 370;

    private const JP_EMPLOYEE = 100;

    private const JP_EMPLOYER = 200;

    private const JKK_EMPLOYER = 24;

    private const JKM_EMPLOYER = 30;

    private const KES_EMPLOYEE = 100;

    private const KES_EMPLOYER = 400;

    public function __construct(
        private readonly int $jpCeilingMinor = 9_559_600,
        private readonly int $kesCeilingMinor = 12_000_000,
    ) {}

    public function on(Money $gross): BpjsResult
    {
        $jpBase = $this->cap($gross, $this->jpCeilingMinor);
        $kesBase = $this->cap($gross, $this->kesCeilingMinor);

        $employee = $gross->applyRate(self::JHT_EMPLOYEE, self::D)
            ->add($jpBase->applyRate(self::JP_EMPLOYEE, self::D))
            ->add($kesBase->applyRate(self::KES_EMPLOYEE, self::D));

        $employer = $gross->applyRate(self::JHT_EMPLOYER, self::D)
            ->add($jpBase->applyRate(self::JP_EMPLOYER, self::D))
            ->add($gross->applyRate(self::JKK_EMPLOYER, self::D))
            ->add($gross->applyRate(self::JKM_EMPLOYER, self::D))
            ->add($kesBase->applyRate(self::KES_EMPLOYER, self::D));

        return new BpjsResult($employee, $employer);
    }

    private function cap(Money $gross, int $ceilingMinor): Money
    {
        return $gross->minor > $ceilingMinor ? Money::ofMinor($ceilingMinor, $gross->currency) : $gross;
    }
}
