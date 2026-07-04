<?php

declare(strict_types=1);

use App\Providers\AppServiceProvider;
use App\Providers\Filament\AdminPanelProvider;
use Modules\Billing\Providers\BillingServiceProvider;
use Modules\Finance\Providers\FinanceServiceProvider;
use Modules\Inventory\Providers\InventoryServiceProvider;
use Modules\Payables\Providers\PayablesServiceProvider;
use Modules\Payroll\Providers\PayrollServiceProvider;
use Modules\Platform\Providers\PlatformServiceProvider;
use Modules\Procurement\Providers\ProcurementServiceProvider;
use Modules\Projects\Providers\ProjectsServiceProvider;
use Modules\Receivables\Providers\ReceivablesServiceProvider;
use Modules\Reporting\Providers\ReportingServiceProvider;
use Modules\Tax\Providers\TaxServiceProvider;

return [
    AppServiceProvider::class,
    AdminPanelProvider::class,

    // Module service providers. internachi/modular can auto-discover these from
    // each module's composer.json when the composer-merge-plugin is active; they
    // are listed explicitly here so provider registration (posting-rule wiring,
    // migrations, bindings) never silently depends on that plugin having run.
    // Platform is the foundation and comes first; Finance/Tax before the domain
    // modules that publish the facts they consume.
    PlatformServiceProvider::class,
    FinanceServiceProvider::class,
    TaxServiceProvider::class,
    ProjectsServiceProvider::class,
    ProcurementServiceProvider::class,
    InventoryServiceProvider::class,
    ReceivablesServiceProvider::class,
    PayablesServiceProvider::class,
    BillingServiceProvider::class,
    ReportingServiceProvider::class,
    PayrollServiceProvider::class,
];
