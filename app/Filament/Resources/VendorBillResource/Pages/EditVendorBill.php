<?php

declare(strict_types=1);

namespace App\Filament\Resources\VendorBillResource\Pages;

use App\Filament\Resources\VendorBillResource;
use Filament\Resources\Pages\EditRecord;

final class EditVendorBill extends EditRecord
{
    protected static string $resource = VendorBillResource::class;
}
