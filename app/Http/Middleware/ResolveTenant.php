<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\Tenancy\TenantContext;
use App\Models\Tenant;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Establishes the tenant for the request, and proves the caller may access it.
 *
 * This is the only place a tenant enters {@see TenantContext} during a request,
 * and it does so only after membership has been verified. Everything
 * downstream — every Eloquent query, every Graph client — inherits that
 * decision, so there is one place to get tenant isolation right.
 *
 * A caller who is not a member gets 404, not 403. Distinguishing the two would
 * confirm that a given tenant exists on the platform, which is not something a
 * stranger should be able to learn.
 */
final class ResolveTenant
{
    public function __construct(
        private readonly TenantContext $context,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User) {
            abort(401);
        }

        $tenant = $this->tenantFrom($request);

        if (! $tenant instanceof Tenant) {
            throw new NotFoundHttpException;
        }

        // Membership is the whole basis of access. No membership row, no
        // tenant, regardless of the caller's Entra directory roles.
        $membership = $user->membershipFor($tenant);

        if ($membership === null) {
            throw new NotFoundHttpException;
        }

        $this->context->set($tenant);

        $request->attributes->set('tenant', $tenant);
        $request->attributes->set('membership', $membership);

        return $next($request);
    }

    /**
     * The tenant comes from the route (/api/tenants/{tenant}/...) or, for
     * routes that are not tenant-prefixed, from the session's current tenant.
     */
    private function tenantFrom(Request $request): ?Tenant
    {
        $routeTenant = $request->route('tenant');

        if ($routeTenant instanceof Tenant) {
            return $routeTenant;
        }

        if (is_string($routeTenant) && $routeTenant !== '') {
            return Tenant::where('slug', $routeTenant)->first();
        }

        $sessionTenantId = $request->session()->get('current_tenant_id');

        return is_string($sessionTenantId)
            ? Tenant::find($sessionTenantId)
            : null;
    }
}
