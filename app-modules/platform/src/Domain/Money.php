<?php

declare(strict_types=1);

namespace Modules\Platform\Domain;

use InvalidArgumentException;

/**
 * An immutable money value object holding an integer amount in the currency's
 * minor unit. There are no floats anywhere in this class — every operation is
 * integer arithmetic, and every division rounds half-to-even (banker's rounding)
 * so long chains of tax/retention splits do not drift.
 *
 * This is deliberately framework-agnostic (no Illuminate imports) so the ledger
 * and tax logic that depend on it can be unit-tested with plain PHP.
 */
final class Money
{
    private function __construct(
        public readonly int $minor,
        public readonly Currency $currency,
    ) {
    }

    /** Build from an integer amount already expressed in minor units. */
    public static function ofMinor(int $minor, Currency $currency): self
    {
        return new self($minor, $currency);
    }

    /** Build from a whole-major amount, e.g. Money::of(1_000_000, IDR) = Rp 1.000.000. */
    public static function of(int $major, Currency $currency): self
    {
        return new self($major * $currency->subunits(), $currency);
    }

    public static function zero(Currency $currency): self
    {
        return new self(0, $currency);
    }

    public function isZero(): bool
    {
        return $this->minor === 0;
    }

    public function isNegative(): bool
    {
        return $this->minor < 0;
    }

    public function add(Money $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->minor + $other->minor, $this->currency);
    }

    public function subtract(Money $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->minor - $other->minor, $this->currency);
    }

    public function negate(): self
    {
        return new self(-$this->minor, $this->currency);
    }

    public function multiply(int $factor): self
    {
        return new self($this->minor * $factor, $this->currency);
    }

    /**
     * Apply a rational rate (numerator/denominator) with banker's rounding.
     * Used for every statutory percentage — PPN, PPh final, retensi, uang muka —
     * as exact integers, e.g. 2.65% is applyRate(265, 10_000), 11% is applyRate(11, 100).
     */
    public function applyRate(int $numerator, int $denominator): self
    {
        if ($denominator === 0) {
            throw new InvalidArgumentException('Rate denominator cannot be zero.');
        }

        return new self(
            self::divideHalfEven($this->minor * $numerator, $denominator),
            $this->currency,
        );
    }

    /**
     * Split this amount across integer weights with no rupiah lost or created —
     * the total of the parts always equals the whole. Remainders are distributed
     * largest-remainder first. This is how a termin's retention or a shared cost
     * is apportioned across BOQ/WBS lines without a rounding leak.
     *
     * @param  array<int|string, int>  $weights  positive integer weights keyed however you like
     * @return array<int|string, Money> parts keyed the same as $weights
     */
    public function allocate(array $weights): array
    {
        $total = array_sum($weights);
        if ($total <= 0) {
            throw new InvalidArgumentException('Allocation weights must sum to a positive value.');
        }

        $parts = [];
        $remainders = [];
        $allocated = 0;

        foreach ($weights as $key => $weight) {
            if ($weight < 0) {
                throw new InvalidArgumentException('Allocation weights cannot be negative.');
            }
            $numerator = $this->minor * $weight;
            $base = intdiv($numerator, $total);
            $parts[$key] = $base;
            $remainders[$key] = $numerator - ($base * $total);
            $allocated += $base;
        }

        $leftover = $this->minor - $allocated; // whole minor units still to hand out
        if ($leftover !== 0) {
            $step = $leftover <=> 0; // +1 or -1
            // Hand the leftover units to the largest remainders first (deterministic tie-break by key).
            uksort($remainders, function ($a, $b) use ($remainders) {
                return $remainders[$b] <=> $remainders[$a] ?: ($a <=> $b);
            });
            foreach (array_keys($remainders) as $key) {
                if ($leftover === 0) {
                    break;
                }
                $parts[$key] += $step;
                $leftover -= $step;
            }
        }

        return array_map(fn (int $m) => new self($m, $this->currency), $parts);
    }

    public function equals(Money $other): bool
    {
        return $this->minor === $other->minor && $this->currency === $other->currency;
    }

    public function compareTo(Money $other): int
    {
        $this->assertSameCurrency($other);

        return $this->minor <=> $other->minor;
    }

    /** Human string for prints/debug, e.g. "IDR 1.000.000" or "USD 1,234.50". */
    public function format(): string
    {
        $scale = $this->currency->scale();
        $sign = $this->minor < 0 ? '-' : '';
        $abs = abs($this->minor);
        $sub = $this->currency->subunits();
        $major = intdiv($abs, $sub);
        $majorStr = number_format($major, 0, '.', $this->currency === Currency::IDR ? '.' : ',');

        if ($scale === 0) {
            return sprintf('%s %s%s', $this->currency->value, $sign, $majorStr);
        }

        $minorPart = str_pad((string) ($abs % $sub), $scale, '0', STR_PAD_LEFT);

        return sprintf('%s %s%s.%s', $this->currency->value, $sign, $majorStr, $minorPart);
    }

    private function assertSameCurrency(Money $other): void
    {
        if ($this->currency !== $other->currency) {
            throw new InvalidArgumentException(sprintf(
                'Currency mismatch: %s vs %s.',
                $this->currency->value,
                $other->currency->value,
            ));
        }
    }

    /** Integer division rounding half to even, correct for negative numerators. */
    private static function divideHalfEven(int $numerator, int $denominator): int
    {
        if ($denominator < 0) {
            $numerator = -$numerator;
            $denominator = -$denominator;
        }

        $q = intdiv($numerator, $denominator);
        $r = $numerator - ($q * $denominator); // sign follows $numerator

        if ($r === 0) {
            return $q;
        }

        // Work with magnitudes, then re-apply sign.
        $sign = $numerator < 0 ? -1 : 1;
        $absR = abs($r);
        $twice = $absR * 2;

        if ($twice > $denominator) {
            $q += $sign;
        } elseif ($twice === $denominator) {
            // Round to even.
            if ($q % 2 !== 0) {
                $q += $sign;
            }
        }

        return $q;
    }
}
