<?php

declare(strict_types=1);

namespace App\Filament\Resources\VendorResource\Pages;

use App\Filament\BaseCreateRecord;
use App\Filament\Resources\VendorResource;

final class CreateVendor extends BaseCreateRecord
{
    protected static string $resource = VendorResource::class;
}
