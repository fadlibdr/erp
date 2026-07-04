<?php

declare(strict_types=1);

namespace App\Filament\Resources\ProgressClaimResource\Pages;

use App\Filament\Resources\ProgressClaimResource;
use Filament\Resources\Pages\CreateRecord;

final class CreateProgressClaim extends CreateRecord
{
    protected static string $resource = ProgressClaimResource::class;
}
