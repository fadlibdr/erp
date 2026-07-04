<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;

/**
 * GARIS S-curve: plan (dashed grey) vs realisation (solid Biru Baja) with the
 * deviation drawn explicitly in Amber — the chart must never hide a slip. The SVG
 * geometry is computed from the data arrays, so this is a real reusable widget;
 * wiring live opname/progress time-series into it is a follow-on.
 */
final class SCurveWidget extends Widget
{
    protected static string $view = 'filament.widgets.s-curve';

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = 2;

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $months = ['Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu'];
        $plan = [2, 12, 28, 46, 65.5, 88, 100];
        $actual = [2, 10, 26, 45, 62.4]; // realised to date

        $x0 = 40;
        $x1 = 620;
        $y0 = 185;
        $y1 = 12;
        $n = count($months);
        $px = fn (int $i): float => round($x0 + ($x1 - $x0) * $i / ($n - 1), 1);
        $py = fn (float $p): float => round($y0 - ($y0 - $y1) * $p / 100, 1);

        $lastI = count($actual) - 1;
        $actualLast = $actual[$lastI];

        return [
            'planPts' => implode(' ', array_map(fn ($p, $i) => $px($i).','.$py($p), $plan, array_keys($plan))),
            'actPts' => implode(' ', array_map(fn ($p, $i) => $px($i).','.$py($p), $actual, array_keys($actual))),
            'mx' => $px($lastI),
            'my' => $py($actualLast),
            'devTopY' => $py($plan[$lastI]),
            'labels' => array_map(fn ($m, $i) => ['x' => $px($i), 'm' => $m], $months, array_keys($months)),
            'realisasi' => number_format($actualLast, 1, ',', '.'),
            'deviasi' => '−3,1',
        ];
    }
}
