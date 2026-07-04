<?php

declare(strict_types=1);

namespace App\Filament\Resources\VendorBillResource\Pages;

use App\Filament\BaseCreateRecord;
use App\Filament\Resources\VendorBillResource;

final class CreateVendorBill extends BaseCreateRecord
{
    protected static string $resource = VendorBillResource::class;
}
