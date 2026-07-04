<?php

declare(strict_types=1);

namespace Modules\Finance\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Finance\Domain\Ledger\CustomerReceiptPostingRule;
use Modules\Finance\Domain\Ledger\GrnPostingRule;
use Modules\Finance\Domain\Ledger\MaterialBillPostingRule;
use Modules\Finance\Domain\Ledger\MaterialIssuePostingRule;
use Modules\Finance\Domain\Ledger\ProgressInvoicePostingRule;
use Modules\Finance\Domain\Ledger\Psak72PostingRule;
use Modules\Finance\Domain\Ledger\RetentionReleasePostingRule;
use Modules\Finance\Domain\Ledger\VendorBillPostingRule;
use Modules\Finance\Domain\Ledger\VendorPaymentPostingRule;
use Modules\Finance\Services\CommitmentProjector;
use Modules\Finance\Services\LedgerPosting;
use Modules\Finance\Services\OutboxRelay;
use Modules\Finance\Services\PostingRuleEngine;
use Modules\Platform\Support\Outbox;

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
            $engine->register(new GrnPostingRule);          // Pass 3: GR/IR accrual
            $engine->register(new Psak72PostingRule);       // Pass 3: month-end recognition
            $engine->register(new MaterialBillPostingRule); // Pass 4: clears GR/IR on the bill
            $engine->register(new MaterialIssuePostingRule); // Pass 5A: material → project cost
            $engine->register(new VendorPaymentPostingRule);    // Pass 5B: AP settlement
            $engine->register(new CustomerReceiptPostingRule);  // Pass 5B: AR receipt
            $engine->register(new RetentionReleasePostingRule); // Pass 5B: retention release

            return $engine;
        });

        // The commitment projection reacts to procurement facts without posting a
        // journal — an outbox consumer, tagged so the relay picks it up.
        $this->app->tag(CommitmentProjector::class, 'outbox.consumers');

        // The relay drains the outbox into the engine plus every tagged consumer.
        // Tags resolve at runtime, so this binding creates no static dependency on
        // the modules (Inventory, …) that contribute consumers.
        $this->app->bind(OutboxRelay::class, function ($app) {
            return new OutboxRelay(
                $app->make(Outbox::class),
                $app->make(PostingRuleEngine::class),
                $app->tagged('outbox.consumers'),
            );
        });
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
    }
}
