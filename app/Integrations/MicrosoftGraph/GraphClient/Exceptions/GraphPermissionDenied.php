<?php

declare(strict_types=1);

namespace App\Integrations\MicrosoftGraph\GraphClient\Exceptions;

use Throwable;

/**
 * Graph returned 403.
 *
 * In practice this nearly always means admin consent has not been granted for
 * a permission the operation needs, so the message names that permission
 * rather than saying "forbidden" and leaving the administrator to guess.
 */
final class GraphPermissionDenied extends GraphException
{
    public function __construct(
        string $method,
        string $path,
        int $status = 403,
        ?string $errorCode = null,
        ?string $graphMessage = null,
        ?string $requestId = null,
        /** The Graph permission this operation requires, when known. */
        private readonly ?string $requiredPermission = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($method, $path, $status, $errorCode, $graphMessage, $requestId, $previous);
    }

    public function requiredPermission(): ?string
    {
        return $this->requiredPermission;
    }

    public function userMessage(): string
    {
        if ($this->requiredPermission !== null) {
            return sprintf(
                'Microsoft Graph rejected the request because of insufficient privileges. '
                .'This operation requires the %s permission. Ask a Global Administrator to '
                .'grant admin consent for the connected tenant.',
                $this->requiredPermission,
            );
        }

        return 'Microsoft Graph rejected the request because of insufficient privileges. '
            .'Admin consent may be missing for the connected tenant.';
    }

    public function errorKey(): string
    {
        return 'graph_permission_denied';
    }

    /**
     * @return array<string, mixed>
     */
    public function context(): array
    {
        return array_filter(
            [...parent::context(), 'required_permission' => $this->requiredPermission],
            static fn (mixed $value): bool => $value !== null,
        );
    }
}
