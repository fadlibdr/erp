<?php

declare(strict_types=1);

namespace Modules\Finance\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Finance\Domain\Ledger\ProgressInvoicePostingRule;
use Modules\Finance\Domain\Ledger\VendorBillPostingRule;
use Modules\Finance\Services\LedgerPosting;
use Modules\Finance\Services\PostingRuleEngine;

final class FinanceServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // One posting engine per request; domain modules resolve it to react to facts.
        $this->app->singleton(PostingRuleEngine::class, function ($app) {
            $engine = new PostingRuleEngine($app->make(LedgerPosting::class));

            // Register every posting rule the product ships with. Adding a new
            // financial fact = adding a typed rule here (a code release), by design.
            $engine->register(new ProgressInvoicePostingRule);
            $engine->register(new VendorBillPostingRule);

            return $engine;
        });
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
    }
}
