<?php

declare(strict_types=1);

namespace App\Integrations\MicrosoftGraph\GraphClient\Exceptions;

/**
 * Graph returned 404.
 *
 * Usually means the object was deleted in Microsoft 365 since our last sync,
 * so the cached row the administrator clicked no longer has a counterpart.
 */
final class GraphResourceNotFound extends GraphException
{
    public function userMessage(): string
    {
        return 'This object no longer exists in Microsoft 365. It may have been deleted since the last synchronisation.';
    }

    public function errorKey(): string
    {
        return 'graph_resource_not_found';
    }
}
