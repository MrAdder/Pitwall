# Roadmap

Referenced from the code wherever something is deliberately unfinished, so a
gap is never mistaken for an oversight.

## Shipped

**Phase 1 — Foundation.** Laravel + React application, Microsoft OIDC sign-in,
tenant connection via admin consent, tenant isolation, RBAC, the Graph
integration layer, audit foundation.

**Phase 2/3 — MVP surface.** Users, groups, devices, global search, dashboard,
audit log, scheduled and on-demand synchronisation.

## Next: the safe-change system

This is the gate on everything destructive, and the next thing to build.

`retire` and `wipe` exist in `ManagedDevicesResource`, are covered by
`DeviceManagementManagedDevices.PrivilegedOperations.All`, and are **not
routed**. They stay unrouted until the flow below exists. Shipping an
irreversible action behind a single confirmation dialog is how a help desk
factory-resets the wrong laptop.

Required before either is exposed:

1. **Preview** — a server-computed statement of what the action will affect:
   the device, its primary user, its last check-in, whether it is currently
   reachable. Computed server-side because the client must not be the thing
   deciding what an action's blast radius is.
2. **Confirmation token** — the preview issues a short-lived token; execution
   requires it. This makes it impossible to reach the action without having
   requested a preview first.
3. **Approval** — optionally requiring a second platform administrator for the
   highest-risk actions.
4. **Result and audit** — outcome recorded as `pending`, since Graph accepting
   a wipe means it was queued, not performed.

`ConfirmDialog` and `AuditResult::Pending` are already built for this.

## Phase 4 — Dashboard depth

Compliance trend over time, per-policy compliance breakdown, application
deployment failures. Needs a historical table: today's projection holds current
state only, so nothing can answer "what changed this week".

## Phase 5 — Automation

Trigger → conditions → actions, stored in the database rather than hard-coded.
Requires dry runs, execution limits, retry limits and recursion guards before
anything is allowed to act on its own. An automation that can trigger itself
across a tenant is a denial-of-service against a customer's directory.

## Phase 6 — Reporting

CSV and PDF export, scheduled reports. The projection tables are already
indexed for it.

## Phase 7 — Enterprise

Advanced RBAC, approval workflows, MSP multi-tenant views, custom branding.

## Deliberately not planned

- **User deletion.** Entra's own 30-day recycle bin is the right tool.
  Disabling covers the operational need and is reversible.
- **Conditional Access modification.** Reading policies to detect conflicts is
  in scope. Writing them from here is not: a mistake locks every administrator
  out of the tenant, including the one making it.
- **Replacing Intune or Entra.** Microsoft stays the source of truth.
