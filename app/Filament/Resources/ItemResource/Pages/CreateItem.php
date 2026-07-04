<?php

declare(strict_types=1);

namespace App\Filament\Resources\ItemResource\Pages;

use App\Filament\BaseCreateRecord;
use App\Filament\Resources\ItemResource;

final class CreateItem extends BaseCreateRecord
{
    protected static string $resource = ItemResource::class;
}
