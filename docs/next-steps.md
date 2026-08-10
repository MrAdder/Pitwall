# Next steps

Working reference for picking this project back up. Ordered roughly by what
blocks what.

[roadmap.md](roadmap.md) covers the strategic phases. This file is the practical
"what do I do on Monday" list, including the loose ends left in the code as it
stands.

Current state: Phase 1 foundation + MVP surface. 32 tests passing, frontend
builds clean.

---

## 0a. Licensing

BUSL 1.1, fully filled in — Licensor is Daniel Peter John Green, contact
[dgreen03@gmail.com](mailto:dgreen03@gmail.com). Nothing blocking.

Two things for later, from [licensing.md](licensing.md):

- **Advance the Change Date when cutting a new version.** Each version converts
  four years after *its own* release. Leave the date at 2030-08-10 and a version
  shipped in 2028 arrives with only two years of cover.
- **Consider a dedicated licensing address** on a domain you own. The one in
  `LICENSE` is public and will be scraped, and it is the only route anyone has
  to buy a commercial licence.

---

## 0. Before anything runs against a real tenant

None of this is code — it is the setup gate, and nothing below can be tested
properly until it is done.

- [ ] **Register the multi-tenant Entra application.** One registration; customer
      tenants consent to it. Supported account types: *Accounts in any
      organizational directory*.
- [ ] **Add both redirect URIs.** `/auth/microsoft/callback` **and**
      `/auth/microsoft/consent/callback`. Sign-in and consent are separate flows
      and both must be registered, or consent fails after sign-in works.
- [ ] **Grant the application permissions** from
      [graph-permissions.md](graph-permissions.md). Consider omitting
      `DeviceManagementManagedDevices.PrivilegedOperations.All` until the
      safe-change system exists — nothing currently routed needs it.
- [ ] **Set `MS_GRAPH_CLIENT_ID` / `MS_GRAPH_CLIENT_SECRET`** in `.env`.
- [ ] **Note the secret's expiry date somewhere you will actually see it.** When
      it lapses, every tenant goes `degraded` at once and the cause is not
      obvious from the UI. A rotation reminder is worth more than it sounds.
- [ ] **Start MySQL and Redis** (`docker compose up -d`), then `php artisan migrate`.
- [ ] **Confirm the queue worker is running.** Nothing synchronises without it.
      `composer run dev` starts server, queue, scheduler, logs and Vite together.

**First real test:** connect a test tenant, wait for the first sync, and check
`GET /api/tenants/{slug}/sync` shows `last_successful_at` populated for users,
groups and devices.

---

## 1. Close the loose ends already in the code

These are places where something is half-wired. Each is small, and each is
currently a dead end a user can walk into.

### Sign-in activity is never populated

`UsersResource::signInActivity()` exists and is correct, but **nothing calls
it**. Consequences:

- `entra_users.last_sign_in_at` is always `NULL`
- the user detail page always shows "Last sign-in: never"
- `ListEntraUsersRequest` accepts `sort=last_sign_in_at`, which sorts by a
  column that is always null

Either wire it into a sync job or remove it from the sort allow-list and the UI.
Note it is one Graph call **per user**, so a naive loop will throttle a large
tenant hard — batch it, or accept a slower dedicated job with its own schedule.

### Licence SKUs are ids, not names

`SynchroniseUsers` stores raw `skuId` GUIDs in `assigned_license_skus`. The UI
shows a count, which is fine, but any licence *report* will be unreadable.

Fix: sync `/subscribedSkus` per tenant into a lookup table and resolve
`skuPartNumber` (e.g. `SPB` → "Microsoft 365 Business Premium"). Also needs a
display-name map, since the part numbers are not what administrators call them
either. `UsersResource::licenseDetails()` exists and is also currently uncalled.

### `member_count` counts users only

Nested groups, devices and service principals are not expanded, so the number
will not match what the Entra portal shows for a group containing other groups.
It is documented in the model and labelled "User members" in the UI — decide
whether that is good enough or whether nested expansion is needed.

### Group member sync assumes ordering

`SynchroniseGroupMembers` skips members that are not already in `entra_users`.
On a first-ever sync it can therefore run before users exist and record almost
nothing, self-correcting on the next pass. Acceptable, but worth either chaining
the jobs or documenting the first-run behaviour so it does not look like a bug.

### Brand tokens are duplicated, not shared

Resolved as far as it usefully can be: `resources/css/app.css` and
`docs/images/banner.php` now hold the same OKLCH values, and the UI is built on
the role-named tokens rather than stock Tailwind colours.

The two copies are still copies. PHP cannot read a CSS custom property, so
changing the palette means editing both and re-running the generator. If that
ever drifts, the fix is to make one of them generated — a small script emitting
the `@theme` block from the PHP array, or vice versa. Not worth building yet at
one palette change every few months.

### Consent errors are not shown

`TenantConsentController` redirects to `/tenants?error=consent_declined` and
similar, but `TenantsPage` never reads the `error` param — the user is bounced
back with no explanation. `LoginPage` already does this correctly; copy that
pattern.

---

## 2. The safe-change system

**This is the next real feature**, and the gate on everything destructive.

`retire` and `wipe` are implemented in `ManagedDevicesResource`, covered by
their permission, and deliberately **not routed**. They stay that way until this
exists. See [roadmap.md](roadmap.md) for the reasoning.

Build order:

1. **`PreviewController` / `ChangePreview` value object.** Given an action and a
   target, compute what will be affected: the device, its primary user, its last
   check-in, whether Intune has heard from it recently. Server-side — the client
   must not be what decides an action's blast radius.
