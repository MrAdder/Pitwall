<?php

declare(strict_types=1);

namespace App\Domain\Audit;

use App\Integrations\MicrosoftGraph\GraphClient\Exceptions\GraphException;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Str;
use Throwable;

/**
 * Immutable description of something an administrator did.
 *
 * Built by the calling action, then handed to {@see AuditLogger}, which
 * supplies the ambient details (actor, tenant, IP, correlation id).
 */
final readonly class AuditEntry
{
    /**
     * @param  ?array<string, mixed>  $previousState
     * @param  ?array<string, mixed>  $newState
     * @param  ?array<string, mixed>  $context
     */
    public function __construct(
        public AuditAction $action,
        public AuditResult $result = AuditResult::Success,
        public ?string $resourceType = null,
        public ?string $resourceId = null,
        public ?string $resourceMicrosoftId = null,
        public ?string $resourceLabel = null,
        public ?array $previousState = null,
        public ?array $newState = null,
        public ?array $context = null,
        public ?string $errorCode = null,
        public ?string $errorMessage = null,
        public ?string $correlationId = null,
        public AuditChannel $channel = AuditChannel::Web,
        public ?Tenant $tenant = null,
        public ?User $actor = null,
    ) {}

    /**
     * @param  ?array<string, mixed>  $previousState
     * @param  ?array<string, mixed>  $newState
     * @param  ?array<string, mixed>  $context
     */
    public function with(
        ?AuditResult $result = null,
        ?array $previousState = null,
        ?array $newState = null,
        ?array $context = null,
        ?string $errorCode = null,
        ?string $errorMessage = null,
        ?string $correlationId = null,
        ?AuditChannel $channel = null,
        ?Tenant $tenant = null,
        ?User $actor = null,
    ): self {
        return new self(
            action: $this->action,
            result: $result ?? $this->result,
            resourceType: $this->resourceType,
            resourceId: $this->resourceId,
            resourceMicrosoftId: $this->resourceMicrosoftId,
            resourceLabel: $this->resourceLabel,
            previousState: $previousState ?? $this->previousState,
            newState: $newState ?? $this->newState,
            context: $context ?? $this->context,
            errorCode: $errorCode ?? $this->errorCode,
            errorMessage: $errorMessage ?? $this->errorMessage,
            correlationId: $correlationId ?? $this->correlationId,
            channel: $channel ?? $this->channel,
            tenant: $tenant ?? $this->tenant,
            actor: $actor ?? $this->actor,
        );
    }

    /**
     * Derive a failure entry from a thrown exception.
     *
     * Graph exceptions carry a Microsoft error code worth keeping. Anything
     * else is recorded by class name and message only — never a stack trace,
     * which can contain tokens and request bodies.
     */
    public function failed(Throwable $e): self
    {
        return $this->with(
            result: AuditResult::Failure,
            errorCode: $e instanceof GraphException ? $e->graphErrorCode() : class_basename($e),
            errorMessage: Str::limit($e->getMessage(), 1000),
        );
    }
}
