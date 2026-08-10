<?php

use App\Domain\Sync\SyncResource;
use App\Http\Middleware\EnsureTenantIsConnected;
use App\Http\Middleware\RequirePermission;
use App\Http\Middleware\ResolveTenant;
use App\Integrations\MicrosoftGraph\GraphClient\Exceptions\GraphException;
use App\Integrations\MicrosoftGraph\GraphClient\Exceptions\GraphPermissionDenied;
use App\Jobs\PruneAuditLogs;
use App\Jobs\SynchroniseAllTenants;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Schedule;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // The SPA authenticates with session cookies rather than a bearer
        // token, so no credential is ever readable from JavaScript.
        $middleware->statefulApi();

        $middleware->alias([
            'tenant' => ResolveTenant::class,
            'tenant.connected' => EnsureTenantIsConnected::class,
            'permission' => RequirePermission::class,
        ]);

        // Route model binding resolves {user}, {device} and friends through the
        // tenant scope, so the tenant must already be established when it runs.
        // Without this, SubstituteBindings executes first and every bound model
        // query hits a missing tenant context.
        $middleware->prependToPriorityList(SubstituteBindings::class, ResolveTenant::class);
    })
    ->withSchedule(function (Schedule $schedule): void {
        // Each resource is scheduled at its own configured interval. The job
        // itself decides which tenants are actually due, so changing an
        // interval does not require touching the schedule.
        foreach (SyncResource::cases() as $resource) {
            $schedule->job(new SynchroniseAllTenants($resource))
                ->everyMinute()
                ->withoutOverlapping()
                ->name('sync:'.$resource->value)
                ->onOneServer();
        }

        $schedule->job(new PruneAuditLogs)->dailyAt('03:00')->onOneServer();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        // Graph failures are translated into an explanation an administrator
        // can act on. Microsoft's raw error body never reaches the client:
        // it can contain object ids, request paths and internal detail.
        $exceptions->render(function (GraphException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'message' => $e->userMessage(),
                'error' => array_filter([
                    'key' => $e->errorKey(),
                    'graph_error_code' => $e->graphErrorCode(),
                    'graph_request_id' => $e->graphRequestId(),
                    'required_permission' => $e instanceof GraphPermissionDenied
                        ? $e->requiredPermission()
                        : null,
                    'retryable' => $e->isRetryable(),
                ], static fn (mixed $v): bool => $v !== null),
            ], match (true) {
                $e->status() === 404 => 404,
                $e->status() === 429 => 429,
                $e->status() >= 500, $e->status() === 0 => 503,
                default => 502,
            });
        });
    })->create();
