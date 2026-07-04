<?php

declare(strict_types=1);

namespace App\Providers;

use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;

final class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Fail loud in non-production: unguarded attributes, lazy loading, and
        // accessing missing attributes all throw. An ERP ledger should never
        // silently swallow a typo'd column.
        Model::shouldBeStrict(! $this->app->isProduction());

        // GARIS design-system layer, injected into every Filament page head. The
        // palette/font are set on the panel; this stylesheet carries the rest
        // (three-font system, border-first surfaces, mono numbers, hazard stripe).
        FilamentView::registerRenderHook(
            PanelsRenderHook::HEAD_END,
            fn (): string => Blade::render('<link rel="stylesheet" href="{{ asset(\'css/garis.css\') }}?v=1">'),
        );
    }
}
