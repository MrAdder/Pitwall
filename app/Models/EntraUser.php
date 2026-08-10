<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Cached projection of an Entra ID user.
 *
 * Read paths (lists, search, dashboards, reports) use this table. Write paths
 * always go to Microsoft Graph first and only then refresh the local row, so
 * this never becomes a competing source of truth.
 *
 * @property string $tenant_id
 * @property string $microsoft_id
 * @property ?string $user_principal_name
 * @property ?string $display_name
 * @property ?bool $account_enabled
 */
class EntraUser extends Model
{
    use BelongsToTenant, HasUlids, SoftDeletes;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'account_enabled' => 'boolean',
            'on_premises_sync_enabled' => 'boolean',
            'is_mfa_registered' => 'boolean',
            'is_mfa_capable' => 'boolean',
            'assigned_license_skus' => 'array',
            'created_date_time' => 'datetime',
            'last_sign_in_at' => 'datetime',
            'last_non_interactive_sign_in_at' => 'datetime',
            'synced_at' => 'datetime',
        ];
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(EntraGroupMembership::class);
    }

    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(EntraGroup::class, 'entra_group_memberships')
            ->withTimestamps();
    }

    public function devices(): HasMany
    {
        return $this->hasMany(ManagedDevice::class);
    }

    /**
     * Free-text search across the fields an administrator actually types:
     * name, sign-in name, and mail.
     */
    public function scopeSearch(Builder $query, string $term): Builder
    {
        $term = trim($term);

        if ($term === '') {
            return $query;
        }

        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';

        return $query->where(function (Builder $query) use ($like): void {
            $query->where('display_name', 'like', $like)
                ->orWhere('user_principal_name', 'like', $like)
                ->orWhere('mail', 'like', $like);
        });
    }

    /**
     * On-premises synchronised users cannot have most attributes changed in
     * the cloud; Graph rejects the write. The UI uses this to explain why an
     * action is unavailable instead of surfacing a Graph error later.
     */
    public function isDirectorySynced(): bool
    {
        return $this->on_premises_sync_enabled === true;
    }
}
