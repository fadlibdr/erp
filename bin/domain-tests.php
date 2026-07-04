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
use Modules\Finance\Domain\BudgetControlPolicy;
use Modules\Finance\Domain\BudgetVerdict;
use Modules\Finance\Domain\Ledger\AccountMap;
use Modules\Finance\Domain\Ledger\GrnPostingRule;
use Modules\Finance\Domain\Ledger\JournalDraft;
use Modules\Finance\Domain\Ledger\JournalLineDraft;
use Modules\Finance\Domain\Ledger\LedgerException;
use Modules\Finance\Domain\Ledger\CustomerReceiptPostingRule;
use Modules\Finance\Domain\Ledger\MaterialBillPostingRule;
use Modules\Finance\Domain\Ledger\MaterialIssuePostingRule;
use Modules\Finance\Domain\Ledger\PayrollPostingRule;
use Modules\Finance\Domain\Ledger\ProgressInvoicePostingRule;
use Modules\Finance\Domain\Ledger\RetentionReleasePostingRule;
use Modules\Finance\Domain\Ledger\VendorPaymentPostingRule;
use Modules\Finance\Domain\Ledger\Psak72PostingRule;
use Modules\Finance\Domain\Ledger\VendorBillPostingRule;
use Modules\Finance\Domain\Psak72Calculator;
use Modules\Inventory\Domain\MovingAverageValuation;
use Modules\Inventory\Domain\StockBalance;
use Modules\Payables\Domain\MaterialBillCalculator;
use Modules\Payables\Domain\SubcontractBillCalculator;
use Modules\Payables\Domain\VendorBillFact;
use Modules\Payroll\Domain\BpjsCalculator;
use Modules\Payroll\Domain\PayrollCalculator;
use Modules\Platform\Domain\Currency;
use Modules\Platform\Domain\Money;
use Modules\Procurement\Domain\MatchVerdict;
use Modules\Procurement\Domain\ThreeWayMatch;
use Modules\Tax\Domain\EfakturInvoice;
use Modules\Tax\Domain\EfakturSubmissionStatus;
use Modules\Tax\Domain\EfakturXmlBuilder;
use Modules\Tax\Domain\PphFinalRateResolver;
use Modules\Tax\Domain\PphFinalRateTable;
use Modules\Tax\Domain\PpnCalculator;
use Modules\Tax\Domain\Pph21TerCalculator;
use Modules\Tax\Domain\Pph21TerTable;
use Modules\Tax\Domain\PtkpStatus;
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

test('PSAK 72 close true-up posts a balanced asset (under-billed) and liability (over-billed) entry', function () use ($IDR) {
    $accounts = new AccountMap([
        'contract_asset' => '1171',
        'contract_liability' => '2181',
        'contract_revenue' => '4101',
    ]);
    $rule = new Psak72PostingRule;

    // Under-billed: recognised Rp 500.000 more than billed this period → asset.
    $asset = $rule->toJournal(['project_id' => 'PRJ-1', 'currency' => $IDR->value, 'true_up' => 500_000], $accounts);
    $d = 0;
    $c = 0;
    foreach ($asset->lines as $l) {
        $d += $l->debit->minor;
        $c += $l->credit->minor;
    }
    assertSameInt($c, $d, 'asset entry balances');
    assertSameInt(500_000, $d, 'asset movement');
    assertTrue($asset->lines[0]->accountCode === '1171' && ! $asset->lines[0]->debit->isZero(), 'debits the contract asset');

    // Over-billed: billed Rp 300.000 more than recognised → liability, revenue debited.
    $liab = $rule->toJournal(['project_id' => 'PRJ-1', 'currency' => $IDR->value, 'true_up' => -300_000], $accounts);
    $d = 0;
    $c = 0;
    foreach ($liab->lines as $l) {
        $d += $l->debit->minor;
        $c += $l->credit->minor;
    }
    assertSameInt($c, $d, 'liability entry balances');
    assertSameInt(300_000, $d, 'liability movement');
    assertTrue($liab->lines[1]->accountCode === '2181' && ! $liab->lines[1]->credit->isZero(), 'credits the contract liability');
});

