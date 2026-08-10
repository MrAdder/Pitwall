<?php

declare(strict_types=1);

namespace App\Integrations\MicrosoftGraph\Authentication;

use App\Integrations\MicrosoftGraph\GraphClient\Exceptions\GraphAuthenticationFailed;
use App\Models\Tenant;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Cache;

/**
 * Acquires application (app-only) access tokens using the OAuth 2.0 client
 * credentials flow against each customer's Entra tenant.
 *
 * The platform is registered once as a multi-tenant application. A customer
 * grants admin consent to that registration, after which we can request tokens
 * for their directory using our own client id and secret. No customer
 * credential is ever held, and no user password is ever seen.
 *
 * Tokens are cached per tenant. The cache key is derived from the tenant's
 * Microsoft directory id so two platform tenants pointing at the same
 * directory cannot collide, and the cached value is encrypted because a
 * bearer token in a shared Redis is a bearer token an attacker can use.
 *
 * @see https://learn.microsoft.com/entra/identity-platform/v2-oauth2-client-creds-grant-flow
 */
final class ClientCredentialsTokenProvider implements TokenProvider
{
    public function __construct(
        private readonly HttpFactory $http,
    ) {}

    public function tokenFor(Tenant $tenant): AccessToken
    {
        $cached = $this->readFromCache($tenant);

        if ($cached instanceof AccessToken && ! $cached->isExpired()) {
            return $cached;
        }

        $token = $this->requestToken($tenant);

        $ttl = $token->secondsUntilRefresh();

        if ($ttl > 0) {
            $this->cache()->put($this->cacheKey($tenant), [
                'token' => encrypt($token->value()),
                'expires_at' => $token->expiresAt->getTimestamp(),
            ], $ttl);
        }

        return $token;
    }

    public function forget(Tenant $tenant): void
    {
        $this->cache()->forget($this->cacheKey($tenant));
    }

    private function requestToken(Tenant $tenant): AccessToken
    {
        $clientId = (string) config('graph.client_id');
        $clientSecret = (string) config('graph.client_secret');

        if ($clientId === '' || $clientSecret === '') {
            throw new GraphAuthenticationFailed(
                method: 'POST',
                path: 'oauth2/v2.0/token',
                status: 0,
                errorCode: 'application_not_configured',
                graphMessage: 'MS_GRAPH_CLIENT_ID and MS_GRAPH_CLIENT_SECRET are not set.',
            );
        }

        $endpoint = sprintf(
            '%s/%s/oauth2/v2.0/token',
            rtrim((string) config('graph.login_base_url'), '/'),
            $tenant->microsoft_tenant_id,
        );

        try {
            $response = $this->http
                ->asForm()
                ->connectTimeout((int) config('graph.http.connect_timeout'))
                ->timeout((int) config('graph.http.timeout'))
                ->post($endpoint, [
                    'grant_type' => 'client_credentials',
                    'client_id' => $clientId,
                    'client_secret' => $clientSecret,
                    'scope' => (string) config('graph.client_credentials_scope'),
                ]);
        } catch (ConnectionException $e) {
            // The identity platform was unreachable. Distinct from a rejected
            // credential, but the caller's recovery is the same: no Graph call
            // can be made right now.
            throw new GraphAuthenticationFailed(
                method: 'POST',
                path: 'oauth2/v2.0/token',
                status: 0,
                errorCode: 'connection_failed',
                graphMessage: 'Could not reach the Microsoft identity platform.',
                previous: $e,
            );
        }

        if ($response->failed()) {
            // The token endpoint returns `error` / `error_description`, not the
            // Graph error envelope. The description can be long and contains a
            // correlation id, so it is kept for logs but not shown to users.
            throw new GraphAuthenticationFailed(
                method: 'POST',
                path: 'oauth2/v2.0/token',
                status: $response->status(),
                errorCode: (string) $response->json('error', 'token_request_failed'),
                graphMessage: (string) $response->json('error_description', ''),
            );
        }

        $accessToken = (string) $response->json('access_token', '');
        $expiresIn = (int) $response->json('expires_in', 0);

        if ($accessToken === '' || $expiresIn <= 0) {
            throw new GraphAuthenticationFailed(
                method: 'POST',
                path: 'oauth2/v2.0/token',
                status: $response->status(),
                errorCode: 'malformed_token_response',
                graphMessage: 'The token response did not contain a usable access token.',
            );
        }

        return new AccessToken(
            $accessToken,
            CarbonImmutable::now()->addSeconds($expiresIn),
        );
    }

    private function readFromCache(Tenant $tenant): ?AccessToken
    {
        $cached = $this->cache()->get($this->cacheKey($tenant));

        if (! is_array($cached) || ! isset($cached['token'], $cached['expires_at'])) {
            return null;
        }

        try {
            $token = decrypt($cached['token']);
        } catch (\Throwable) {
            // APP_KEY rotated, or the entry was tampered with. Re-acquire.
            $this->forget($tenant);

            return null;
        }

        return new AccessToken(
            $token,
            CarbonImmutable::createFromTimestamp($cached['expires_at']),
        );
    }

    private function cacheKey(Tenant $tenant): string
    {
        return 'graph:token:'.$tenant->microsoft_tenant_id;
    }

    private function cache(): CacheRepository
    {
        return Cache::store(config('graph.token_cache.store'));
    }
}
