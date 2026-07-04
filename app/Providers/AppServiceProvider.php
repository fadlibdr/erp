<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Database\Eloquent\Model;
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
    }
}
