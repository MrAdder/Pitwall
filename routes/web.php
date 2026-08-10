<?php

declare(strict_types=1);

use App\Http\Controllers\Auth\MicrosoftAuthController;
use App\Http\Controllers\Auth\TenantConsentController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web routes
|--------------------------------------------------------------------------
|
| Only the OAuth browser flows and the SPA shell live here. Everything else the
| application does is under routes/api.php.
|
*/

Route::prefix('auth/microsoft')->group(function (): void {
    Route::get('redirect', [MicrosoftAuthController::class, 'redirect'])
        ->middleware('guest')
        ->name('auth.microsoft.redirect');

    Route::get('callback', [MicrosoftAuthController::class, 'callback'])
        ->middleware('guest')
        ->name('auth.microsoft.callback');

    Route::post('logout', [MicrosoftAuthController::class, 'logout'])
        ->middleware('auth')
        ->name('auth.microsoft.logout');

    // Connecting a customer tenant is a separate, deliberate step from signing
    // in, and requires an already-authenticated platform user.
    Route::middleware('auth')->group(function (): void {
        Route::get('consent', [TenantConsentController::class, 'redirect'])
            ->name('auth.microsoft.consent');

        Route::get('consent/callback', [TenantConsentController::class, 'callback'])
            ->name('auth.microsoft.consent.callback');
    });
});

/*
 * The SPA shell. Every non-API path returns it so client-side routing works on
 * a hard refresh or a pasted deep link.
 */
Route::view('/{path?}', 'app')
    ->where('path', '^(?!api|auth|up).*$')
    ->name('spa');
