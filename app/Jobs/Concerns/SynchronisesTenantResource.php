<?php

declare(strict_types=1);

namespace App\Jobs\Concerns;

use App\Domain\Sync\SyncResource;
use App\Domain\Sync\SyncStatus;
use App\Domain\Tenancy\TenantContext;
use App\Domain\Tenancy\TenantStatus;
use App\Integrations\MicrosoftGraph\GraphClient\Exceptions\GraphAuthenticationFailed;
use App\Integrations\MicrosoftGraph\GraphClient\Exceptions\GraphException;
use App\Integrations\MicrosoftGraph\GraphClient\Exceptions\GraphThrottled;
use App\Models\SyncState;
use App\Models\Tenant;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Shared machinery for the per-resource synchronisation jobs.
 *
 * Concrete jobs supply {@see self::resource()} and {@see self::synchronise()};
 * everything about bookkeeping, tenant scoping and Graph failure handling
 * lives here so each job is only the mapping logic that is actually unique.
 */
trait SynchronisesTenantResource
{
    abstract public function resource(): SyncResource;

    /**
     * Perform the synchronisation. Runs inside the tenant context.
     *
     * @return array{created?: int, updated?: int, deleted?: int}
     */
    abstract protected function synchronise(Tenant $tenant, SyncState $state): array;

    public function handle(TenantContext $context): void
    {
        $tenant = $this->tenant();

        if (! $tenant->isConnected()) {
            return;
        }

        $context->run($tenant, function () use ($tenant): void {
            $state = $this->markRunning($tenant);

            try {
                $counts = $this->synchronise($tenant, $state);

                $this->markSucceeded($state, $counts);
            } catch (GraphThrottled $e) {
                // Microsoft is rate limiting harder than a single request can
                // wait out. Give the tenant room and try the whole job later
                // rather than hammering it.
                $this->markFailed($state, $e);

                $this->release($e->retryAfterSeconds() ?? 300);
            } catch (GraphAuthenticationFailed $e) {
                // Consent revoked, secret expired, or the tenant is gone.
                // Retrying cannot help, so stop syncing and make the reason
                // visible on the tenant.
                $this->markFailed($state, $e);
                $this->markTenantDegraded($tenant, $e);

                $this->fail($e);
            } catch (Throwable $e) {
                $this->markFailed($state, $e);

                throw $e;
            }
        });
    }

    abstract protected function tenant(): Tenant;

    private function markRunning(Tenant $tenant): SyncState
    {
        $state = SyncState::firstOrNew([
            'tenant_id' => $tenant->id,
            'resource' => $this->resource(),
        ]);

        $state->fill([
            'status' => SyncStatus::Running,
            'started_at' => now(),
        ])->save();

        return $state;
    }

    /**
     * @param  array{created?: int, updated?: int, deleted?: int}  $counts
     */
    private function markSucceeded(SyncState $state, array $counts): void
    {
        $state->fill([
            'status' => SyncStatus::Idle,
            'completed_at' => now(),
            'last_successful_at' => now(),
            'records_created' => $counts['created'] ?? 0,
            'records_updated' => $counts['updated'] ?? 0,
            'records_deleted' => $counts['deleted'] ?? 0,
            'consecutive_failures' => 0,
            'error_code' => null,
            'error_message' => null,
        ])->save();
    }

    private function markFailed(SyncState $state, Throwable $e): void
    {
        // last_successful_at is deliberately untouched: a failed run must not
        // make stale cached data look freshly synchronised.
        $state->fill([
            'status' => SyncStatus::Failed,
            'completed_at' => now(),
            'consecutive_failures' => $state->consecutive_failures + 1,
            'error_code' => $e instanceof GraphException ? $e->graphErrorCode() : class_basename($e),
            'error_message' => Str::limit($e->getMessage(), 1000),
        ])->save();

        Log::warning('Tenant synchronisation failed.', [
            'tenant_id' => $state->tenant_id,
            'resource' => $this->resource()->value,
            'exception' => class_basename($e),
            ...($e instanceof GraphException ? $e->context() : []),
        ]);
    }

    private function markTenantDegraded(Tenant $tenant, GraphException $e): void
    {
        $tenant->forceFill([
            'status' => TenantStatus::Degraded,
            'connection_error_code' => $e->graphErrorCode() ?? 'authentication_failed',
            'connection_error_message' => $e->userMessage(),
            'connection_checked_at' => now(),
        ])->save();
    }
}
