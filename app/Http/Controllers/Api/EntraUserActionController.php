<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domain\Identity\Actions\RevokeUserSessions;
use App\Domain\Identity\Actions\SetUserAccountEnabled;
use App\Http\Controllers\Controller;
use App\Http\Resources\EntraUserResource;
use App\Models\EntraUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Actions taken against an Entra ID user.
 *
 * Each is a separate endpoint behind its own permission, rather than a general
 * "update user" that decides what it is allowed to change from the body. That
 * keeps authorisation readable from the route table.
 */
final class EntraUserActionController extends Controller
{
    /**
     * Disable the account.
     *
     * Reversible, and the platform's alternative to deletion, which is not
     * offered at all.
     */
    public function disable(Request $request, EntraUser $user, SetUserAccountEnabled $action): JsonResponse
    {
        $this->guardDirectorySynced($user);

        $action->handle($user, enabled: false);

        return response()->json([
            'data' => (new EntraUserResource($user->refresh()))->toArray($request),
            'message' => 'The account has been disabled. Existing sessions may remain valid for up to an hour unless they are also revoked.',
        ]);
    }

    public function enable(Request $request, EntraUser $user, SetUserAccountEnabled $action): JsonResponse
    {
        $this->guardDirectorySynced($user);

        $action->handle($user, enabled: true);

        return response()->json([
            'data' => (new EntraUserResource($user->refresh()))->toArray($request),
            'message' => 'The account has been enabled.',
        ]);
    }

    /**
     * Revoke refresh tokens and sign the user out of their sessions.
     */
    public function revokeSessions(Request $request, EntraUser $user, RevokeUserSessions $action): JsonResponse
    {
        $action->handle($user);

        return response()->json([
            'message' => 'Sign-in sessions have been revoked. Access tokens already issued remain valid until they expire, typically within an hour.',
        ]);
    }

    /**
     * Accounts mastered on-premises cannot have accountEnabled changed in the
     * cloud. Graph would reject it, so the request is refused here with an
     * explanation rather than passed on to fail.
     */
    private function guardDirectorySynced(EntraUser $user): void
    {
        abort_if(
            $user->isDirectorySynced(),
            422,
            'This account is synchronised from on-premises Active Directory. Its enabled state must be changed there, not in Microsoft 365.',
        );
    }
}