// --- Cost-control commitment loop (Pass 3, leg A) ----------------------------
fwrite(STDOUT, "\nCost control (budget / GRN / stock / 3-way match)\n");

test('budget policy: OK, WARN at the soft threshold, BLOCK past the ceiling', function () {
    $policy = new BudgetControlPolicy;
    // Budget 1.000.000; committed 500.000, actual 200.000 (consumed 700.000).
    $ok = $policy->decide(1_000_000, 500_000, 200_000, 100_000);     // projected 800k (<90%)
    $warn = $policy->decide(1_000_000, 500_000, 200_000, 200_000);   // projected 900k (=90%)
    $block = $policy->decide(1_000_000, 500_000, 200_000, 400_000);  // projected 1.1M (>100%)

    assertTrue($ok->verdict === BudgetVerdict::Ok, 'within threshold is OK');
    assertSameInt(300_000, $ok->availableMinor, 'available = budget − committed − actual');
    assertTrue($warn->verdict === BudgetVerdict::Warn, 'at 90% is WARN');
    assertTrue($block->verdict === BudgetVerdict::Block, 'over ceiling is BLOCK');
    assertSameInt(100_000, $block->overspendMinor, 'overspend amount');
});

test('GRN posting rule books a balanced Inventory/WIP ↔ GR/IR accrual', function () use ($IDR) {
    $accounts = new AccountMap([
        'inventory_wip' => '1141',
        'gr_ir_accrual' => '2109',
    ]);
    $payload = [
        'grn_id' => 'GRN-001',
        'project_id' => 'PRJ-001',
        'wbs_id' => 'WBS-1',
        'cost_code' => 'MAT',
        'currency' => $IDR->value,
        'amount' => 750_000,
    ];
    $journal = (new GrnPostingRule)->toJournal($payload, $accounts);

    $debits = 0;
    $credits = 0;
    foreach ($journal->lines as $line) {
        $debits += $line->debit->minor;
        $credits += $line->credit->minor;
    }
    assertSameInt($credits, $debits, 'accrual journal balances');
    assertSameInt(750_000, $debits, 'movement = goods received value');
});

test('moving-average: receipts blend, an issue leaves at the running average', function () use ($IDR) {
    $mav = new MovingAverageValuation;
    $bal = StockBalance::opening(Money::zero($IDR));
    // Receive 100 units @ Rp 1.000 = 100.000, then 100 units @ Rp 3.000 = 300.000.
    $bal = $mav->receive($bal, 100_000, Money::of(100_000, $IDR)); // qty in milli: 100.000 = 100 units
    $bal = $mav->receive($bal, 100_000, Money::of(300_000, $IDR));
    assertSameInt(200_000, $bal->qtyMilli, '200 units on hand');
    assertSameInt(400_000, $bal->value->minor, 'pooled value 400.000');

    // Issue 50 units → 50/200 of 400.000 = 100.000 at the Rp 2.000 average.
    $issue = $mav->issue($bal, 50_000);
    assertSameInt(100_000, $issue->issuedValue->minor, 'issued value at moving average');
    assertSameInt(150_000, $issue->remaining->qtyMilli, '150 units remain');
    assertSameInt(300_000, $issue->remaining->value->minor, 'remaining value');

    // Issuing the rest takes exactly the remaining value — no rounding dust.
    $rest = $mav->issue($issue->remaining, 150_000);
    assertSameInt(300_000, $rest->issuedValue->minor, 'final issue drains the pool exactly');
    assertSameInt(0, $rest->remaining->qtyMilli, 'empty');
    assertSameInt(0, $rest->remaining->value->minor, 'zero value left');
});

test('three-way match: clean, quantity variance, price variance', function () {
    $m = new ThreeWayMatch;
    // Ordered 100 units for 1.000.000; received 100; billed 1.005.000 (0.5% < 1% tol).
    assertTrue($m->match(100_000, 100_000, 1_000_000, 1_005_000) === MatchVerdict::Matched, 'within tolerance');
    // Received only 90 units → quantity variance (default qty tolerance is zero).
    assertTrue($m->match(100_000, 90_000, 1_000_000, 1_000_000) === MatchVerdict::QtyVariance, 'short delivery');
    // Billed 1.100.000 (10% > 1% tol) → price variance.
    assertTrue($m->match(100_000, 100_000, 1_000_000, 1_100_000) === MatchVerdict::PriceVariance, 'overbilled');
});

