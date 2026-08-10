<?php

declare(strict_types=1);

namespace App\Integrations\MicrosoftGraph\GraphClient\Exceptions;

use Throwable;

/**
 * Graph returned 429, and we exhausted our retry budget.
 *
 * The client already honoured Retry-After for the earlier attempts. Reaching
 * this exception means the tenant is being throttled harder than a single
 * request can wait out, so the work belongs back on the queue.
 */
final class GraphThrottled extends GraphException
{
    public function __construct(
        string $method,
        string $path,
        int $status = 429,
        ?string $errorCode = null,
        ?string $graphMessage = null,
        ?string $requestId = null,
        /** Seconds Microsoft asked us to wait, when supplied. */
        private readonly ?int $retryAfterSeconds = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($method, $path, $status, $errorCode, $graphMessage, $requestId, $previous);
    }

    public function retryAfterSeconds(): ?int
    {
        return $this->retryAfterSeconds;
    }

    public function isRetryable(): bool
    {
        return true;
    }

    public function userMessage(): string
    {
        return 'Microsoft is currently rate limiting requests for this tenant. The operation has been queued and will be retried automatically.';
    }

    public function errorKey(): string
    {
        return 'graph_throttled';
    }

    /**
     * @return array<string, mixed>
     */
    public function context(): array
    {
        return array_filter(
            [...parent::context(), 'retry_after_seconds' => $this->retryAfterSeconds],
            static fn (mixed $value): bool => $value !== null,
        );
    }
}
