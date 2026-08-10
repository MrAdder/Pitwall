<?php

declare(strict_types=1);

namespace App\Integrations\MicrosoftGraph\Entra;

use App\Integrations\MicrosoftGraph\GraphClient\GraphClient;
use App\Integrations\MicrosoftGraph\GraphClient\GraphRequestOptions;
use App\Integrations\MicrosoftGraph\GraphPermission;
use Generator;

/**
 * Microsoft Graph reporting endpoints.
 */
final readonly class ReportsResource
{
    public function __construct(
        private GraphClient $client,
    ) {}

    /**
     * Authentication method registration state for every user.
     *
     * This report is how "MFA status" is determined. Registration is not the
     * same thing as enforcement: a user can be registered for MFA and still not
     * be required to use it, because that is decided by Conditional Access.
     * The UI therefore labels this "MFA registered", not "MFA enabled", and
     * never implies the account is protected.
     *
     * @return Generator<int, array<string, mixed>>
     *
     * @see https://learn.microsoft.com/graph/api/authenticationmethodsroot-list-userregistrationdetails
     */
    public function userRegistrationDetails(): Generator
    {
        return $this->client->paginate('reports/authenticationMethods/userRegistrationDetails', [
            '$top' => config('graph.page_size.default'),
        ], GraphRequestOptions::requiring(GraphPermission::AuditLogRead));
    }
}
