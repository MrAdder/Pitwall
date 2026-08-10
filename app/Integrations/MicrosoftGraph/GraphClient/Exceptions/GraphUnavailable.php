<?php

declare(strict_types=1);

namespace App\Integrations\MicrosoftGraph\GraphClient\Exceptions;

/**
 * A transient failure that survived every retry: a 5xx from Graph, or a
 * connection/timeout error before Graph answered at all.
 */
final class GraphUnavailable extends GraphException
{
    public function isRetryable(): bool
    {
        return true;
    }

    public function userMessage(): string
    {
        return 'Microsoft Graph is not responding at the moment. The data shown may be out of date. Please try again shortly.';
    }

    public function errorKey(): string
    {
        return 'graph_unavailable';
    }
}
