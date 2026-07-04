<?php

declare(strict_types=1);

namespace Modules\Billing\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Billing\Domain\TerminCalculator;
use Modules\Tax\Domain\PpnCalculator;

final class BillingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Default output-VAT rate is configurable per install (11% today, 12% pending).
        $this->app->bind(TerminCalculator::class, fn ($app) => new TerminCalculator(
            new PpnCalculator((int) config('karya.ppn_rate_percent', 11)),
        ));
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
    }
}
