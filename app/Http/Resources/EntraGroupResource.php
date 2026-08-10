<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\EntraGroup;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin EntraGroup
 */
final class EntraGroupResource extends JsonResource
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
            'description' => $this->description,
            'mail' => $this->mail,

            'security_enabled' => $this->security_enabled,
            'mail_enabled' => $this->mail_enabled,
            'group_types' => $this->group_types ?? [],

            'member_count' => $this->member_count,

            // Dynamic and directory-synchronised groups compute or import
            // their membership, so the client hides the add/remove actions
            // rather than letting Graph reject them.
            'has_dynamic_membership' => $this->hasDynamicMembership(),
            'is_directory_synced' => $this->isDirectorySynced(),
            'is_membership_editable' => $this->isMembershipEditable(),

            'synced_at' => $this->synced_at?->toIso8601String(),
        ];
    }
}
