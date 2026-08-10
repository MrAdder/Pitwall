# Architecture

## Shape

```
                   Administrator (browser)
                            │
                    React SPA (session cookie)
                            │
                      Laravel API
        ┌───────────────────┼───────────────────┐
        │                   │                   │
   Tenant scope        RBAC gates          Audit logger
        │                   │                   │
        └───────────────────┼───────────────────┘
                            │
                 Microsoft Graph integration
                            │
                   Microsoft Graph (v1.0)
                            │
                  ┌─────────┴─────────┐
                Entra ID            Intune
```

Reads are served from a local projection of Microsoft data. Writes go to Graph
first and update the projection only once Microsoft has accepted them. Microsoft
is always the source of truth; the local database is a cache that exists to make
search, lists, dashboards and reporting fast.

## Directory layout

```
app/
├── Domain/                    Business logic, organised by area
│   ├── Access/                Roles and permissions
│   ├── Audit/                 Audit actions, entries, logger
│   ├── Auth/                  Microsoft OIDC sign-in
│   ├── Devices/               Device actions and enums
│   ├── Identity/              User actions
│   ├── Sync/                  Synchronisation enums
│   └── Tenancy/               Tenant context and status
│
├── Integrations/
│   └── MicrosoftGraph/        The only code that speaks HTTP to Microsoft
│       ├── Authentication/    Token acquisition and caching
│       ├── GraphClient/       Retry, throttling, pagination, error mapping
│       ├── Entra/             Users, groups, organisation, reports
│       └── Intune/            Managed devices
│
├── Http/                      Controllers, middleware, requests, resources
├── Jobs/                      Queued synchronisation and maintenance
└── Models/                    Eloquent models
```

Controllers stay thin. Anything with a decision in it lives in `Domain/`, and
anything that talks to Microsoft lives in `Integrations/`.

## Tenant isolation

The property the product cannot get wrong.

1. `ResolveTenant` middleware resolves the tenant from the route and verifies
   the caller's membership. It is the only place a tenant enters the context
   during a request, and non-members get **404**, not 403 — a 403 would confirm
   which tenants exist on the platform.
2. `TenantContext` holds that tenant for the request or job.
3. `TenantScope` constrains every query on a tenant-owned model to it.
4. `BelongsToTenant` stamps `tenant_id` on creation, so a caller cannot write a
   row into another customer's tenant.

With no tenant resolved, tenant-owned queries **throw** rather than returning
every tenant's rows. Code that legitimately spans tenants — the scheduler, tenant
provisioning — must say so explicitly via `TenantContext::withoutTenant()`.

Covered by `tests/Feature/TenantIsolationTest.php`.

## Authorisation

Application roles (`Owner`, `Administrator`, `Operator`, `Auditor`, `Read Only`)
are held per tenant on the membership row, so one person can be an Administrator
of one customer and Read Only on another. They are unrelated to Entra directory
roles: being a Global Administrator in Microsoft 365 grants nothing here.

Permissions are declared per route (`permission:devices.wipe`), so the route
table is a readable statement of who can do what. High-impact permissions are
separated from their read/write siblings — an Operator can sync a device but not
wipe one.

## Talking to Microsoft Graph

`GraphClient` is the only place in the application that makes an HTTP request to
Microsoft. One instance is bound to one tenant. It handles:

- token acquisition and attachment
- exponential backoff with **full jitter** — without jitter, a batch of sync jobs
  throttled together retries in lockstep and throttles itself again
- `Retry-After`, always honoured, with a ceiling beyond which the work is
  requeued rather than blocking a worker
- one silent re-authentication after a 401, since tokens can be revoked early
- `@odata.nextLink` pagination, as a generator so a 50,000-user directory is
  never held in memory at once
- mapping failures to typed exceptions that carry a message an administrator can
  act on, including the name of a missing Graph permission

Tokens are cached per tenant, encrypted at rest, and never logged. Request
logging records method, path, status, duration and Microsoft's request id —
never a token, a request body or a response body.

## Synchronisation

| Resource | Method | Why |
| --- | --- | --- |
| Users | Delta | `/users/delta` — routine runs transfer only changes |
| Groups | Delta | `/groups/delta` |
| Group membership | Full | No delta endpoint; one call per group, so it runs on its own cadence |
| Managed devices | Full | No delta endpoint for `managedDevices` |
| Authentication methods | Full | Report endpoint, no delta |

`SynchroniseAllTenants` runs every minute and decides which tenants are due, so
changing an interval takes effect without touching the schedule.

`last_successful_at` is deliberately separate from `completed_at`: a failed run
must never make stale data look freshly synchronised. Everything that displays
cached data displays its age alongside it.

## Audit

Every administrative action writes an entry through `AuditLogger`. Entries are
append-only — the model refuses updates and deletes outright, rather than
relying on convention. Retention pruning is the one place that bypasses this,
deliberately and in a single identifiable job.

Failures and denials are audited too. A failed device wipe is at least as
interesting as a successful one, and a pattern of an Operator trying to exceed
their role is exactly what an audit trail exists to show.

## What is deliberately not built yet

- **Device wipe and retire endpoints.** The Graph calls exist and are covered by
  their permissions, but they are not routed. An irreversible action must not
  ship before the safe-change preview and confirmation flow it belongs behind.
- **User deletion.** Not offered at all. Entra's own recycle bin is a better
  tool, and disabling covers the actual need.
- **Automation, policies, applications, reporting.** Phases 5–7. The directory
  structure anticipates them; there is no half-built implementation to work
  around.
