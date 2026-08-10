<?php

declare(strict_types=1);

namespace App\Domain\Audit;

use App\Domain\Tenancy\TenantContext;
use App\Models\AuditLog;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Throwable;

/**
 * The single way audit entries are written.
 *
 * Callers describe what they did with an {@see AuditEntry}; this class fills in
 * the actor, tenant, request metadata and correlation id. Keeping that in one
 * place is what makes "every administrative action is audited" checkable.
 */
final class AuditLogger
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly Request $request,
    ) {}

    /**
     * Record a completed action.
     */
    public function log(AuditEntry $entry): AuditLog
    {
        $tenant = $entry->tenant ?? $this->tenantContext->tenant();
        $actor = $entry->actor ?? $this->currentUser();

        return AuditLog::create([
            'tenant_id' => $tenant->id,

            'actor_id' => $actor?->id,
            'actor_name' => $actor?->name,
            'actor_email' => $actor?->email,

            'action' => $entry->action,
            'result' => $entry->result,

            'resource_type' => $entry->resourceType,
            'resource_id' => $entry->resourceId,
            'resource_microsoft_id' => $entry->resourceMicrosoftId,
            'resource_label' => $entry->resourceLabel,

            'previous_state' => $entry->previousState,
            'new_state' => $entry->newState,
            'context' => $entry->context,

            'error_code' => $entry->errorCode,
            'error_message' => $entry->errorMessage,

            'correlation_id' => $entry->correlationId ?? (string) Str::uuid(),

            'ip_address' => $this->clientIp(),
            'user_agent' => Str::limit((string) $this->request->userAgent(), 500, ''),
            'channel' => $entry->channel->value,

            'created_at' => now(),
        ]);
    }

    /**
     * Run an action, recording success or failure either way.
     *
     * The audit entry is written even when the callback throws, which is the
     * point: a failed device wipe is at least as interesting as a successful
     * one. The exception is then rethrown untouched.
     *
     * @template TReturn
     *
     * @param  callable(): TReturn  $callback
     * @return TReturn
     */
    public function around(AuditEntry $entry, callable $callback): mixed
    {
        $correlationId = $entry->correlationId ?? (string) Str::uuid();

        try {
            $result = $callback();

            $this->log($entry->with(
                correlationId: $correlationId,
                newState: $entry->newState,
            ));

            return $result;
        } catch (Throwable $e) {
            $this->log($entry->failed($e)->with(correlationId: $correlationId));

            throw $e;
        }
    }

    /**
     * Record that an action was refused. Denials are audited so that repeated
     * attempts to exceed a role are visible.
     */
    public function denied(AuditEntry $entry, string $reason): AuditLog
    {
        return $this->log($entry->with(
            result: AuditResult::Denied,
            errorMessage: $reason,
        ));
    }

    /**
     * Read the actor from the auth guard rather than the request.
     *
     * The guard is authoritative in every context this runs in — HTTP, queue
     * and console — whereas a request object outside a real HTTP cycle has no
     * user resolver attached and would silently report no actor.
     */
    private function currentUser(): ?User
    {
        $user = Auth::user();

        return $user instanceof User ? $user : null;
    }

    /**
     * Console and queue contexts have no request IP; recording the loopback
     * address there would be misleading.
     */
    private function clientIp(): ?string
    {
        return app()->runningInConsole() ? null : $this->request->ip();
    }
}
