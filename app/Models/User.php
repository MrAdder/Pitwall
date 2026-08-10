<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Access\Permission;
use App\Domain\Access\Role;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/**
 * A platform administrator.
 *
 * These are the people who sign in to this application. The Entra ID users
 * they manage are {@see EntraUser} records and are tenant-owned.
 *
 * Sign-in is delegated to Microsoft Entra ID, so no password is ever set.
 *
 * @property string $id
 * @property string $name
 * @property string $email
 * @property ?string $microsoft_object_id
 * @property ?string $microsoft_tenant_id
 */
#[Fillable(['name', 'email', 'microsoft_object_id', 'microsoft_tenant_id'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasUlids, Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(TenantMembership::class);
    }

    public function tenants(): BelongsToMany
    {
        return $this->belongsToMany(Tenant::class, 'tenant_memberships')
            ->withPivot('role')
            ->withTimestamps();
    }

    public function membershipFor(Tenant|string $tenant): ?TenantMembership
    {
        $tenantId = $tenant instanceof Tenant ? $tenant->id : $tenant;

        return $this->memberships
            ->firstWhere('tenant_id', $tenantId)
            ?? $this->memberships()->where('tenant_id', $tenantId)->first();
    }

    public function roleFor(Tenant|string $tenant): ?Role
    {
        return $this->membershipFor($tenant)?->role;
    }

    /**
     * Whether this user holds a permission within a specific tenant.
     *
     * Authorisation is always tenant-relative: the same person may be an
     * Administrator on one tenant and Read Only on another.
     */
    public function hasPermissionFor(Tenant|string $tenant, Permission $permission): bool
    {
        return (bool) $this->roleFor($tenant)?->grants($permission);
    }
}
