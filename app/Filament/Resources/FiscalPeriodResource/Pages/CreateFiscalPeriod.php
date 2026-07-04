<?php

declare(strict_types=1);

namespace App\Filament\Resources\FiscalPeriodResource\Pages;

use App\Filament\Resources\FiscalPeriodResource;
use Filament\Resources\Pages\CreateRecord;

final class CreateFiscalPeriod extends CreateRecord
{
    protected static string $resource = FiscalPeriodResource::class;
}
