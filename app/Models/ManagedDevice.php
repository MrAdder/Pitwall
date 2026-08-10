<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Devices\ComplianceState;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Cached projection of an Intune managed device.
 *
 * @property string $tenant_id
 * @property string $microsoft_id
 * @property ?string $device_name
 * @property ?ComplianceState $compliance_state
 */
class ManagedDevice extends Model
{
    use BelongsToTenant, HasUlids, SoftDeletes;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'compliance_state' => ComplianceState::class,
            'is_encrypted' => 'boolean',
            'is_supervised' => 'boolean',
            'jail_broken' => 'boolean',
            'last_sync_date_time' => 'datetime',
            'enrolled_date_time' => 'datetime',
            'compliance_grace_period_expires_at' => 'datetime',
            'synced_at' => 'datetime',
        ];
    }

    public function primaryUser(): BelongsTo
    {
        return $this->belongsTo(EntraUser::class, 'entra_user_id');
    }

    public function scopeSearch(Builder $query, string $term): Builder
    {
        $term = trim($term);

        if ($term === '') {
            return $query;
        }

        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';

        return $query->where(function (Builder $query) use ($like): void {
            $query->where('device_name', 'like', $like)
                ->orWhere('serial_number', 'like', $like)
                ->orWhere('user_principal_name', 'like', $like);
        });
    }

    /**
     * Devices that have not checked in recently. Intune keeps reporting the
     * last known compliance state for these, which is how a wiped or retired
     * machine can sit in a list looking healthy.
     */
    public function scopeNotCheckedInFor(Builder $query, int $days): Builder
    {
        return $query->where(function (Builder $query) use ($days): void {
            $query->where('last_sync_date_time', '<', now()->subDays($days))
                ->orWhereNull('last_sync_date_time');
        });
    }

    public function isStale(int $days = 14): bool
    {
        return $this->last_sync_date_time === null
            || $this->last_sync_date_time->lt(now()->subDays($days));
    }
}
