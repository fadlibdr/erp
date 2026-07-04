<?php

declare(strict_types=1);

namespace Modules\Tax\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Tax\Domain\PphFinalRateResolver;
use Modules\Tax\Services\PphFinalRateRepository;

final class TaxServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PphFinalRateRepository::class);
        $this->app->bind(PphFinalRateResolver::class, fn ($app) => $app->make(PphFinalRateRepository::class)->resolver());
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
    }
}
