<?php

declare(strict_types=1);

namespace Modules\Receivables\Providers;

use Illuminate\Support\ServiceProvider;

final class ReceivablesServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
    }
}
