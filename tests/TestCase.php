<?php

declare(strict_types=1);

namespace Tests;

use App\Domain\Access\Role;
use App\Domain\Tenancy\TenantContext;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * A tenant with one member holding the given role.
     *
     * @return array{Tenant, User}
     */
    protected function tenantWithMember(Role $role = Role::Administrator): array
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create();

        TenantMembership::create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'role' => $role,
            'accepted_at' => now(),
        ]);

        return [$tenant, $user];
    }

    /**
     * Run a callback inside a tenant context.
     *
     * Needed whenever a test creates tenant-owned records directly, since the
     * tenant scope refuses to operate without a resolved tenant.
     *
     * @template TReturn
     *
     * @param  \Closure(): TReturn  $callback
     * @return TReturn
     */
    protected function asTenant(Tenant $tenant, \Closure $callback): mixed
    {
        return app(TenantContext::class)->run($tenant, $callback);
    }
}
