<?php

declare(strict_types=1);

namespace Modules\Payables\Providers;

use Illuminate\Support\ServiceProvider;

final class PayablesServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');
    }
}
