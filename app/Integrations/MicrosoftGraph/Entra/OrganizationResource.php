<?php

declare(strict_types=1);

namespace App\Integrations\MicrosoftGraph\Entra;

use App\Integrations\MicrosoftGraph\GraphClient\GraphClient;
use App\Integrations\MicrosoftGraph\GraphClient\GraphRequestOptions;
use App\Integrations\MicrosoftGraph\GraphPermission;

/**
 * Microsoft Graph `/organization`.
 *
 * Used to confirm a newly consented tenant is reachable and to read back its
 * real name and verified domains, rather than trusting what the person
 * connecting it typed.
 *
 * @see https://learn.microsoft.com/graph/api/resources/organization
 */
final readonly class OrganizationResource
{
    public function __construct(
        private GraphClient $client,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function profile(): array
    {
        $response = $this->client->get('organization', [
            '$select' => 'id,displayName,verifiedDomains,tenantType,createdDateTime',
        ], GraphRequestOptions::requiring(GraphPermission::OrganizationRead));

        return $response->items()[0] ?? [];
    }

    /**
     * The tenant's primary verified domain, e.g. contoso.onmicrosoft.com.
     *
     * @param  array<string, mixed>  $profile
     */
    public static function defaultDomain(array $profile): ?string
    {
        foreach ($profile['verifiedDomains'] ?? [] as $domain) {
            if (($domain['isDefault'] ?? false) === true && isset($domain['name'])) {
                return (string) $domain['name'];
            }
        }

        return null;
    }
}
