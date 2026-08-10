<?php

declare(strict_types=1);

use App\Http\Controllers\Api\AuditLogController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\EntraGroupController;
use App\Http\Controllers\Api\EntraUserActionController;
use App\Http\Controllers\Api\EntraUserController;
use App\Http\Controllers\Api\ManagedDeviceActionController;
use App\Http\Controllers\Api\ManagedDeviceController;
use App\Http\Controllers\Api\MeController;
use App\Http\Controllers\Api\SearchController;
use App\Http\Controllers\Api\SyncController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API routes
|--------------------------------------------------------------------------
|
| Everything except /me is nested under a tenant and passes through the
| `tenant` middleware, which resolves the tenant and verifies the caller's
| membership before anything else runs. Authorisation is then declared per
| route with `permission:`, so the route table is a readable statement of who
| can do what.
|
| Actions that reach Microsoft additionally require `tenant.connected`; read
| routes deliberately do not, so cached data stays visible on a tenant whose
| connection has broken.
|
*/

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('me', [MeController::class, 'show'])->name('me');

    Route::prefix('tenants/{tenant}')->middleware('tenant')->group(function (): void {
        Route::get('dashboard', DashboardController::class)
            ->middleware('permission:tenant.read')
            ->name('dashboard');

        Route::get('search', SearchController::class)
            ->middleware('permission:tenant.read')
            ->name('search');

        /*
         * Users
         */
        Route::middleware('permission:users.read')->group(function (): void {
            Route::get('users', [EntraUserController::class, 'index'])->name('users.index');
            Route::get('users/{user}', [EntraUserController::class, 'show'])->name('users.show');
        });

        Route::middleware('tenant.connected')->group(function (): void {
            Route::post('users/{user}/disable', [EntraUserActionController::class, 'disable'])
                ->middleware('permission:users.disable')
                ->name('users.disable');

            Route::post('users/{user}/enable', [EntraUserActionController::class, 'enable'])
                ->middleware('permission:users.disable')
                ->name('users.enable');

            Route::post('users/{user}/revoke-sessions', [EntraUserActionController::class, 'revokeSessions'])
                ->middleware('permission:users.revoke_sessions')
                ->name('users.revoke-sessions');
        });

        /*
         * Groups
         */
        Route::middleware('permission:groups.read')->group(function (): void {
            Route::get('groups', [EntraGroupController::class, 'index'])->name('groups.index');
            Route::get('groups/{group}', [EntraGroupController::class, 'show'])->name('groups.show');
            Route::get('groups/{group}/members', [EntraGroupController::class, 'members'])->name('groups.members');
        });

        /*
         * Devices
         */
        Route::middleware('permission:devices.read')->group(function (): void {
            Route::get('devices', [ManagedDeviceController::class, 'index'])->name('devices.index');
            Route::get('devices/{device}', [ManagedDeviceController::class, 'show'])->name('devices.show');
        });

        Route::post('devices/{device}/sync', [ManagedDeviceActionController::class, 'sync'])
            ->middleware(['tenant.connected', 'permission:devices.sync'])
            ->name('devices.sync');

        /*
         * Retire and wipe are not routed yet. The Graph calls exist, but an
         * irreversible action must not be reachable before the safe-change
         * preview and confirmation flow is built. See docs/roadmap.md.
         */

        /*
         * Audit
         */
        Route::get('audit', [AuditLogController::class, 'index'])
            ->middleware('permission:audit.read')
            ->name('audit.index');

        /*
         * Synchronisation
         */
        Route::get('sync', [SyncController::class, 'index'])
            ->middleware('permission:tenant.read')
            ->name('sync.index');

        Route::post('sync', [SyncController::class, 'store'])
            ->middleware(['tenant.connected', 'permission:devices.sync'])
            ->name('sync.store');
    });
});
