<?php

declare(strict_types=1);

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Modules\Platform\Models\Company;

/**
 * The back-office panel. It lives in the app layer — never inside a module — so the
 * dependency law and the "domain is Filament-free" arch test hold without exception:
 * resources here call module Actions, but no module ever imports Filament. Swapping
 * Filament 3 → 4 (or another admin engine entirely) touches only app/Filament.
 */
final class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            // GARIS palette: Biru Baja is every interactive/primary surface, Amber K3
            // is the single attention accent, Beton the warm neutral, and the semantics
            // (hijau/merah) keep their meaning. Hues anchored to the GARIS spec.
            ->colors([
                'primary' => Color::hex('#17497E'),   // Biru Baja
                'gray' => Color::Stone,               // Beton (warm neutral)
                'info' => Color::hex('#1B5693'),
                'warning' => Color::hex('#EDA200'),   // Amber K3
                'success' => Color::hex('#1E7F4F'),   // Hijau
                'danger' => Color::hex('#B3261E'),    // Merah
            ])
            ->font('Public Sans')
            ->brandName('KARYA')
            // Multi-company tenancy (the KSO substrate): every tenant-aware resource
            // is scoped to the chosen company via each record's company() relation,
            // and company_id is auto-filled on create — closing the create-form gap.
            ->tenant(Company::class, slugAttribute: 'code')
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([Dashboard::class])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
