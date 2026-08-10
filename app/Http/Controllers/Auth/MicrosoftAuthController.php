<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Domain\Auth\Exceptions\MicrosoftSignInFailed;
use App\Domain\Auth\MicrosoftIdentity;
use App\Domain\Auth\MicrosoftOidc;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Interactive sign-in with Microsoft.
 *
 * Browser redirects rather than API endpoints, because the OAuth flow is a
 * browser flow. The SPA calls /api/me afterwards to discover who it is.
 */
final class MicrosoftAuthController extends Controller
{
    public function __construct(
        private readonly MicrosoftOidc $oidc,
    ) {}

    /**
     * Start sign-in.
     */
    public function redirect(Request $request): RedirectResponse
    {
        $state = MicrosoftOidc::randomValue();
        $nonce = MicrosoftOidc::randomValue();

        $request->session()->put('microsoft_oauth_state', $state);
        $request->session()->put('microsoft_oauth_nonce', $nonce);

        return redirect()->away($this->oidc->authorizationUrl($state, $nonce));
    }

    /**
     * Handle the redirect back from Microsoft.
     */
    public function callback(Request $request): RedirectResponse
    {
        $expectedState = $request->session()->pull('microsoft_oauth_state');
        $expectedNonce = $request->session()->pull('microsoft_oauth_nonce');

        // A missing or mismatched state means this callback did not originate
        // from a sign-in we started.
        if (! is_string($expectedState) || ! hash_equals($expectedState, (string) $request->query('state'))) {
            return $this->failed('sign_in_state_mismatch');
        }

        if ($request->query('error') !== null) {
            // The user declined, or Entra refused. Neither is an application
            // error worth a stack trace.
            return $this->failed('sign_in_cancelled');
        }

        $code = (string) $request->query('code');

        if ($code === '' || ! is_string($expectedNonce)) {
            return $this->failed('sign_in_incomplete');
        }

        try {
            $identity = $this->oidc->exchangeCode($code, $expectedNonce);
        } catch (MicrosoftSignInFailed $e) {
            Log::warning('Microsoft sign-in failed.', ['reason' => $e->reason()]);

            return $this->failed('sign_in_failed');
        }

        $user = $this->userFor($identity);

        // Regenerating fixes the session id across the privilege change, so a
        // session id observed before sign-in is useless afterwards.
        $request->session()->regenerate();

        Auth::login($user, remember: false);

        $user->forceFill([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ])->save();

        return redirect()->intended('/');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }

    /**
     * Find or create the platform user behind a verified Microsoft identity.
     *
     * Matched on the object id, never the email address: a person's UPN can
     * change, and matching on it would either lock them out or, worse, attach
     * them to someone else's account after a rename.
     */
    private function userFor(MicrosoftIdentity $identity): User
    {
        $user = User::firstOrNew(['microsoft_object_id' => $identity->objectId]);

        $user->fill([
            'name' => $identity->displayName(),
            'email' => $identity->email,
            'microsoft_tenant_id' => $identity->tenantId,
        ]);

        if (! $user->exists) {
            $user->email_verified_at = now();
        }

        $user->save();

        return $user;
    }

    /**
     * Send the browser back to the SPA with a failure reason it can render.
     */
    private function failed(string $reason): RedirectResponse
    {
        return redirect('/login?error='.$reason);
    }
}
