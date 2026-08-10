<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\SyncState;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SyncState
 */
final class SyncStateResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'resource' => $this->resource->value,
            'resource_label' => $this->resource->label(),

            'status' => $this->status->value,
            'status_label' => $this->status->label(),

            // The timestamp the UI shows as "Last synchronised". Deliberately
            // the last *successful* run, so a failing sync cannot make stale
            // data look current.
            'last_successful_at' => $this->last_successful_at?->toIso8601String(),
            'is_stale' => $this->isStale(),

            'records' => [
                'created' => $this->records_created,
                'updated' => $this->records_updated,
                'deleted' => $this->records_deleted,
            ],

            'consecutive_failures' => $this->consecutive_failures,
            'error_code' => $this->error_code,
        ];
    }
}
