<?php

declare(strict_types=1);

/**
 * Standalone runner for the pure-domain suite.
 *
 * These tests exercise the framework-agnostic core — Money, the PPh-final rate
 * resolver, the double-entry balance invariant, and the termin calculator — with
 * nothing but the PHP CLI. No Composer, no database, no Laravel. It exists so the
 * highest-risk logic (the ledger and the construction tax) can be verified the
 * moment it is written. The full Pest suite (which also covers the Eloquent and
 * Filament layers) runs after `composer install`; see tests/.
 *
 *   php bin/domain-tests.php
 */

// --- minimal PSR-4 autoloader: Modules\<Name>\Path => app-modules/<name>/src/Path.php ---
spl_autoload_register(static function (string $class): void {
    if (! str_starts_with($class, 'Modules\\')) {
        return;
    }
    $parts = explode('\\', $class);
    array_shift($parts);                 // drop "Modules"
    $module = strtolower(array_shift($parts));
    $path = __DIR__.'/../app-modules/'.$module.'/src/'.implode('/', $parts).'.php';
    if (is_file($path)) {
        require $path;
    }
});

use Modules\Billing\Domain\ProgressInvoiceFact;
use Modules\Billing\Domain\TerminCalculator;
use Modules\Finance\Domain\Ledger\AccountMap;
use Modules\Finance\Domain\Ledger\JournalDraft;
use Modules\Finance\Domain\Ledger\JournalLineDraft;
use Modules\Finance\Domain\Ledger\LedgerException;
use Modules\Finance\Domain\Ledger\ProgressInvoicePostingRule;
use Modules\Finance\Domain\Ledger\VendorBillPostingRule;
use Modules\Finance\Domain\Psak72Calculator;
use Modules\Payables\Domain\SubcontractBillCalculator;
use Modules\Payables\Domain\VendorBillFact;
use Modules\Platform\Domain\Currency;
use Modules\Platform\Domain\Money;
use Modules\Tax\Domain\PphFinalRateResolver;
use Modules\Tax\Domain\PphFinalRateTable;
use Modules\Tax\Domain\PpnCalculator;
use Modules\Tax\Domain\SbuClass;
use Modules\Tax\Domain\ServiceClass;

// --- tiny test harness -------------------------------------------------------
$passed = 0;
$failed = 0;
$failures = [];

function test(string $name, callable $fn): void
{
    global $passed, $failed, $failures;
    try {
        $fn();
        $passed++;
        fwrite(STDOUT, "  \033[32m✓\033[0m {$name}\n");
    } catch (Throwable $e) {
        $failed++;
        $failures[] = "{$name}: {$e->getMessage()}";
        fwrite(STDOUT, "  \033[31m✗\033[0m {$name}\n      {$e->getMessage()}\n");
    }
}

function assertTrue(bool $cond, string $msg = 'expected true'): void
{
    if (! $cond) {
        throw new RuntimeException($msg);
    }
}

function assertSameInt(int $expected, int $actual, string $ctx = ''): void
{
    if ($expected !== $actual) {
        throw new RuntimeException(trim("{$ctx} expected {$expected}, got {$actual}"));
    }
}

function assertThrows(string $exceptionClass, callable $fn, string $ctx = ''): void
{
    try {
        $fn();
    } catch (Throwable $e) {
        if ($e instanceof $exceptionClass) {
            return;
        }
        throw new RuntimeException("{$ctx} expected {$exceptionClass}, got ".$e::class);
    }
    throw new RuntimeException("{$ctx} expected {$exceptionClass}, nothing thrown");
}

$IDR = Currency::IDR;

// --- Money -------------------------------------------------------------------
fwrite(STDOUT, "\nMoney\n");

test('allocate splits with no rupiah lost or created', function () use ($IDR) {
    $pot = Money::of(1_000_000, $IDR);          // Rp 1.000.000
    $parts = $pot->allocate(['a' => 1, 'b' => 1, 'c' => 1]); // thirds — does not divide evenly
    $sum = $parts['a']->add($parts['b'])->add($parts['c']);
    assertSameInt(1_000_000, $sum->minor, 'reassembled total');
    // largest-remainder: 333334 + 333333 + 333333
    assertSameInt(333_334, $parts['a']->minor, 'first part gets the leftover');
    assertSameInt(333_333, $parts['b']->minor);
    assertSameInt(333_333, $parts['c']->minor);
});

