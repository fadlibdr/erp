<?php

declare(strict_types=1);

namespace App\Filament;

use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;

/**
 * Base create page: stamps the current company (tenant) onto every new record, since
 * we scope by company_id manually (see BaseResource) rather than via a tenant
 * relationship the dependency law disallows.
 *
 * @property array<string, mixed> $data
 */
abstract class BaseCreateRecord extends CreateRecord
{
    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if ($tenant = Filament::getTenant()) {
            $data['company_id'] = $tenant->getKey();
        }

        return $data;
    }
}
