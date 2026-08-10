<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domain\Sync\SyncResource;
use App\Domain\Sync\SyncStatus;
use App\Domain\Tenancy\TenantContext;
use App\Domain\Tenancy\TenantStatus;
use App\Models\SyncState;
use App\Models\Tenant;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Scheduler entry point: dispatches a per-resource sync for every tenant that
 * is due.
 *
 * Runs every minute and decides for itself which tenants are due, so a change
 * to an interval takes effect without touching the schedule definition.
 *
 * This is one of the few places that legitimately reads across tenants, and it
 * says so explicitly rather than relying on an unscoped query slipping through.
 */
final class SynchroniseAllTenants implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly SyncResource $resource,
    ) {
        $this->onQueue('sync');
    }

    public function handle(TenantContext $context): void
    {
        $tenants = $context->withoutTenant(
            fn () => Tenant::query()
                ->whereIn('status', [TenantStatus::Active, TenantStatus::Degraded])
                ->get(),
        );

        foreach ($tenants as $tenant) {
            if (! $this->isDue($context, $tenant)) {
                continue;
            }

            $job = $this->jobClass();

            $job::dispatch($tenant);
        }
    }

    private function isDue(TenantContext $context, Tenant $tenant): bool
    {
        $state = $context->run($tenant, fn (): ?SyncState => SyncState::query()
            ->where('resource', $this->resource)
            ->first());

        if ($state === null || $state->last_successful_at === null) {
            return true;
        }

        // A run that is already in flight is not due again. WithoutOverlapping
        // on the concrete jobs is the real guard; this just avoids queueing
        // work that would immediately be released.
        if ($state->status === SyncStatus::Running) {
            return false;
        }

        return $state->last_successful_at->addMinutes($this->resource->intervalMinutes())->isPast();
    }

    /**
     * @return class-string<ShouldQueue>
     */
    private function jobClass(): string
    {
        return match ($this->resource) {
            SyncResource::Users => SynchroniseUsers::class,
            SyncResource::Groups => SynchroniseGroups::class,
            SyncResource::GroupMembers => SynchroniseGroupMembers::class,
            SyncResource::ManagedDevices => SynchroniseManagedDevices::class,
            SyncResource::AuthenticationMethods => SynchroniseAuthenticationMethods::class,
        };
    }
}
