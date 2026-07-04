<?php

declare(strict_types=1);

namespace App\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasTenants;
use Filament\Panel;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Modules\Platform\Models\Company;

/**
 * The back-office user. Tenancy lives here in the app layer — never on the Company
 * model — so the "domain is Filament-free" arch test holds (Company stays a plain
 * Platform model; the Filament contracts are implemented only here).
 *
 * A user belongs to one or more companies (the KSO substrate: a person may work
 * across a company and the KSOs it leads). Filament scopes every tenant-aware
 * resource to the current company and auto-fills company_id on create, which is what
 * closes the create-form company gap the Pass-3 spine left open.
 */
final class User extends Authenticatable implements FilamentUser, HasTenants
{
    use HasUuids;

    protected $fillable = ['name', 'email', 'password'];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = ['password' => 'hashed'];

    public function canAccessPanel(Panel $panel): bool
    {
        return true; // real gating (roles/status) is a later pass; all users reach the admin panel
    }

    /** @return BelongsToMany<Company, $this> */
    public function companies(): BelongsToMany
    {
        return $this->belongsToMany(Company::class, 'company_user');
    }

    /** @return Collection<int, Company> */
    public function getTenants(Panel $panel): Collection
    {
        return $this->companies;
    }

    public function canAccessTenant(Model $tenant): bool
    {
        return $this->companies()->whereKey($tenant->getKey())->exists();
    }
}
