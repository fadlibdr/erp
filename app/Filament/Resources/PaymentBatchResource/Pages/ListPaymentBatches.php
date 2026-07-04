<?php

declare(strict_types=1);

namespace App\Filament\Resources\PaymentBatchResource\Pages;

use App\Filament\Resources\PaymentBatchResource;
use Filament\Resources\Pages\ListRecords;

final class ListPaymentBatches extends ListRecords
{
    protected static string $resource = PaymentBatchResource::class;
}
