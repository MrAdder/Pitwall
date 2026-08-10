<?php

declare(strict_types=1);

namespace App\Integrations\MicrosoftGraph\GraphClient\Exceptions;

/**
 * Graph rejected the request itself (400, 409, 412, 422...).
 *
 * These are our fault or the caller's, not Microsoft's, and retrying the same
 * request will fail identically. Microsoft's own message is surfaced here
 * because for this class of error it is usually the most useful thing we have:
 * "property cannot be updated for a directory-synchronised user", for example.
 */
final class GraphRequestRejected extends GraphException
{
    public function userMessage(): string
    {
        $reason = trim((string) $this->graphMessage());

        if ($reason === '') {
            return 'Microsoft Graph rejected the request.';
        }

        return 'Microsoft Graph rejected the request. Reason: '.rtrim($reason, '.').'.';
    }

    public function errorKey(): string
    {
        return 'graph_request_rejected';
    }
}
