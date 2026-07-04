<?php

declare(strict_types=1);

namespace App\Filament\Resources\VendorBillResource\Pages;

use App\Filament\Resources\VendorBillResource;
use Filament\Resources\Pages\CreateRecord;

final class CreateVendorBill extends CreateRecord
{
    protected static string $resource = VendorBillResource::class;
}
