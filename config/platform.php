<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Platform Identity
    |--------------------------------------------------------------------------
    */

    'name' => env('PLATFORM_NAME', 'PitWall IT Operations'),

    /*
    |--------------------------------------------------------------------------
    | Frontend
    |--------------------------------------------------------------------------
    |
    | The SPA is served from the same origin and authenticates with Sanctum's
    | cookie-based session guard, so no bearer token is ever stored in the
    | browser.
    |
    */

    'spa_url' => env('SPA_URL', env('APP_URL')),

    /*
    |--------------------------------------------------------------------------
    | High Impact Actions
    |--------------------------------------------------------------------------
    |
    | Actions listed here can never be executed in a single request. The client
    | must first request a preview, then confirm using the returned token.
    |
    | See app/Domain/SafeChange.
    |
    */

    'safe_change' => [
        'preview_ttl_seconds' => (int) env('SAFE_CHANGE_PREVIEW_TTL', 900),
    ],

    /*
    |--------------------------------------------------------------------------
    | Audit
    |--------------------------------------------------------------------------
    |
    | Audit records are append-only from the administrative interface. Retention
    | is enforced by a scheduled prune command, not by the application code that
    | writes them.
    |
    */

    'audit' => [
        'retention_days' => (int) env('AUDIT_RETENTION_DAYS', 730),
    ],

    /*
    |--------------------------------------------------------------------------
    | Staleness
    |--------------------------------------------------------------------------
    |
    | Cached Microsoft data older than this is surfaced in the UI as stale so an
    | administrator never mistakes it for live data.
    |
    */

    'stale_after_minutes' => (int) env('DATA_STALE_AFTER_MINUTES', 60),
];
