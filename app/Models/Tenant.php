<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Tenancy\TenantStatus;
use Database\Factories\TenantFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A connected customer Microsoft 365 environment.
 *
 * This model is not itself tenant-scoped; it defines the scope. Access to a
 * tenant is granted only through {@see TenantMembership}.
 *
 * @property string $id
 * @property string $name
 * @property string $slug
 * @property string $microsoft_tenant_id
 * @property ?string $default_domain
 * @property TenantStatus $status
 */
#[Fillable(['name', 'slug', 'microsoft_tenant_id', 'default_domain', 'status', 'settings'])]
class Tenant extends Model
{
    /** @use HasFactory<TenantFactory> */
    use HasFactory, HasUlids, SoftDeletes;

    protected function casts(): array
    {
        return [
            'status' => TenantStatus::class,
            'settings' => 'array',
            'consented_at' => 'datetime',
            'connection_checked_at' => 'datetime',
        ];
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(TenantMembership::class);
    }

    public function syncStates(): HasMany
    {
        return $this->hasMany(SyncState::class);
    }

    /**
     * Whether Graph calls should be attempted for this tenant. A pending or
     * disabled tenant has no usable credentials, so callers skip it rather
     * than generating guaranteed failures.
     */
    public function isConnected(): bool
    {
        return $this->status->isConnected();
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