// --- Material bill / GR-IR clearing (Pass 4 — commitment loop closed) ---------
fwrite(STDOUT, "\nMaterial bill (GR/IR clearing)\n");

test('material bill has no PPh withholding and clears via a balanced GR/IR entry', function () use ($IDR) {
    // Rp 800.000 of goods, vendor is PKP → 11% PPN input, no retention, no PPh.
    $calc = new MaterialBillCalculator(new PpnCalculator(11));
    $b = $calc->calculate(Money::of(800_000, $IDR), 0, vendorIsPkp: true);

    assertSameInt(800_000, $b->workValue->minor, 'work value');
    assertSameInt(88_000, $b->ppnInput->minor, 'PPN input 11%');
    assertSameInt(0, $b->retention->minor, 'no retention on goods');
    assertSameInt(888_000, $b->netPayable->minor, 'net = work + PPN (no PPh)');

    // The bill clears GR/IR rather than re-booking cost: Dr GR/IR + Dr PPN / Cr AP.
    $accounts = new AccountMap([
        'gr_ir_accrual' => '2109',
        'ppn_input' => '1152',
        'accounts_payable' => '2101',
        'retention_payable' => '2104',
    ]);
    $payload = [
        'bill_id' => 'MAT-BILL-001',
        'project_id' => 'PRJ-001',
        'currency' => $IDR->value,
        'work_value' => $b->workValue->minor,
        'ppn_input' => $b->ppnInput->minor,
        'net_payable' => $b->netPayable->minor,
        'retention' => $b->retention->minor,
    ];
    $journal = (new MaterialBillPostingRule)->toJournal($payload, $accounts);

    $debits = 0;
    $credits = 0;
    foreach ($journal->lines as $line) {
        $debits += $line->debit->minor;
        $credits += $line->credit->minor;
    }
    assertSameInt($credits, $debits, 'material bill journal balances');
    assertSameInt(888_000, $debits, 'movement = work + PPN');

    // The debit that clears the accrual must hit GR/IR (2109), not a fresh cost account.
    $grIr = null;
    foreach ($journal->lines as $line) {
        if ($line->accountCode === '2109') {
            $grIr = $line;
        }
    }
    assertTrue($grIr !== null && $grIr->debit->minor === 800_000, 'GR/IR debited by the goods value');
    // No retention line when retention is zero.
    foreach ($journal->lines as $line) {
        assertTrue($line->accountCode !== '2104', 'no zero retention line');
    }
});

test('material issue posts a balanced project-cost entry (Dr cost / Cr inventory)', function () use ($IDR) {
    $accounts = new AccountMap(['project_material_cost' => '5102', 'inventory' => '1301']);
    $payload = [
        'issue_id' => 'ISS-1', 'project_id' => 'PRJ-1', 'wbs_id' => 'W1', 'cost_code' => 'MAT',
        'currency' => $IDR->value, 'amount' => 350_000,
    ];
    $journal = (new MaterialIssuePostingRule)->toJournal($payload, $accounts);

    $debits = 0;
    $credits = 0;
    foreach ($journal->lines as $line) {
        $debits += $line->debit->minor;
        $credits += $line->credit->minor;
    }
    assertSameInt($credits, $debits, 'material issue journal balances');
    assertSameInt(350_000, $debits, 'movement = issued value');
    assertTrue($journal->lines[0]->accountCode === '5102' && ! $journal->lines[0]->debit->isZero(), 'debits project material cost');
    assertTrue($journal->lines[1]->accountCode === '1301' && ! $journal->lines[1]->credit->isZero(), 'credits inventory');
});

// --- Settlement (Pass 5B — cash leg of both money paths) ---------------------
fwrite(STDOUT, "\nSettlement (AP payment / AR receipt / retention release)\n");

