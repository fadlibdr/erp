<?php

declare(strict_types=1);

namespace App\Filament\Resources\ProgressClaimResource\Pages;

use App\Filament\Resources\ProgressClaimResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

final class ListProgressClaims extends ListRecords
{
    protected static string $resource = ProgressClaimResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
