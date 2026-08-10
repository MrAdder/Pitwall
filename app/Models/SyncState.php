<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Sync\SyncResource;
use App\Domain\Sync\SyncStatus;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * Per-tenant, per-resource synchronisation bookkeeping.
 *
 * @property SyncResource $resource
 * @property SyncStatus $status
 * @property ?string $delta_link
 */
class SyncState extends Model
{
    use BelongsToTenant, HasUlids;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'resource' => SyncResource::class,
            'status' => SyncStatus::class,
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'last_successful_at' => 'datetime',

            // Delta links are opaque continuation tokens issued by Microsoft
            // and are treated as credentials.
            'delta_link' => 'encrypted',
        ];
    }

    /**
     * Whether the cached data for this resource is old enough that the UI
     * should warn rather than present it as current.
     */
    public function isStale(): bool
    {
        if ($this->last_successful_at === null) {
            return true;
        }

        return $this->last_successful_at->diffInMinutes(now())
            > config('platform.stale_after_minutes');
    }
}
