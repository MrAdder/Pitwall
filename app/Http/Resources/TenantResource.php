<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Tenant
 */
final class TenantResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'default_domain' => $this->default_domain,

            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'is_connected' => $this->isConnected(),

            // Explains a degraded connection so an owner knows to reconsent
            // rather than wondering why data stopped refreshing.
            'connection_error' => $this->connection_error_message,

            'consented_at' => $this->consented_at?->toIso8601String(),

            // The caller's own role and permissions for this tenant, so the UI
            // can hide actions it would not be allowed to perform.
            'role' => $this->whenNotNull(
                $request->user()?->roleFor($this->resource)?->value,
            ),
            'permissions' => $request->user()?->roleFor($this->resource)?->permissionValues() ?? [],
        ];
    }
}
