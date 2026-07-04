<?php

declare(strict_types=1);

namespace App\Filament\Resources\PurchaseOrderResource\Pages;

use App\Filament\BaseCreateRecord;
use App\Filament\Resources\PurchaseOrderResource;

final class CreatePurchaseOrder extends BaseCreateRecord
{
    protected static string $resource = PurchaseOrderResource::class;
}
