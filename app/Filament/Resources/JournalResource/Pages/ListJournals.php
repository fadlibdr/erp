<?php

declare(strict_types=1);

namespace App\Filament\Resources\JournalResource\Pages;

use App\Filament\Resources\JournalResource;
use Filament\Resources\Pages\ListRecords;

final class ListJournals extends ListRecords
{
    protected static string $resource = JournalResource::class;
}
