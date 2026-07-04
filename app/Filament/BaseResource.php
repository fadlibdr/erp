<?php

declare(strict_types=1);

namespace App\Filament;

use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Base for every KARYA resource. It scopes records to the current company (tenant)
 * *manually* rather than through Filament's relationship-based tenancy, because the
 * dependency law forbids the Company (Platform) model from referencing module models
 * — so `Company->vendors()` etc. can't exist. Filament's own tenant scoping is
 * therefore disabled; we filter on `company_id` here and inject it on create
 * (BaseCreateRecord). Multi-company still holds: the tenant lives in the URL and
 * Filament::getTenant() drives the filter.
 */
abstract class BaseResource extends Resource
{
    protected static bool $isScopedToTenant = false;

    /** @return Builder<Model> */
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        if ($tenant = Filament::getTenant()) {
            $query->where($query->getModel()->getTable().'.company_id', $tenant->getKey());
        }

        return $query;
    }
}
