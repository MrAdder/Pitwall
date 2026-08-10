<?php

declare(strict_types=1);

namespace App\Domain\Auth;

use App\Domain\Auth\Exceptions\MicrosoftSignInFailed;
use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Throwable;

/**
 * Interactive administrator sign-in via OpenID Connect against the Microsoft
 * identity platform.
 *
 * This flow establishes *who is using the platform*. It does not grant access
 * to any customer data: that comes from tenant membership, and the data itself
 * is read with application permissions granted by admin consent. Signing in
 * with a Microsoft account therefore proves identity and nothing more.
 *
 * The id token is verified properly — signature against Microsoft's published
 * keys, issuer, audience, and nonce — because an unverified token is just a
 * string the browser handed us.
 *
 * @see https://learn.microsoft.com/entra/identity-platform/v2-protocols-oidc
 */
final readonly class MicrosoftOidc
{
    /**
     * The multi-tenant sign-in authority. `organizations` accepts work and
     * school accounts from any directory and rejects personal accounts, which
     * have no business administering a tenant.
     */
    private const Authority = 'organizations';

    public function __construct(
        private HttpFactory $http,
    ) {}

    /**
     * Build the URL to send the administrator's browser to.
     *
     * @param  string  $state  Opaque CSRF value, stored in the session and checked on return.
     * @param  string  $nonce  Replay guard, echoed inside the id token.
     */
    public function authorizationUrl(string $state, string $nonce): string
    {
        return sprintf('%s/%s/oauth2/v2.0/authorize?%s',
            rtrim((string) config('graph.login_base_url'), '/'),
            self::Authority,
            http_build_query([
                'client_id' => config('graph.client_id'),
                'response_type' => 'code',
                'redirect_uri' => config('graph.redirect_uri'),
                'response_mode' => 'query',
                'scope' => implode(' ', (array) config('graph.sign_in_scopes')),
                'state' => $state,
                'nonce' => $nonce,
            ]),
        );
    }

    /**
     * The admin consent URL for connecting a customer tenant.
     *
     * Separate from sign-in on purpose. Consent is what actually grants this
     * platform standing application permissions across a customer's directory,
     * and it requires a Global Administrator of that tenant. Presenting it as
     * a distinct, deliberate step is the difference between an administrator
     * knowing what they authorised and not.
     *
     * @see https://learn.microsoft.com/entra/identity-platform/v2-admin-consent
     */
    public function adminConsentUrl(string $state, ?string $microsoftTenantId = null): string
    {
        return sprintf('%s/%s/adminconsent?%s',
            rtrim((string) config('graph.login_base_url'), '/'),
            $microsoftTenantId ?: 'common',
            http_build_query([
                'client_id' => config('graph.client_id'),
                'redirect_uri' => config('graph.consent_redirect_uri'),
                'state' => $state,
            ]),
        );
    }

    /**
     * Exchange the authorization code for tokens and return the verified
     * identity claims.
     *
     *
     * @throws MicrosoftSignInFailed
     */
    public function exchangeCode(string $code, string $expectedNonce): MicrosoftIdentity
    {
        $response = $this->http
            ->asForm()
            ->connectTimeout((int) config('graph.http.connect_timeout'))
            ->timeout((int) config('graph.http.timeout'))
            ->post(sprintf('%s/%s/oauth2/v2.0/token',
                rtrim((string) config('graph.login_base_url'), '/'),
                self::Authority,
            ), [
                'grant_type' => 'authorization_code',
                'client_id' => config('graph.client_id'),
                'client_secret' => config('graph.client_secret'),
                'code' => $code,
                'redirect_uri' => config('graph.redirect_uri'),
                'scope' => implode(' ', (array) config('graph.sign_in_scopes')),
            ]);

        if ($response->failed()) {
            throw new MicrosoftSignInFailed(
                'Microsoft rejected the sign-in code exchange.',
                (string) $response->json('error', 'token_exchange_failed'),
            );
        }

        $idToken = (string) $response->json('id_token', '');

        if ($idToken === '') {
            throw new MicrosoftSignInFailed('Microsoft did not return an id token.', 'missing_id_token');
        }

        return $this->verifyIdToken($idToken, $expectedNonce);
    }

    /**
     * Verify an id token's signature and claims.
     *
     * @throws MicrosoftSignInFailed
     */
    private function verifyIdToken(string $idToken, string $expectedNonce): MicrosoftIdentity
    {
        try {
            $claims = (array) JWT::decode($idToken, JWK::parseKeySet($this->signingKeys()));
        } catch (Throwable $e) {
            throw new MicrosoftSignInFailed('The Microsoft id token could not be verified.', 'invalid_id_token', $e);
        }

        $tenantId = (string) ($claims['tid'] ?? '');
        $objectId = (string) ($claims['oid'] ?? '');

        if ($tenantId === '' || $objectId === '') {
            throw new MicrosoftSignInFailed('The Microsoft id token is missing required claims.', 'incomplete_id_token');
        }

        // Nonce ties this token to the authorization request we started, so a
        // token captured elsewhere cannot be replayed here.
        if (! hash_equals($expectedNonce, (string) ($claims['nonce'] ?? ''))) {
            throw new MicrosoftSignInFailed('The Microsoft id token nonce did not match.', 'nonce_mismatch');
        }

        if (! hash_equals((string) config('graph.client_id'), (string) ($claims['aud'] ?? ''))) {
            throw new MicrosoftSignInFailed('The Microsoft id token was issued for a different application.', 'audience_mismatch');
        }

        // In the multi-tenant case the issuer is tenant-specific, so it is
        // checked against the tenant the token itself claims.
        $expectedIssuer = sprintf('%s/%s/v2.0', rtrim((string) config('graph.login_base_url'), '/'), $tenantId);

        if (! hash_equals($expectedIssuer, (string) ($claims['iss'] ?? ''))) {
            throw new MicrosoftSignInFailed('The Microsoft id token issuer was not recognised.', 'issuer_mismatch');
        }

        return new MicrosoftIdentity(
            objectId: $objectId,
            tenantId: $tenantId,
            email: (string) ($claims['preferred_username'] ?? $claims['email'] ?? ''),
            name: (string) ($claims['name'] ?? ''),
        );
    }

    /**
     * Microsoft's current signing keys.
     *
     * Cached for an hour: they rotate, so pinning them would break sign-in, and
     * fetching them on every request would put the identity platform in the
     * critical path of each login.
     *
     * @return array<string, mixed>
     */
    private function signingKeys(): array
    {
        return Cache::remember('microsoft:jwks', now()->addHour(), function (): array {
            $response = $this->http
                ->timeout((int) config('graph.http.timeout'))
                ->get(sprintf('%s/%s/discovery/v2.0/keys',
                    rtrim((string) config('graph.login_base_url'), '/'),
                    self::Authority,
                ));

            if ($response->failed() || ! is_array($response->json('keys'))) {
                throw new MicrosoftSignInFailed('Could not retrieve Microsoft signing keys.', 'jwks_unavailable');
            }

            return $response->json();
        });
    }

    /**
     * A random value for the state and nonce parameters.
     */
    public static function randomValue(): string
    {
        return Str::random(40);
    }
}
