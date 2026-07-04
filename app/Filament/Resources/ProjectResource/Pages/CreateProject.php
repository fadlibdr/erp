<?php

declare(strict_types=1);

namespace App\Filament\Resources\ProjectResource\Pages;

use App\Filament\BaseCreateRecord;
use App\Filament\Resources\ProjectResource;

final class CreateProject extends BaseCreateRecord
{
    protected static string $resource = ProjectResource::class;
}
