<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Test bootstrap
|--------------------------------------------------------------------------
| Feature tests boot the full Laravel app and hit a PostgreSQL test database.
| Unit and Arch tests need neither, and mirror the pure-domain checks in
| bin/domain-tests.php so they run in CI alongside the rest of the suite.
*/

pest()->extend(Tests\TestCase::class)->in('Feature');

expect()->extend('toBalance', function () {
    $debits = 0;
    $credits = 0;
    foreach ($this->value->lines as $line) {
        $debits += $line->debit->minor;
        $credits += $line->credit->minor;
    }
    expect($debits)->toBe($credits);

    return $this;
});
