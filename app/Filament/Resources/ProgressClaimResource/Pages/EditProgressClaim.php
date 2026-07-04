<?php

declare(strict_types=1);

namespace App\Filament\Resources\ProgressClaimResource\Pages;

use App\Filament\Resources\ProgressClaimResource;
use Filament\Resources\Pages\EditRecord;

final class EditProgressClaim extends EditRecord
{
    protected static string $resource = ProgressClaimResource::class;
}