test('settlement posting rules each book a balanced two-line cash entry', function () use ($IDR) {
    $c = $IDR->value;

    // AP payment: Dr Accounts Payable / Cr Bank.
    $pay = (new VendorPaymentPostingRule)->toJournal(
        ['batch_id' => 'PB-1', 'currency' => $c, 'amount' => 888_000],
        new AccountMap(['accounts_payable' => '2101', 'bank' => '1101']),
    );
    assertSameInt(888_000, $pay->lines[0]->debit->minor, 'AP debited');
    assertTrue($pay->lines[0]->accountCode === '2101' && $pay->lines[1]->accountCode === '1101', 'Dr AP / Cr Bank');

    // AR receipt: Dr Bank / Cr Accounts Receivable.
    $rcpt = (new CustomerReceiptPostingRule)->toJournal(
        ['receipt_id' => 'RC-1', 'project_id' => 'PRJ-1', 'currency' => $c, 'amount' => 833_500],
        new AccountMap(['bank' => '1101', 'accounts_receivable' => '1102']),
    );
    assertTrue($rcpt->lines[0]->accountCode === '1101' && $rcpt->lines[1]->accountCode === '1102', 'Dr Bank / Cr AR');

    // Retention release: Dr Bank / Cr Retention Receivable.
    $rel = (new RetentionReleasePostingRule)->toJournal(
        ['retention_id' => 'RT-1', 'project_id' => 'PRJ-1', 'currency' => $c, 'amount' => 50_000],
        new AccountMap(['bank' => '1101', 'retention_receivable' => '1103']),
    );
    assertTrue($rel->lines[0]->accountCode === '1101' && $rel->lines[1]->accountCode === '1103', 'Dr Bank / Cr Retention');

    foreach ([$pay, $rcpt, $rel] as $j) {
        $d = 0;
        $cr = 0;
        foreach ($j->lines as $l) {
            $d += $l->debit->minor;
            $cr += $l->credit->minor;
        }
        assertSameInt($cr, $d, 'settlement journal balances');
    }
});

// --- e-Faktur (Pass 3, leg C) ------------------------------------------------
fwrite(STDOUT, "\ne-Faktur (Coretax XML + submission status)\n");

test('e-Faktur XML carries the parties, serial and self-checked DPP/PPN totals', function () {
    $invoice = new EfakturInvoice(
        sellerTaxNumber: '0012345678901000',
        buyerTaxNumber: '0098765432101000',
        buyerName: 'PT Karya & Mitra',                 // ampersand must be escaped
        taxInvoiceNumber: '010.000-26.00000001',
        taxInvoiceDate: '2026-07-04',
        transactionCode: '04',
        isReplacement: false,
        referenceNumber: 'CLAIM-001',
        lines: [
            ['name' => 'Pekerjaan konstruksi termin 1', 'kind' => 'J', 'priceMinor' => 1_000_000, 'dppMinor' => 1_000_000, 'ppnMinor' => 110_000],
        ],
    );
    $xml = (new EfakturXmlBuilder)->build($invoice);

    assertTrue(str_contains($xml, '<SellerTaxNumber>0012345678901000</SellerTaxNumber>'), 'seller NPWP');
    assertTrue(str_contains($xml, '<BuyerTaxNumber>0098765432101000</BuyerTaxNumber>'), 'buyer NPWP');
    assertTrue(str_contains($xml, '<TaxInvoiceNumber>010.000-26.00000001</TaxInvoiceNumber>'), 'NSFP serial');
    assertTrue(str_contains($xml, 'PT Karya &amp; Mitra'), 'XML-escaped buyer name');
    assertTrue(str_contains($xml, '<SumDPP>1000000</SumDPP>'), 'DPP total');
    assertTrue(str_contains($xml, '<SumPPN>110000</SumPPN>'), 'PPN total');
    assertTrue(str_contains($xml, '<TaxInvoiceOpt>Normal</TaxInvoiceOpt>'), 'not a replacement');
});