test('applyRate rounds half to even', function () use ($IDR) {
    // 2.5 -> 2 (even), 3.5 -> 4 (even), 0.5 -> 0 (even)
    assertSameInt(2, Money::ofMinor(5, $IDR)->applyRate(1, 2)->minor, '2.5');
    assertSameInt(4, Money::ofMinor(7, $IDR)->applyRate(1, 2)->minor, '3.5');
    assertSameInt(0, Money::ofMinor(1, $IDR)->applyRate(1, 2)->minor, '0.5');
    assertSameInt(2, Money::ofMinor(3, $IDR)->applyRate(1, 2)->minor, '1.5');
});

test('adding different currencies throws', function () use ($IDR) {
    assertThrows(InvalidArgumentException::class, function () use ($IDR) {
        Money::of(1, $IDR)->add(Money::of(1, Currency::USD));
    });
});

// --- PPh final rate resolver -------------------------------------------------
fwrite(STDOUT, "\nPPh final (PP 9/2022 + transitional)\n");

$resolver = new PphFinalRateResolver(PphFinalRateTable::statutory());
$today = '2026-07-04';

test('EPC (integrated) with medium/large SBU is 2.65%', function () use ($resolver, $today) {
    $r = $resolver->resolve(ServiceClass::IntegratedWork, SbuClass::MediumLargeSpecialist, $today);
    assertSameInt(265, $r->rateNumerator, 'rate');
    assertTrue($r->regulationRef === 'PP 9/2022', 'regulation');
});

test('EPC without certificate is 4%', function () use ($resolver, $today) {
    $r = $resolver->resolve(ServiceClass::IntegratedWork, SbuClass::None, $today);
    assertSameInt(400, $r->rateNumerator);
});

test('small-qualification construction work is 1.75%', function () use ($resolver, $today) {
    $r = $resolver->resolve(ServiceClass::ConstructionWork, SbuClass::Small, $today);
    assertSameInt(175, $r->rateNumerator);
});

test('TRANSITIONAL: contract signed 2022-01-01 uses PP 51/2008, not PP 9/2022', function () use ($resolver) {
    // Same EPC/medium classification, but a contract dated before 2022-02-21.
    $old = $resolver->resolve(ServiceClass::IntegratedWork, SbuClass::MediumLargeSpecialist, '2022-01-01');
    assertSameInt(400, $old->rateNumerator, 'old integrated rate is a flat 4%');
    assertTrue(str_starts_with($old->regulationRef, 'PP 51/2008'), 'old regulation ref');
});

// --- Ledger balance invariant ------------------------------------------------
fwrite(STDOUT, "\nDouble-entry invariant\n");

test('a balanced journal is accepted', function () use ($IDR) {
    $j = new JournalDraft('test', [
        JournalLineDraft::debit('1101', Money::of(500, $IDR)),
        JournalLineDraft::credit('4101', Money::of(500, $IDR)),
    ]);
    // IDR is scale 0, so Money::of(500) is 500 minor units (Rp 500).
    assertSameInt(500, $j->total()->minor, 'journal total = sum of debits');
});

test('an unbalanced journal is rejected', function () use ($IDR) {
    assertThrows(LedgerException::class, function () use ($IDR) {
        new JournalDraft('bad', [
            JournalLineDraft::debit('1101', Money::of(500, $IDR)),
            JournalLineDraft::credit('4101', Money::of(499, $IDR)),
        ]);
    }, 'unbalanced');
});

test('a single-line journal is rejected', function () use ($IDR) {
    assertThrows(LedgerException::class, function () use ($IDR) {
        new JournalDraft('bad', [JournalLineDraft::debit('1101', Money::of(500, $IDR))]);
    });
});

