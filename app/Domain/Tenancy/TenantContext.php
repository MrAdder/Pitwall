<?php

declare(strict_types=1);

namespace App\Domain\Tenancy;

use App\Domain\Tenancy\Exceptions\TenantContextMissing;
use App\Models\Tenant;
use Closure;

/**
 * Holds the tenant the current request or job is operating on.
 *
 * Registered as a singleton. Every tenant-owned query is constrained by
 * whatever is set here, so nothing may set it except:
 *
 *   - ResolveTenant middleware, after verifying the user's membership
 *   - a queued job, from the tenant id serialised into the job payload
 *   - a console command that explicitly targets a tenant
 *
 * When no tenant is set, tenant-owned queries throw rather than returning
 * every tenant's rows. Code that legitimately spans tenants must say so by
 * calling {@see self::withoutTenant()}.
 */
final class TenantContext
{
    private ?Tenant $tenant = null;

    /**
     * True while running inside withoutTenant(), which suppresses the tenant
     * scope entirely. Only platform-level code should ever see this.
     */
    private bool $unscoped = false;

    public function set(Tenant $tenant): void
    {
        $this->tenant = $tenant;
    }

    public function clear(): void
    {
        $this->tenant = null;
    }

    public function has(): bool
    {
        return $this->tenant instanceof Tenant;
    }

    public function isUnscoped(): bool
    {
        return $this->unscoped;
    }

    /**
     * The current tenant, or null when none is set. Prefer {@see self::tenant()}
     * anywhere a tenant is required, so a missing context fails loudly.
     */
    public function current(): ?Tenant
    {
        return $this->tenant;
    }

    /**
     * @throws TenantContextMissing when no tenant has been resolved.
     */
    public function tenant(): Tenant
    {
        if (! $this->tenant instanceof Tenant) {
            throw new TenantContextMissing;
        }

        return $this->tenant;
    }

    /**
     * @throws TenantContextMissing when no tenant has been resolved.
     */
    public function id(): string
    {
        return $this->tenant()->id;
    }

    /**
     * Run a callback with a specific tenant, restoring the previous context
     * afterwards. Used by jobs and console commands.
     *
     * @template TReturn
     *
     * @param  Closure(): TReturn  $callback
     * @return TReturn
     */
    public function run(Tenant $tenant, Closure $callback): mixed
    {
        $previousTenant = $this->tenant;
        $previousUnscoped = $this->unscoped;

        $this->tenant = $tenant;
        $this->unscoped = false;

        try {
            return $callback();
        } finally {
            $this->tenant = $previousTenant;
            $this->unscoped = $previousUnscoped;
        }
    }

    /**
     * Run a callback with the tenant scope suppressed.
     *
     * This deliberately reads as an alarming thing to write. Legitimate uses
     * are platform-level: the scheduler enumerating tenants, migrations, and
     * tenant provisioning. It must never be reachable from a request handled
     * on behalf of a tenant administrator.
     *
     * @template TReturn
     *
     * @param  Closure(): TReturn  $callback
     * @return TReturn
     */
    public function withoutTenant(Closure $callback): mixed
    {
        $previousTenant = $this->tenant;
        $previousUnscoped = $this->unscoped;

        $this->tenant = null;
        $this->unscoped = true;

        try {
            return $callback();
        } finally {
            $this->tenant = $previousTenant;
            $this->unscoped = $previousUnscoped;
        }
    }
}
