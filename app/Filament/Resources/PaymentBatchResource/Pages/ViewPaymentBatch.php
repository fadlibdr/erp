<?php

declare(strict_types=1);

namespace App\Filament\Resources\PaymentBatchResource\Pages;

use App\Filament\Resources\PaymentBatchResource;
use Filament\Resources\Pages\ViewRecord;

final class ViewPaymentBatch extends ViewRecord
{
    protected static string $resource = PaymentBatchResource::class;
}
