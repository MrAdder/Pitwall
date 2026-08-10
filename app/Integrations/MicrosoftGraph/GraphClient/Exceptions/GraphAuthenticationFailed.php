<?php

declare(strict_types=1);

namespace App\Integrations\MicrosoftGraph\GraphClient\Exceptions;

/**
 * We could not obtain a usable access token, or Graph returned 401.
 *
 * Distinct from a permission failure: here the tenant's consent has been
 * revoked, the application secret has expired, or the tenant no longer exists.
 * None of those are fixed by retrying, so the tenant is marked degraded and an
 * owner is told to reconnect.
 */
final class GraphAuthenticationFailed extends GraphException
{
    public function userMessage(): string
    {
        return 'The connection to this Microsoft 365 tenant is no longer valid. '
            .'An owner needs to reconnect the tenant and grant admin consent again.';
    }

    public function errorKey(): string
    {
        return 'graph_authentication_failed';
    }
}
