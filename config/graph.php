<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Microsoft Identity Platform
    |--------------------------------------------------------------------------
    |
    | The application is registered once as a multi-tenant Entra ID application.
    | Each customer tenant grants admin consent to that single registration, so
    | the client id / secret below belong to the platform, never to a customer.
    |
    | The client secret must never be exposed to the frontend.
    |
    */

    'client_id' => env('MS_GRAPH_CLIENT_ID'),
    'client_secret' => env('MS_GRAPH_CLIENT_SECRET'),

    'login_base_url' => env('MS_LOGIN_BASE_URL', 'https://login.microsoftonline.com'),

    /*
    | Redirect URIs registered against the Entra application.
    */
    'redirect_uri' => env('MS_GRAPH_REDIRECT_URI', env('APP_URL').'/auth/microsoft/callback'),
    'consent_redirect_uri' => env('MS_GRAPH_CONSENT_REDIRECT_URI', env('APP_URL').'/auth/microsoft/consent/callback'),

    /*
    | Scopes requested during interactive sign-in (delegated). Sign-in only
    | identifies the administrator; all data access uses application
    | permissions granted through admin consent.
    */
    'sign_in_scopes' => ['openid', 'profile', 'email', 'offline_access', 'User.Read'],

    /*
    | Scope used for the client credentials flow. Application permissions are
    | fixed at consent time, so ".default" is the only valid value.
    */
    'client_credentials_scope' => 'https://graph.microsoft.com/.default',

    /*
    |--------------------------------------------------------------------------
    | Microsoft Graph
    |--------------------------------------------------------------------------
    */

    'base_url' => env('MS_GRAPH_BASE_URL', 'https://graph.microsoft.com'),

    /*
    | Default API version. Prefer v1.0. Individual resources may opt into beta
    | explicitly, and every beta usage must be documented in
    | docs/graph-permissions.md with the reason it cannot use v1.0.
    */
    'version' => env('MS_GRAPH_VERSION', 'v1.0'),

    /*
    |--------------------------------------------------------------------------
    | Reliability
    |--------------------------------------------------------------------------
    |
    | Graph throttles aggressively and returns transient failures. Every request
    | goes through exponential backoff with jitter, and Retry-After is always
    | honoured when Microsoft supplies it.
    |
    */

    'http' => [
        'timeout' => (int) env('MS_GRAPH_TIMEOUT', 30),
        'connect_timeout' => (int) env('MS_GRAPH_CONNECT_TIMEOUT', 10),
    ],

    'retry' => [
        'max_attempts' => (int) env('MS_GRAPH_RETRY_ATTEMPTS', 5),

        // Base delay in milliseconds; doubled on each attempt.
        'base_delay_ms' => (int) env('MS_GRAPH_RETRY_BASE_DELAY_MS', 500),

        // Upper bound for a single backoff wait, in milliseconds.
        'max_delay_ms' => (int) env('MS_GRAPH_RETRY_MAX_DELAY_MS', 60_000),

        // Never sleep longer than this even if Retry-After asks us to; the job
        // is released back to the queue instead.
        'max_retry_after_seconds' => (int) env('MS_GRAPH_MAX_RETRY_AFTER', 120),

        'retryable_statuses' => [429, 500, 502, 503, 504],
    ],

    /*
    | Access tokens are cached per tenant and refreshed slightly before expiry
    | to avoid using a token that expires mid-request.
    */
    'token_cache' => [
        'store' => env('MS_GRAPH_TOKEN_CACHE_STORE'),
        'early_expiry_seconds' => 300,
    ],

    /*
    |--------------------------------------------------------------------------
    | Request Logging
    |--------------------------------------------------------------------------
    |
    | Logs method, path, status, duration and the Graph request id only.
    | Tokens, secrets and response bodies are never logged.
    |
    */

    'logging' => [
        'enabled' => (bool) env('MS_GRAPH_LOG_REQUESTS', false),
        'channel' => env('MS_GRAPH_LOG_CHANNEL', 'stack'),
        'slow_request_ms' => (int) env('MS_GRAPH_SLOW_REQUEST_MS', 5_000),
    ],

    /*
    |--------------------------------------------------------------------------
    | Pagination
    |--------------------------------------------------------------------------
    */

    'page_size' => [
        'users' => 999,
        'groups' => 999,
        'group_members' => 999,
        'managed_devices' => 1000,
        'default' => 100,
    ],

    /*
    | Hard ceiling on pages followed by a single paginated call. Prevents an
    | unexpected result set from running a worker indefinitely.
    */
    'max_pages' => (int) env('MS_GRAPH_MAX_PAGES', 5_000),

    /*
    |--------------------------------------------------------------------------
    | Synchronisation Intervals (minutes)
    |--------------------------------------------------------------------------
    */

    'sync' => [
        'users' => (int) env('SYNC_INTERVAL_USERS', 15),
        'groups' => (int) env('SYNC_INTERVAL_GROUPS', 15),
        'devices' => (int) env('SYNC_INTERVAL_DEVICES', 15),
        'applications' => (int) env('SYNC_INTERVAL_APPLICATIONS', 60),
        'policies' => (int) env('SYNC_INTERVAL_POLICIES', 60),
    ],
];
