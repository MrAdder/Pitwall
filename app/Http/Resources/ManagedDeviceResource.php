<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\ManagedDevice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ManagedDevice
 */
final class ManagedDeviceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'microsoft_id' => $this->microsoft_id,

            'device_name' => $this->device_name,
            'manufacturer' => $this->manufacturer,
            'model' => $this->model,
            'serial_number' => $this->serial_number,

            'operating_system' => $this->operating_system,
            'os_version' => $this->os_version,

            'compliance_state' => $this->compliance_state?->value,
            'compliance_state_label' => $this->compliance_state?->label(),

            'owner_type' => $this->owner_type,
            'is_encrypted' => $this->is_encrypted,

            'primary_user' => [
                'entra_user_id' => $this->entra_user_id,
                'user_principal_name' => $this->user_principal_name,
            ],

            'last_check_in_at' => $this->last_sync_date_time?->toIso8601String(),
            'enrolled_at' => $this->enrolled_date_time?->toIso8601String(),

            // A device that has not checked in for a fortnight is reporting
            // its last known state, not its current one.
            'is_stale' => $this->isStale(),

            'storage' => [
                'total_bytes' => $this->total_storage_bytes,
                'free_bytes' => $this->free_storage_bytes,
            ],

            'synced_at' => $this->synced_at?->toIso8601String(),
        ];
    }
}
