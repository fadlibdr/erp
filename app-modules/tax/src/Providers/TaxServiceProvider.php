<?php

declare(strict_types=1);

namespace Modules\Tax\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Tax\Actions\QueueEfaktur;
use Modules\Tax\Domain\Pph21TerCalculator;
use Modules\Tax\Domain\Pph21TerTable;
use Modules\Tax\Domain\PphFinalRateResolver;
use Modules\Tax\Services\PphFinalRateRepository;

final class TaxServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PphFinalRateRepository::class);
        $this->app->bind(PphFinalRateResolver::class, fn ($app) => $app->make(PphFinalRateRepository::class)->resolver());

        // PPh 21 TER: the monthly rate table is data the container can't autowire.
        $this->app->bind(Pph21TerCalculator::class, fn () => new Pph21TerCalculator(Pph21TerTable::statutory()));

        // Queue an e-Faktur when a termin invoice is issued (consumes the billing
        // fact via the outbox; Tax stays Platform-only).
        $this->app->tag(QueueEfaktur::class, 'outbox.consumers');
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
    }
}
