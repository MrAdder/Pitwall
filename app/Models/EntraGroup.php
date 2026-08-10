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
 * Cached projection of an Entra ID group.
 *
 * @property string $tenant_id
 * @property string $microsoft_id
 * @property ?string $display_name
 * @property ?array<int, string> $group_types
 */
class EntraGroup extends Model
{
    use BelongsToTenant, HasUlids, SoftDeletes;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'security_enabled' => 'boolean',
            'mail_enabled' => 'boolean',
            'on_premises_sync_enabled' => 'boolean',
            'group_types' => 'array',
            'created_date_time' => 'datetime',
            'synced_at' => 'datetime',
        ];
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(EntraGroupMembership::class);
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(EntraUser::class, 'entra_group_memberships')
            ->withTimestamps();
    }

    public function scopeSearch(Builder $query, string $term): Builder
    {
        $term = trim($term);

        if ($term === '') {
            return $query;
        }

        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';

        return $query->where(function (Builder $query) use ($like): void {
            $query->where('display_name', 'like', $like)
                ->orWhere('mail', 'like', $like);
        });
    }

    /**
     * Dynamic groups compute their own membership. Graph rejects attempts to
     * add or remove members, so the UI must not offer those actions.
     */
    public function hasDynamicMembership(): bool
    {
        return in_array('DynamicMembership', $this->group_types ?? [], strict: true);
    }

    /**
     * Groups mastered on-premises are read-only in the cloud.
     */
    public function isDirectorySynced(): bool
    {
        return $this->on_premises_sync_enabled === true;
    }

    public function isMembershipEditable(): bool
    {
        return ! $this->hasDynamicMembership() && ! $this->isDirectorySynced();
    }
}
