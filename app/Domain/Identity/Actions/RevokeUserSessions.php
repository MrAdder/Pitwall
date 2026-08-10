<?php

declare(strict_types=1);

namespace App\Domain\Identity\Actions;

use App\Domain\Audit\AuditAction;
use App\Domain\Audit\AuditEntry;
use App\Domain\Audit\AuditLogger;
use App\Integrations\MicrosoftGraph\GraphClientFactory;
use App\Models\EntraUser;

/**
 * Invalidates a user's refresh tokens and browser sessions.
 *
 * The first thing to do when an account may be compromised, and the reason it
 * is a first-class action rather than something buried under user properties.
 *
 * It is not an instant lockout: access tokens already issued stay valid until
 * they expire, typically within an hour. Callers surface that caveat instead
 * of implying the user is out immediately.
 */
final readonly class RevokeUserSessions
{
    public function __construct(
        private GraphClientFactory $graph,
        private AuditLogger $audit,
    ) {}

    public function handle(EntraUser $user): void
    {
        $entry = new AuditEntry(
            action: AuditAction::UserSessionsRevoked,
            resourceType: 'user',
            resourceId: $user->id,
            resourceMicrosoftId: $user->microsoft_id,
            resourceLabel: $user->user_principal_name ?? $user->display_name,
        );

        $this->audit->around($entry, function () use ($user): void {
            $this->graph->users()->revokeSignInSessions($user->microsoft_id);
        });
    }
}
