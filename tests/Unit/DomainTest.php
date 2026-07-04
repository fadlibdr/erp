<?php

declare(strict_types=1);

use Modules\Billing\Domain\TerminCalculator;
use Modules\Finance\Domain\Ledger\JournalDraft;
use Modules\Finance\Domain\Ledger\JournalLineDraft;
use Modules\Finance\Domain\Ledger\LedgerException;
use Modules\Finance\Domain\Psak72Calculator;
use Modules\Platform\Domain\Currency;
use Modules\Platform\Domain\Money;
use Modules\Tax\Domain\PphFinalRateResolver;
use Modules\Tax\Domain\PphFinalRateTable;
use Modules\Tax\Domain\PpnCalculator;
use Modules\Tax\Domain\SbuClass;
use Modules\Tax\Domain\ServiceClass;

/*
| The pure-domain suite, as Pest tests. These mirror bin/domain-tests.php so the
| ledger and tax invariants are covered by CI's normal `pest` run too. No DB.
*/

it('allocates money with no rupiah lost or created', function () {
    $parts = Money::of(1_000_000, Currency::IDR)->allocate(['a' => 1, 'b' => 1, 'c' => 1]);
    expect($parts['a']->add($parts['b'])->add($parts['c'])->minor)->toBe(1_000_000)
        ->and($parts['a']->minor)->toBe(333_334);
});

it('rounds rate application half to even', function () {
    expect(Money::ofMinor(5, Currency::IDR)->applyRate(1, 2)->minor)->toBe(2)
        ->and(Money::ofMinor(7, Currency::IDR)->applyRate(1, 2)->minor)->toBe(4);
});

it('rejects an unbalanced journal', function () {
    new JournalDraft('bad', [
        JournalLineDraft::debit('1101', Money::of(500, Currency::IDR)),
        JournalLineDraft::credit('4101', Money::of(499, Currency::IDR)),
    ]);
})->throws(LedgerException::class);

it('resolves the EPC PPh-final rate at 2.65%', function () {
    $rate = (new PphFinalRateResolver(PphFinalRateTable::statutory()))
        ->resolve(ServiceClass::IntegratedWork, SbuClass::MediumLargeSpecialist, '2026-07-04');
    expect($rate->rateNumerator)->toBe(265)->and($rate->regulationRef)->toBe('PP 9/2022');
});

it('applies the transitional rule for pre-2022 contracts', function () {
    $rate = (new PphFinalRateResolver(PphFinalRateTable::statutory()))
        ->resolve(ServiceClass::IntegratedWork, SbuClass::MediumLargeSpecialist, '2022-01-01');
    expect($rate->rateNumerator)->toBe(400)->and($rate->regulationRef)->toStartWith('PP 51/2008');
});

it('computes a termin that ties out to the rupiah', function () {
    $rate = (new PphFinalRateResolver(PphFinalRateTable::statutory()))
        ->resolve(ServiceClass::IntegratedWork, SbuClass::MediumLargeSpecialist, '2026-07-04');
    $t = (new TerminCalculator(new PpnCalculator(11)))
        ->calculate(Money::of(1_000_000, Currency::IDR), 5, 20, $rate);

    expect($t->ppnOutput->minor)->toBe(110_000)
        ->and($t->retention->minor)->toBe(50_000)
        ->and($t->uangMukaRecovery->minor)->toBe(200_000)
        ->and($t->pphFinal->minor)->toBe(26_500)
        ->and($t->netReceivable->minor)->toBe(833_500);
});

it('recognizes PSAK 72 revenue cost-to-cost with a contract asset', function () {
    $calc = new Psak72Calculator;
    $poc = $calc->pocRatioPpm(Money::of(2_000_000_000, Currency::IDR), Money::of(8_000_000_000, Currency::IDR));
    $r = $calc->recognize(Money::of(10_000_000_000, Currency::IDR), $poc, Money::zero(Currency::IDR), Money::of(2_000_000_000, Currency::IDR));

    expect($poc)->toBe(250_000)
        ->and($r->recognizedToDate->minor)->toBe(2_500_000_000)
        ->and($r->contractAsset->minor)->toBe(500_000_000);
});
