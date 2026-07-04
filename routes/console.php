<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Modules\Finance\Services\OutboxRelay;

// Drain the outbox into the posting engine. Scheduled every minute in production;
// run manually here for demos and tests: `php artisan outbox:relay`.
Artisan::command('outbox:relay', function (OutboxRelay $relay): void {
    $n = $relay->drain();
    $this->info("Relayed {$n} outbox event(s).");
})->purpose('Post queued domain facts to the general ledger');
