<?php

declare(strict_types=1);

namespace App\Filament\Resources\FiscalPeriodResource\Pages;

use App\Filament\BaseCreateRecord;
use App\Filament\Resources\FiscalPeriodResource;

final class CreateFiscalPeriod extends BaseCreateRecord
{
    protected static string $resource = FiscalPeriodResource::class;
}