test('reverse() negates exactly', function () use ($IDR) {
    $j = new JournalDraft('orig', [
        JournalLineDraft::debit('1101', Money::of(500, $IDR)),
        JournalLineDraft::credit('4101', Money::of(500, $IDR)),
    ]);
    $rev = $j->reverse('koreksi');
    // The original debit line is now a credit for the same amount.
    assertSameInt(0, $rev->lines[0]->debit->minor, 'first line debit now zero');
    assertTrue($rev->lines[0]->credit->equals(Money::of(500, $IDR)), 'first line flipped to credit');
    assertTrue($rev->lines[1]->debit->equals(Money::of(500, $IDR)), 'second line flipped to debit');
});

// --- Termin money path (end to end, pure) ------------------------------------
fwrite(STDOUT, "\nTermin money path\n");

test('worked example ties out and the posted journal balances', function () use ($resolver, $today, $IDR) {
    // Rp 1.000.000 certified work; 20% advance recovery; 5% retensi; 11% PPN;
    // EPC medium SBU -> 2.65% PPh final.
    $rate = $resolver->resolve(ServiceClass::IntegratedWork, SbuClass::MediumLargeSpecialist, $today);
    $calc = new TerminCalculator(new PpnCalculator(11));
    $t = $calc->calculate(Money::of(1_000_000, $IDR), 5, 20, $rate);

    assertSameInt(1_000_000, $t->workValue->minor, 'work value');
    assertSameInt(110_000, $t->ppnOutput->minor, 'PPN 11%');
    assertSameInt(50_000, $t->retention->minor, 'retensi 5%');
    assertSameInt(200_000, $t->uangMukaRecovery->minor, 'uang muka 20%');
    assertSameInt(26_500, $t->pphFinal->minor, 'PPh final 2.65%');
    assertSameInt(833_500, $t->netReceivable->minor, 'net receivable');

    // Map the fact to a journal via the Finance posting rule and confirm it balances.
    $fact = ProgressInvoiceFact::fromTermin('CLAIM-001', 'PRJ-001', $t);
    $accounts = new AccountMap([
        'accounts_receivable' => '1102',
        'retention_receivable' => '1103',
        'advance_liability' => '2103',
        'pph_final_prepaid' => '1181',
        'contract_revenue' => '4101',
        'ppn_output' => '2151',
    ]);
    $journal = (new ProgressInvoicePostingRule)->toJournal($fact->toPayload(), $accounts);

    $debits = 0;
    $credits = 0;
    foreach ($journal->lines as $line) {
        $debits += $line->debit->minor;
        $credits += $line->credit->minor;
    }
    assertSameInt($credits, $debits, 'journal balances');
    assertSameInt(1_110_000, $debits, 'total movement = work + PPN');
});

// --- Subcontract bill money path (procure-to-pay, pure) ----------------------
fwrite(STDOUT, "\nSubcontract bill money path\n");

test('subcontractor bill ties out and its accrual journal balances', function () use ($resolver, $today, $IDR) {
    // Rp 500.000 certified subcontract work; 5% retensi; 11% PPN (vendor is PKP);
    // subcontractor holds a small (kecil) SBU doing pelaksanaan -> 1.75% PPh final.
    $rate = $resolver->resolve(ServiceClass::ConstructionWork, SbuClass::Small, $today);
    $calc = new SubcontractBillCalculator(new PpnCalculator(11));
    $b = $calc->calculate(Money::of(500_000, $IDR), 5, $rate, vendorIsPkp: true);

    assertSameInt(500_000, $b->workValue->minor, 'work value');
    assertSameInt(55_000, $b->ppnInput->minor, 'PPN input 11%');
    assertSameInt(25_000, $b->retention->minor, 'retensi 5%');
    assertSameInt(8_750, $b->pphFinal->minor, 'PPh final 1.75%');
    assertSameInt(521_250, $b->netPayable->minor, 'net payable = 500k + 55k − 25k − 8.75k');

    // Map the fact to a journal via the Finance posting rule and confirm it balances.
    $fact = VendorBillFact::fromResult('BILL-001', 'PRJ-001', null, 'SUB', $b);
    $accounts = new AccountMap([
        'subcontract_cost' => '5101',
        'ppn_input' => '1152',
        'accounts_payable' => '2101',
        'retention_payable' => '2104',
        'pph_final_payable' => '2131',
    ]);
    $journal = (new VendorBillPostingRule)->toJournal($fact->toPayload(), $accounts);

    $debits = 0;
    $credits = 0;
    foreach ($journal->lines as $line) {
        $debits += $line->debit->minor;
        $credits += $line->credit->minor;
    }
    assertSameInt($credits, $debits, 'accrual journal balances');
    assertSameInt(555_000, $debits, 'total movement = work + PPN');
});

