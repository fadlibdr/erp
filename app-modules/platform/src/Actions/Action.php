<?php

declare(strict_types=1);

namespace Modules\Platform\Actions;

/**
 * Marker base for application Actions.
 *
 * Every state change in the system goes through an Action, never through a
 * Filament resource or a controller directly. Filament pages call Actions; the
 * arch test asserts the domain never imports Filament. That keeps business logic
 * in one place and the UI layer disposable — the reason a 10-year ERP survives a
 * framework-UI rewrite (Filament 3 -> 4) with the blast radius limited to the skin.
 */
abstract class Action
{
}
