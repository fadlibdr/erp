<?php

declare(strict_types=1);

namespace App\Filament\Resources\EmployeeResource\Pages;

use App\Filament\BaseCreateRecord;
use App\Filament\Resources\EmployeeResource;

final class CreateEmployee extends BaseCreateRecord
{
    protected static string $resource = EmployeeResource::class;
}