test('a non-PKP vendor bill drops the input-VAT line and still balances', function () use ($resolver, $today, $IDR) {
    $rate = $resolver->resolve(ServiceClass::ConstructionWork, SbuClass::Small, $today);
    $calc = new SubcontractBillCalculator(new PpnCalculator(11));
    $b = $calc->calculate(Money::of(500_000, $IDR), 5, $rate, vendorIsPkp: false);

    assertSameInt(0, $b->ppnInput->minor, 'no creditable input VAT for a non-PKP vendor');
    assertSameInt(466_250, $b->netPayable->minor, 'net = 500k − 25k − 8.75k');

    $fact = VendorBillFact::fromResult('BILL-002', null, null, 'SUB', $b);
    $accounts = new AccountMap([
        'subcontract_cost' => '5101',
        'ppn_input' => '1152',
        'accounts_payable' => '2101',
        'retention_payable' => '2104',
        'pph_final_payable' => '2131',
    ]);
    $journal = (new VendorBillPostingRule)->toJournal($fact->toPayload(), $accounts);

    $debits = 0;
    $credits = 0;
    foreach ($journal->lines as $line) {
        $debits += $line->debit->minor;
        $credits += $line->credit->minor;
    }
    assertSameInt($credits, $debits, 'journal still balances without input VAT');
    assertSameInt(500_000, $debits, 'total movement = work only');
    // The PPN Masukan line must not appear at all.
    foreach ($journal->lines as $line) {
        assertTrue($line->accountCode !== '1152', 'no zero PPN Masukan line');
    }
});

// --- PSAK 72 revenue recognition ---------------------------------------------
fwrite(STDOUT, "\nPSAK 72 (percentage of completion)\n");

test('cost-to-cost POC and contract asset when recognized exceeds billed', function () use ($IDR) {
    $calc = new Psak72Calculator;
    $contract = Money::of(10_000_000_000, $IDR);
    $poc = $calc->pocRatioPpm(Money::of(2_000_000_000, $IDR), Money::of(8_000_000_000, $IDR));
    assertSameInt(250_000, $poc, 'POC ppm = 25%');

    $r = $calc->recognize($contract, $poc, Money::zero($IDR), Money::of(2_000_000_000, $IDR));
    assertSameInt(2_500_000_000, $r->recognizedToDate->minor, 'recognized to date');
    assertSameInt(2_500_000_000, $r->periodRecognition->minor, 'period recognition');
    assertSameInt(500_000_000, $r->contractAsset->minor, 'unbilled contract asset');
    assertSameInt(0, $r->contractLiability->minor, 'no liability');
});

test('contract liability when billing runs ahead of recognition', function () use ($IDR) {
    $calc = new Psak72Calculator;
    $r = $calc->recognize(Money::of(10_000_000_000, $IDR), 250_000, Money::zero($IDR), Money::of(3_000_000_000, $IDR));
    assertSameInt(0, $r->contractAsset->minor, 'no asset');
    assertSameInt(500_000_000, $r->contractLiability->minor, 'advance-billing liability');
});

// --- summary -----------------------------------------------------------------
fwrite(STDOUT, "\n".str_repeat('─', 48)."\n");
fwrite(STDOUT, sprintf("  %d passed, %d failed\n", $passed, $failed));
if ($failed > 0) {
    fwrite(STDOUT, "\nFAILURES:\n");
    foreach ($failures as $f) {
        fwrite(STDOUT, "  - {$f}\n");
    }
    exit(1);
}
fwrite(STDOUT, "  \033[32mall green\033[0m\n");
exit(0);
