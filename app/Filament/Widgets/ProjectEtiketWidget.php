<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;

/**
 * Showcases the GARIS etiket (drawing title-block) component with a representative
 * BAST document. The <x-garis.etiket> component is the reusable deliverable; a
 * real resource (BAST/SPK/BA Opname view page) feeds it live document data.
 */
final class ProjectEtiketWidget extends Widget
{
    protected static string $view = 'filament.widgets.project-etiket';

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = 3;
}
