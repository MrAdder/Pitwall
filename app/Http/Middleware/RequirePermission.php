<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\Access\Permission;
use App\Domain\Audit\AuditAction;
use App\Domain\Audit\AuditChannel;
use App\Domain\Audit\AuditEntry;
use App\Domain\Audit\AuditLogger;
use App\Domain\Tenancy\TenantContext;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Enforces a permission for the current tenant.
 *
 * Used as `permission:devices.wipe`. Runs after ResolveTenant, so a tenant and
 * a verified membership are already in place.
 *
 * Denials of high-impact permissions are written to the audit log. A pattern
 * of an Operator repeatedly trying to wipe devices is exactly the kind of thing
 * an audit trail exists to show.
 */
final class RequirePermission
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly AuditLogger $audit,
    ) {}

    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user();

        if (! $user instanceof User) {
            abort(401);
        }

        $tenant = $this->context->tenant();

        foreach ($permissions as $value) {
            $permission = Permission::tryFrom($value);

            if ($permission === null) {
                // A typo in a route definition must fail loudly at request
                // time rather than silently authorising everyone.
                throw new \InvalidArgumentException("Unknown permission [{$value}] on route ".$request->path().'.');
            }

            if ($user->hasPermissionFor($tenant, $permission)) {
                continue;
            }

            if ($permission->isHighImpact()) {
                $this->audit->denied(
                    new AuditEntry(
                        action: AuditAction::PermissionDenied,
                        resourceType: 'permission',
                        resourceId: $permission->value,
                        context: ['route' => $request->path(), 'method' => $request->method()],
                        channel: AuditChannel::Web,
                    ),
                    sprintf('Role %s does not grant %s.', $user->roleFor($tenant)?->value ?? 'none', $permission->value),
                );
            }

            abort(403, sprintf('Your role does not allow you to %s.', lcfirst($permission->label())));
        }

        return $next($request);
    }
}
