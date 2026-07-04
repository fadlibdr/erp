<?php

declare(strict_types=1);

namespace Modules\Platform\Support;

use Illuminate\Support\Facades\DB;
use Modules\Platform\Models\NumberingSeries;
use RuntimeException;

/**
 * Allocates the next gap-free document number for a company + series.
 *
 * Correctness rests on a row lock: the series row is read `lockForUpdate()`
 * inside a transaction, so two requests issuing an invoice at the same instant
 * serialise on that row and can never be handed the same number. The counter
 * optionally resets each year/month (period_scope), which matches how Indonesian
 * document numbering and the tax invoice serial ranges roll over.
 *
 * Format tokens: {YYYY} {YY} {MM} and a run of {#} for the zero-padded counter,
 * e.g. "INV-{YYYY}-{####}" -> "INV-2026-0042".
 */
final class NumberingService
{
    public function next(string $companyId, string $key): string
    {
        return DB::transaction(function () use ($companyId, $key) {
            /** @var NumberingSeries|null $series */
            $series = NumberingSeries::query()
                ->where('company_id', $companyId)
                ->where('key', $key)
                ->lockForUpdate()
                ->first();

            if ($series === null) {
                throw new RuntimeException("Numbering series '{$key}' is not configured for this company.");
            }

            $period = $this->periodFor($series->period_scope);

            if ($series->period_scope !== 'none' && $series->current_period !== $period) {
                $series->current_period = $period;
                $series->next = 1;
            }

            $value = $series->next;
            $series->next = $value + 1;
            $series->save();

            return $this->render($series->format, $value);
        });
    }

    private function periodFor(string $scope): ?string
    {
        return match ($scope) {
            'year' => date('Y'),
            'month' => date('Y-m'),
            default => null,
        };
    }

    private function render(string $format, int $value): string
    {
        $out = str_replace(
            ['{YYYY}', '{YY}', '{MM}'],
            [date('Y'), date('y'), date('m')],
            $format,
        );

        // Replace a braced run of '#' (e.g. "{####}") with the zero-padded counter,
        // consuming the braces so "INV-{YYYY}-{####}" renders "INV-2026-0042".
        return preg_replace_callback('/\{#+\}/', static function (array $m) use ($value): string {
            return str_pad((string) $value, strlen($m[0]) - 2, '0', STR_PAD_LEFT);
        }, $out) ?? $out;
    }
}
