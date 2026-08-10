<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domain\Sync\SyncResource;
use App\Integrations\MicrosoftGraph\GraphClient\Exceptions\GraphPermissionDenied;
use App\Integrations\MicrosoftGraph\GraphClientFactory;
use App\Jobs\Concerns\SynchronisesTenantResource;
use App\Models\EntraUser;
use App\Models\SyncState;
use App\Models\Tenant;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Arr;

/**
 * Refreshes MFA registration state from the authentication methods report.
 *
 * "Registered" is not "enforced". A user can be registered for MFA and still
 * sign in without it, because enforcement is a Conditional Access decision this
 * report says nothing about. Every label derived from these columns says
 * "registered" for that reason.
 */
final class SynchroniseAuthenticationMethods implements ShouldQueue
{
    use Queueable, SynchronisesTenantResource;

    public int $tries = 3;

    public int $timeout = 1800;

    public function __construct(
        private readonly Tenant $syncTenant,
    ) {
        $this->onQueue('sync');
    }

    public function resource(): SyncResource
    {
        return SyncResource::AuthenticationMethods;
    }

    protected function tenant(): Tenant
    {
        return $this->syncTenant;
    }

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping('sync:auth-methods:'.$this->syncTenant->id))
                ->releaseAfter(60)
                ->expireAfter(1800),
        ];
    }

    /**
     * @return array{created: int, updated: int, deleted: int}
     */
    protected function synchronise(Tenant $tenant, SyncState $state): array
    {
        $updated = 0;

        try {
            foreach (app(GraphClientFactory::class)->reports($tenant)->userRegistrationDetails() as $item) {
                $microsoftId = Arr::get($item, 'id');

                if (! is_string($microsoftId) || $microsoftId === '') {
                    continue;
                }

                $updated += EntraUser::where('microsoft_id', $microsoftId)->update([
                    'is_mfa_registered' => Arr::get($item, 'isMfaRegistered'),
                    'is_mfa_capable' => Arr::get($item, 'isMfaCapable'),
                ]);
            }
        } catch (GraphPermissionDenied) {
            // This report needs AuditLog.Read.All and an Entra ID P1 licence.
            // A tenant without either should not have its whole sync marked
            // failed over an optional enrichment; the MFA columns simply stay
            // null and the UI shows "unknown" rather than a wrong answer.
            return ['created' => 0, 'updated' => 0, 'deleted' => 0];
        }

        return ['created' => 0, 'updated' => $updated, 'deleted' => 0];
    }
}
