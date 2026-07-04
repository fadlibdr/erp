<?php

declare(strict_types=1);

namespace App\Filament\Resources\GrnResource\Pages;

use App\Filament\Resources\GrnResource;
use Filament\Resources\Pages\ListRecords;

final class ListGrns extends ListRecords
{
    protected static string $resource = GrnResource::class;
}
