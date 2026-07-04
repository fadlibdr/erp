<?php

declare(strict_types=1);

namespace Modules\Projects\Providers;

use Illuminate\Support\ServiceProvider;

final class ProjectsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
    }
}