2. **Confirmation token.** The preview issues a short-lived token (TTL already
   configured at `platform.safe_change.preview_ttl_seconds`); execution requires
   it. This makes it structurally impossible to reach a wipe without having
   first been shown what it hits.
3. **Route the actions** behind `permission:devices.wipe` + the token check.
4. **Wire the UI.** `ConfirmDialog` already supports `risk="high"` and
   `confirmPhrase` — require typing the device name for a wipe.
5. **Audit as `pending`.** `AuditResult::Pending` exists for exactly this: Graph
   accepting a wipe means it was queued, not performed.

Tests to write alongside: an Operator cannot reach it; a preview token cannot be
reused; a token for device A cannot execute against device B.

---

## 3. Tenant and member management

`Permission::TenantManage` and `Permission::MembersManage` exist, `Role::Owner`
holds them, and **nothing uses them** — there is no `TenantController`. Missing:

- [ ] `GET /api/tenants/{tenant}` — tenant detail (`TenantResource` already built)
- [ ] `DELETE` or `POST .../disconnect` — set status `disabled`, stop syncing,
      **revoke the cached token** (`TokenProvider::forget`)
- [ ] `GET/POST/PATCH/DELETE .../members` — invite, change role, remove
- [ ] Reconnect flow for a `degraded` tenant

Guard rails worth building in from the start: an Owner must not be able to
remove or demote the last Owner of a tenant, and role changes must be audited
(`AuditAction::MemberRoleChanged` already exists).

---

## 4. Hardening before real customer data

Roughly in order of how much it would matter.

- [ ] **API rate limiting.** There is none. Graph throttling is handled well;
      *our* endpoints are wide open. Add `throttle:` to the API group, and
      something much tighter on the action routes.
- [ ] **Sanctum stateful domains in production.** `SANCTUM_STATEFUL_DOMAINS` is
      set for localhost in `.env.example`. Getting this wrong in production
      means either broken auth or an over-permissive CORS surface.
- [ ] **Session cookie flags.** `SESSION_SECURE_COOKIE=true` and
      `SESSION_SAME_SITE=lax` for any non-local environment.
- [ ] **Secret storage.** `MS_GRAPH_CLIENT_SECRET` in a `.env` is fine for
      development and not fine in production. Move to a managed secret store.
- [ ] **Content-Security-Policy.** The nginx config sets the other headers but
      not CSP. This app holds standing admin access to customer directories, so
      an injected script is worth a lot to an attacker.
- [ ] **Consider certificate credentials** over a shared secret for the Graph
      app. Longer-lived and not copy-pasteable out of a config file.
- [ ] **Failed-job monitoring.** A `queue:work` that dies silently means data
      quietly goes stale while the UI keeps showing timestamps. The dashboard
      surfaces sync staleness, but nobody is watching the dashboard at 3am.

---

## 5. Tests and CI

- [ ] **CI pipeline** — `php artisan test`, `pint --test`, `tsc --noEmit`,
      `npm run build`. All four already pass locally, so this is cheap to set up
      now and annoying to retrofit later. Dependabot is already configured to
      watch `.github/workflows`, so the action versions are covered from the
      moment the first workflow lands.
- [ ] **docker-compose images are not tracked by Dependabot.** `.github/dependabot.yml`
      covers `docker/php/Dockerfile`, but the `nginx`, `mysql` and `redis` tags
      pinned in `docker-compose.yml` are not. Either add a `docker-compose`
      ecosystem entry (newer Dependabot support — confirm GitHub accepts it
      rather than flagging an unknown ecosystem) or bump those tags by hand at
      a set interval. They are development-stack images today, which is why this
      is not urgent; it becomes urgent if they ever back production.
- [ ] **Frontend tests.** There are none. The highest-value target is
      `useSession`/`can()`, since it decides which destructive buttons render.
- [ ] **Sync job tests.** `SynchroniseUsers` and `SynchroniseManagedDevices` have
      no coverage. Worth testing specifically: delta `@removed` handling, and
      that a partial Graph response does not null out columns it omitted.
- [ ] **A test that fails if a destructive route ships unguarded** — assert every
      route requiring a high-impact permission also requires a confirmation
      token. Cheap insurance against the safe-change gate being bypassed later.

---

## 6. Then

Phases 4–7 in [roadmap.md](roadmap.md): dashboard depth (needs a historical
table — the projection holds current state only, so nothing can answer "what
changed this week"), automation, reporting, enterprise RBAC.

Resist starting automation before the safe-change system is finished. Automation
that can act unattended on a tenant is exactly the thing that needs the
preview/approval/limit machinery underneath it.

---

## Quick reference

| Task | Command |
| --- | --- |
| Everything, running | `composer run dev` |
| Tests | `php artisan test` |
| Style | `./vendor/bin/pint` |
| Types | `npx tsc --noEmit` |
| Fresh database | `php artisan migrate:fresh` |
| Sync one tenant now | `POST /api/tenants/{slug}/sync` with `{"resource":"users"}` |

| Where things live | |
| --- | --- |
| Tenant isolation | `app/Models/Scopes/TenantScope.php`, `app/Http/Middleware/ResolveTenant.php` |
| Roles and permissions | `app/Domain/Access/` |
| Graph HTTP (the only place) | `app/Integrations/MicrosoftGraph/GraphClient/GraphClient.php` |
| Graph endpoints by resource | `app/Integrations/MicrosoftGraph/Entra/`, `.../Intune/` |
| Audit | `app/Domain/Audit/` |
| Sync jobs | `app/Jobs/` |
| Route/permission map | `routes/api.php` |
