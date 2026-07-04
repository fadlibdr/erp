<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Architecture tests
|--------------------------------------------------------------------------
| These back up deptrac with in-suite guarantees. The two that matter most:
|   1. Domain code never imports Filament — so the whole UI layer is swappable.
|   2. Domain money/ledger code never touches floats.
| Deptrac enforces the module dependency graph; these enforce the layer rules
| within a module.
*/

arch('domain layer is free of Filament')
    ->expect('Modules')
    ->toUseNothing(['Filament'])
    ->ignoring([
        'Modules\Platform\Providers',
        'Modules\Finance\Providers',
        'Modules\Tax\Providers',
        'Modules\Projects\Providers',
        'Modules\Billing\Providers',
    ]);

arch('the ledger and money value objects avoid float casts')
    ->expect(['Modules\Platform\Domain\Money', 'Modules\Finance\Domain\Ledger'])
    ->not->toUse(['floatval', 'round']);

arch('strict types everywhere')
    ->expect('Modules')
    ->toUseStrictTypes();
