<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\Ledger;

use Modules\Platform\Domain\Currency;
use Modules\Platform\Domain\Money;

/**
 * Posting rule for an approved payroll run (payroll.run_approved):
 *
 *   Dr Labor Cost        gross wages, tagged to the project/WBS (site labor cost)
 *   Dr BPJS Expense      the employer BPJS share (a company cost)
 *       Cr Salaries Payable   net take-home owed to employees
 *       Cr PPh 21 Payable     employee income tax withheld, owed to the state
 *       Cr BPJS Payable       total BPJS (employee + employer) owed to BPJS
 *
 * Balanced by construction: debits (gross + employer BPJS) equal credits
 * (net + PPh 21 + employee BPJS + employer BPJS), since net = gross − PPh 21 −
 * employee BPJS. JournalDraft re-checks it regardless.
 */
final class PayrollPostingRule implements PostingRule
{
    public const FACT_TYPE = 'payroll.run_approved';

    public function factType(): string
    {
        return self::FACT_TYPE;
    }

    public function toJournal(array $payload, AccountMap $accounts): JournalDraft
    {
        $currency = Currency::from($payload['currency']);
        $project = $payload['project_id'] ?? null;
        $wbs = $payload['wbs_id'] ?? null;
        $costCode = $payload['cost_code'] ?? null;

        $money = static fn (string $k): Money => Money::ofMinor((int) $payload[$k], $currency);
        $bpjsPayable = Money::ofMinor((int) $payload['bpjs_employee'] + (int) $payload['bpjs_employer'], $currency);

        $lines = [
            JournalLineDraft::debit($accounts->code('labor_cost'), $money('labor'), $project, $wbs, $costCode, memo: 'Beban upah proyek'),
            JournalLineDraft::debit($accounts->code('bpjs_expense'), $money('bpjs_employer'), $project, $wbs, $costCode, memo: 'Beban BPJS (pemberi kerja)'),
            JournalLineDraft::credit($accounts->code('salaries_payable'), $money('net'), memo: 'Utang gaji (neto)'),
            JournalLineDraft::credit($accounts->code('pph21_payable'), $money('pph21'), memo: 'Utang PPh 21'),
            JournalLineDraft::credit($accounts->code('bpjs_payable'), $bpjsPayable, memo: 'Utang BPJS'),
        ];

        $lines = array_values(array_filter(
            $lines,
            static fn (JournalLineDraft $l) => ! $l->debit->isZero() || ! $l->credit->isZero(),
        ));

        return new JournalDraft(
            description: 'Penggajian '.($payload['run_id'] ?? ''),
            lines: $lines,
            factType: self::FACT_TYPE,
            sourceReference: $payload['run_id'] ?? null,
        );
    }
}
