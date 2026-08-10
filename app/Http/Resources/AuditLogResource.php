<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AuditLog
 */
final class AuditLogResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,

            'action' => $this->action->value,
            'action_label' => $this->action->label(),

            'result' => $this->result->value,
            'result_label' => $this->result->label(),

            'actor' => [
                'id' => $this->actor_id,
                'name' => $this->actor_name,
                'email' => $this->actor_email,
            ],

            'resource' => [
                'type' => $this->resource_type,
                'id' => $this->resource_id,
                'label' => $this->resource_label,
            ],

            'previous_state' => $this->previous_state,
            'new_state' => $this->new_state,

            // Microsoft's error code is safe to show and is what support will
            // ask for. The underlying exception message is not exposed.
            'error_code' => $this->error_code,

            'channel' => $this->channel,
            'correlation_id' => $this->correlation_id,
            'ip_address' => $this->ip_address,

            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
