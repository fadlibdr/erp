<?php

declare(strict_types=1);

namespace Modules\Receivables\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Receivables\Actions\RecordArInvoice;

final class ReceivablesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Build the AR sub-ledger from termin facts (outbox consumer of the billing
        // fact). Tagged so the relay picks it up; Receivables stays Platform-only.
        $this->app->tag(RecordArInvoice::class, 'outbox.consumers');
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
    }
}
