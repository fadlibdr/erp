<?php

declare(strict_types=1);

namespace App\Filament\Resources\ArRetentionResource\Pages;

use App\Filament\Resources\ArRetentionResource;
use Filament\Resources\Pages\ListRecords;

final class ListArRetentions extends ListRecords
{
    protected static string $resource = ArRetentionResource::class;
}
