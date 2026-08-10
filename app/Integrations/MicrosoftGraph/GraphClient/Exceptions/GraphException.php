<?php

declare(strict_types=1);

namespace App\Integrations\MicrosoftGraph\GraphClient\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Base class for every Microsoft Graph failure.
 *
 * Two audiences are served deliberately differently:
 *
 *   - getMessage() is for logs and the audit trail. It names the operation and
 *     the Graph error code, and never contains a token or a response body.
 *   - userMessage() is for administrators. It explains what failed and what to
 *     do about it, with no Graph internals leaking through.
 */
class GraphException extends RuntimeException
{
    /**
     * @param  string  $method  HTTP method of the failed request
     * @param  string  $path  Graph path, with no query string (which can carry filters containing user data)
     * @param  ?string  $errorCode  Microsoft's `error.code`
     * @param  ?string  $graphMessage  Microsoft's `error.message`
     * @param  ?string  $requestId  Microsoft's request id, for support escalation
     */
    public function __construct(
        protected readonly string $method,
        protected readonly string $path,
        protected readonly int $status = 0,
        protected readonly ?string $errorCode = null,
        protected readonly ?string $graphMessage = null,
        protected readonly ?string $requestId = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct(
            sprintf(
                'Graph %s %s failed with status %d%s.',
                $method,
                $path,
                $status,
                $errorCode === null ? '' : ' ('.$errorCode.')',
            ),
            $status,
            $previous,
        );
    }

    public function graphErrorCode(): ?string
    {
        return $this->errorCode;
    }

    public function graphMessage(): ?string
    {
        return $this->graphMessage;
    }

    public function graphRequestId(): ?string
    {
        return $this->requestId;
    }

    public function status(): int
    {
        return $this->status;
    }

    public function method(): string
    {
        return $this->method;
    }

    public function path(): string
    {
        return $this->path;
    }

    /**
     * Message shown to an administrator.
     */
    public function userMessage(): string
    {
        return 'Microsoft Graph rejected the request. Please try again, and contact support if the problem continues.';
    }

    /**
     * Stable machine code returned to the frontend so the UI can react without
     * string-matching on prose.
     */
    public function errorKey(): string
    {
        return 'graph_request_failed';
    }

    /**
     * Whether retrying the identical request could plausibly succeed. Used by
     * jobs to decide between releasing and failing.
     */
    public function isRetryable(): bool
    {
        return false;
    }

    /**
     * Safe structured context for logs and audit entries.
     *
     * @return array<string, mixed>
     */
    public function context(): array
    {
        return array_filter([
            'method' => $this->method,
            'path' => $this->path,
            'status' => $this->status,
            'graph_error_code' => $this->errorCode,
            'graph_request_id' => $this->requestId,
        ], static fn (mixed $value): bool => $value !== null);
    }
}