test('e-Faktur status guard allows the happy path and refuses illegal jumps', function () {
    assertTrue(EfakturSubmissionStatus::Queued->canTransitionTo(EfakturSubmissionStatus::Sent), 'queued → sent');
    assertTrue(EfakturSubmissionStatus::Sent->canTransitionTo(EfakturSubmissionStatus::Acked), 'sent → acked');
    assertTrue(EfakturSubmissionStatus::Failed->canTransitionTo(EfakturSubmissionStatus::Sent), 'failed retries');
    assertTrue(EfakturSubmissionStatus::Acked->isTerminal(), 'acked is terminal');

    assertThrows(RuntimeException::class, function () {
        EfakturSubmissionStatus::Queued->transitionTo(EfakturSubmissionStatus::Acked);
    }, 'cannot ack an unsent invoice');
    assertThrows(RuntimeException::class, function () {
        EfakturSubmissionStatus::Acked->transitionTo(EfakturSubmissionStatus::Failed);
    }, 'cannot fail an acked invoice');
});

// --- Payroll (Pass 5D — PPh 21 TER + BPJS crown jewels) ----------------------
fwrite(STDOUT, "\nPayroll (PPh 21 TER + BPJS + labor posting)\n");

test('PPh 21 monthly TER withholds by category (PMK 168/2023)', function () use ($IDR) {
    $calc = new Pph21TerCalculator(Pph21TerTable::statutory());
    // Category A (K/0), gross Rp 10.000.000 → 9,65jt–10,05jt bracket = 2%.
    $a = $calc->monthlyWithholding(Money::of(10_000_000, $IDR), PtkpStatus::K0);
    assertSameInt(200_000, $a->minor, 'category A 2%');
    // Category B (TK/2), gross Rp 8.000.000 → 7,3jt–9,2jt bracket = 1%.
    $b = $calc->monthlyWithholding(Money::of(8_000_000, $IDR), PtkpStatus::TK2);
    assertSameInt(80_000, $b->minor, 'category B 1%');
    // Below the category-A threshold there is no withholding.
    $zero = $calc->monthlyWithholding(Money::of(5_000_000, $IDR), PtkpStatus::TK0);
    assertSameInt(0, $zero->minor, 'under PTKP threshold = 0');
});

test('BPJS splits employee and employer shares on gross', function () use ($IDR) {
    // Gross Rp 8.000.000, below the JP and Kesehatan ceilings.
    $b = (new BpjsCalculator)->on(Money::of(8_000_000, $IDR));
    assertSameInt(320_000, $b->employee->minor, 'employee = JHT2% + JP1% + Kes1%');
    assertSameInt(819_200, $b->employer->minor, 'employer = JHT3.7 + JP2 + JKK.24 + JKM.3 + Kes4');
    assertSameInt(1_139_200, $b->total()->minor, 'total remitted to BPJS');
});

test('payroll run decomposes and posts a balanced labor journal', function () use ($IDR) {
    $calc = new PayrollCalculator(new Pph21TerCalculator(Pph21TerTable::statutory()), new BpjsCalculator);
    // Gross Rp 8.000.000, TK/0 (category A) → 1.5% PPh 21 = 120.000.
    $r = $calc->calculate(Money::of(8_000_000, $IDR), PtkpStatus::TK0);
    assertSameInt(120_000, $r->pph21->minor, 'PPh 21 1.5%');
    assertSameInt(320_000, $r->bpjsEmployee->minor, 'BPJS employee');
    assertSameInt(7_560_000, $r->net->minor, 'net = gross − PPh21 − BPJS employee');

    $accounts = new AccountMap([
        'labor_cost' => '5103', 'bpjs_expense' => '5104',
        'salaries_payable' => '2141', 'pph21_payable' => '2142', 'bpjs_payable' => '2143',
    ]);
    $payload = [
        'run_id' => 'PR-1', 'project_id' => 'PRJ-1', 'wbs_id' => 'W1', 'cost_code' => 'LAB',
        'currency' => $IDR->value, 'labor' => 8_000_000, 'pph21' => 120_000,
        'bpjs_employee' => 320_000, 'bpjs_employer' => 819_200, 'net' => 7_560_000,
    ];
    $journal = (new PayrollPostingRule)->toJournal($payload, $accounts);

    $debits = 0;
    $credits = 0;
    foreach ($journal->lines as $line) {
        $debits += $line->debit->minor;
        $credits += $line->credit->minor;
    }
    assertSameInt($credits, $debits, 'payroll journal balances');
    assertSameInt(8_819_200, $debits, 'movement = gross + employer BPJS');
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
