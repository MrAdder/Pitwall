<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks routes that require live Graph access when the tenant has no usable
 * connection.
 *
 * Applied to action routes only. Read routes stay available on a disconnected
 * tenant so an administrator can still see the last synchronised data and
 * understand what state things were left in.
 */
final class EnsureTenantIsConnected
{
    public function __construct(
        private readonly TenantContext $context,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $tenant = $this->context->tenant();

        if (! $tenant->isConnected()) {
            return response()->json([
                'message' => 'This tenant is not connected to Microsoft 365.',
                'error' => [
                    'key' => 'tenant_not_connected',
                    'detail' => 'An owner needs to reconnect the tenant and grant admin consent before changes can be made.',
                    'tenant_status' => $tenant->status->value,
                ],
            ], 409);
        }

        return $next($request);
    }
}
