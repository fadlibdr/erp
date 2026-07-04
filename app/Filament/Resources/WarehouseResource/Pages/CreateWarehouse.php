<?php

declare(strict_types=1);

namespace App\Filament\Resources\WarehouseResource\Pages;

use App\Filament\BaseCreateRecord;
use App\Filament\Resources\WarehouseResource;

final class CreateWarehouse extends BaseCreateRecord
{
    protected static string $resource = WarehouseResource::class;
}
