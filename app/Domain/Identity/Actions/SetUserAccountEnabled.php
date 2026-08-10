<?php

declare(strict_types=1);

namespace App\Domain\Identity\Actions;

use App\Domain\Audit\AuditAction;
use App\Domain\Audit\AuditEntry;
use App\Domain\Audit\AuditLogger;
use App\Integrations\MicrosoftGraph\GraphClientFactory;
use App\Models\EntraUser;

/**
 * Enables or disables an Entra ID user account.
 *
 * Disabling is the platform's answer to "offboard this person": it stops
 * sign-in immediately and is reversible. Deletion is not offered at all, on
 * purpose — Entra's own 30-day recycle bin is a better tool for it, and an
 * irreversible action does not belong behind a button in a list view.
 *
 * Microsoft is written to first. The local row is only updated once Graph has
 * accepted the change, so a failure can never leave the cache claiming
 * something the directory does not agree with.
 */
final readonly class SetUserAccountEnabled
{
    public function __construct(
        private GraphClientFactory $graph,
        private AuditLogger $audit,
    ) {}

    public function handle(EntraUser $user, bool $enabled): EntraUser
    {
        $entry = new AuditEntry(
            action: $enabled ? AuditAction::UserEnabled : AuditAction::UserDisabled,
            resourceType: 'user',
            resourceId: $user->id,
            resourceMicrosoftId: $user->microsoft_id,
            resourceLabel: $user->user_principal_name ?? $user->display_name,
            previousState: ['account_enabled' => $user->account_enabled],
            newState: ['account_enabled' => $enabled],
        );

        return $this->audit->around($entry, function () use ($user, $enabled): EntraUser {
            $this->graph->users()->setAccountEnabled($user->microsoft_id, $enabled);

            $user->forceFill([
                'account_enabled' => $enabled,
                'synced_at' => now(),
            ])->save();

            return $user;
        });
    }
}
