<?php

declare(strict_types=1);

namespace Modules\Platform\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Platform\Support\NumberingService;
use Modules\Platform\Support\Outbox;

final class PlatformServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Platform primitives are singletons shared across modules.
        $this->app->singleton(Outbox::class);
        $this->app->singleton(NumberingService::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');
    }
}
