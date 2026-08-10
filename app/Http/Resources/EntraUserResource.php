<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\EntraUser;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin EntraUser
 */
final class EntraUserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'microsoft_id' => $this->microsoft_id,

            'display_name' => $this->display_name,
            'user_principal_name' => $this->user_principal_name,
            'mail' => $this->mail,
            'job_title' => $this->job_title,
            'department' => $this->department,
            'office_location' => $this->office_location,
            'user_type' => $this->user_type,

            'account_enabled' => $this->account_enabled,

            // Null means we have not synchronised the report, or the tenant
            // cannot serve it. The UI shows "Unknown", never "Not registered".
            'mfa_registered' => $this->is_mfa_registered,

            'license_count' => $this->assigned_license_count,
            'device_count' => $this->managed_device_count,

            'last_sign_in_at' => $this->last_sign_in_at?->toIso8601String(),
            'created_at' => $this->created_date_time?->toIso8601String(),

            // Directory-synchronised accounts are mastered on-premises, so
            // cloud writes are rejected. Sent so the client can disable the
            // action instead of offering it and failing.
            'is_directory_synced' => $this->isDirectorySynced(),

            'synced_at' => $this->synced_at?->toIso8601String(),
        ];
    }
}
