<?php

declare(strict_types=1);

namespace Modules\Inventory\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Inventory\Services\StockLedgerWriter;

final class InventoryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // The stock ledger reacts to goods-received facts. Tagging it (rather than
        // having Finance's relay import it) keeps Inventory → Platform only.
        $this->app->tag(StockLedgerWriter::class, 'outbox.consumers');
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
    }
}
