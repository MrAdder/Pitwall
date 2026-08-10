<?php

declare(strict_types=1);

namespace App\Integrations\MicrosoftGraph\Authentication;

use App\Integrations\MicrosoftGraph\GraphClient\Exceptions\GraphAuthenticationFailed;
use App\Models\Tenant;

/**
 * Supplies access tokens for calling Graph on behalf of a tenant.
 *
 * An interface so tests can swap in a stub without reaching the Microsoft
 * identity platform, and so a future certificate-credential implementation can
 * replace the shared-secret one without touching callers.
 */
interface TokenProvider
{
    /**
     * @throws GraphAuthenticationFailed when a token cannot be obtained.
     */
    public function tokenFor(Tenant $tenant): AccessToken;

    /**
     * Discard any cached token for this tenant, forcing the next call to
     * acquire a fresh one. Called after a 401, since a token can be revoked
     * before it expires.
     */
    public function forget(Tenant $tenant): void;
}
