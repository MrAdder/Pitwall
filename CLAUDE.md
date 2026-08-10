# CLAUDE.md

## Project: Enterprise Microsoft 365 Management Platform

## Overview

This project is a professional enterprise SaaS platform designed to simplify and centralize Microsoft Entra ID and Microsoft Intune administration.

The platform sits **on top of Microsoft Graph** and provides a significantly easier operational interface for IT administrators, MSPs, and enterprise IT teams.

The product is **not intended to replace Microsoft Entra or Intune**.

Instead, it provides:

* Unified administration
* Simplified workflows
* Bulk operations
* Automation
* Reporting
* Compliance visibility
* Audit history
* Safe-change workflows
* Cross-service searching
* Operational dashboards

The long-term vision is an **IT operations control plane for Microsoft 365**.

---

# Core Product Philosophy

The application should answer:

> "How can we make common Microsoft 365 administration tasks faster, safer, and easier?"

Do not simply reproduce Microsoft portals.

Every feature should improve one or more of:

1. Speed
2. Visibility
3. Automation
4. Safety
5. Reporting
6. User experience

Avoid building features merely because Microsoft already exposes the underlying API.

---

# Initial Product Scope

The initial release focuses on:

### Microsoft Entra ID

* Users
* Groups
* Group membership
* Devices
* Licenses
* Authentication methods
* MFA status
* Sign-in information
* User status
* Administrative roles
* Basic audit information

### Microsoft Intune

* Managed devices
* Device compliance
* Device configuration
* Applications
* Application deployment status
* Device actions
* Compliance policies
* Configuration policies
* Windows update information
* Device synchronization

### Automation

* Rules
* Triggers
* Conditions
* Actions
* Scheduled tasks
* Remediation workflows
* Notifications

### Reporting

* Device reports
* User reports
* Compliance reports
* Application reports
* Security reports
* Audit reports
* Export to CSV/PDF where appropriate

---

# Product Positioning

The product should be positioned as:

> "The operations layer for Microsoft 365 IT teams."

Avoid positioning it as:

> "A replacement for Microsoft Intune."

Microsoft remains the source of truth.

Our platform provides:

* Better workflows
* Better visibility
* Better automation
* Better administration
* Better reporting

---

# Technology Stack

Use the following stack unless there is a compelling technical reason to change it.

## Backend

* Laravel
* PHP
* Laravel Sanctum where appropriate
* Laravel Queues
* Laravel Scheduler
* Redis
* MySQL

## Frontend

* React
* TypeScript
* Tailwind CSS
* Vite

## API

* REST API
* Microsoft Graph API
* OAuth 2.0 / OpenID Connect

## Infrastructure

Initial development should support:

* Docker
* Docker Compose
* Linux
* Nginx

Production should be designed for:

* Horizontal scaling
* Queue workers
* Redis
* Managed MySQL
* Object storage
* Secure secrets management

---

# Architecture

Use a modular architecture.

Preferred structure:

```text
app/
├── Domain/
│   ├── Identity/
│   ├── Devices/
│   ├── Applications/
│   ├── Policies/
│   ├── Compliance/
│   ├── Automation/
│   ├── Audit/
│   └── Reporting/
│
├── Integrations/
│   └── MicrosoftGraph/
│       ├── Entra/
│       ├── Intune/
│       ├── GraphClient/
│       └── Authentication/
│
├── Actions/
├── Jobs/
├── Models/
├── Services/
└── Policies/
```

Frontend:

```text
resources/
├── js/
│   ├── components/
│   ├── layouts/
│   ├── pages/
│   ├── features/
│   │   ├── users/
│   │   ├── devices/
│   │   ├── applications/
│   │   ├── policies/
│   │   ├── automation/
│   │   └── reports/
│   ├── hooks/
│   ├── services/
│   ├── types/
│   └── utils/
```

---

# Multi-Tenancy

The application MUST be designed as a multi-tenant SaaS from the beginning.

A tenant represents a customer Microsoft 365 environment.

Example:

```text
Platform
│
├── Tenant A
│   ├── Users
│   ├── Devices
│   ├── Policies
│   └── Automations
│
├── Tenant B
│   ├── Users
│   ├── Devices
│   ├── Policies
│   └── Automations
│
└── Tenant C
```

Never allow data from one tenant to be accessible to another tenant.

Every tenant-owned database record must be scoped appropriately.

---

# Microsoft Authentication

Microsoft identity authentication is fundamental to the application.

Use:

* Microsoft Entra ID
* OAuth 2.0
* OpenID Connect
* Microsoft Graph

Never store user Microsoft passwords.

Never implement custom Microsoft password authentication.

Tokens and credentials must be encrypted at rest.

---

# Microsoft Graph

Microsoft Graph is the primary integration layer.

Do not scatter raw Graph API requests throughout controllers or frontend code.

Use dedicated integration services.

Example:

```php
MicrosoftGraphClient
    ├── Users
    ├── Groups
    ├── Devices
    ├── Applications
    ├── Policies
    ├── Compliance
    └── Audit
```

Graph API interaction should be abstracted behind services/interfaces wherever practical.

---

# Graph API Requirements

All Graph operations must account for:

* Authentication
* Authorization
* Permissions
* Pagination
* Rate limiting
* Retry handling
* Transient failures
* API versioning
* Error handling
* Throttling
* Request logging where appropriate

Never assume a Graph request will always succeed.

Implement exponential backoff for throttling and transient errors.

Respect Microsoft Graph `Retry-After` responses.

---

# Permissions

Follow the principle of least privilege.

Do not request Microsoft Graph permissions that are not required.

Clearly document every Graph permission used by the application.

Example:

```text
Permission
Purpose
Risk
Required by
```

High-impact permissions require additional consideration.

---

# Dangerous Operations

Some operations can cause significant damage.

Examples:

* Disable user
* Delete user
* Remove group membership
* Delete group
* Wipe device
* Retire device
* Remove application assignment
* Modify Conditional Access
* Change compliance policy
* Disable security controls

These operations must NOT be executed silently.

Use a confirmation workflow.

For high-impact actions provide:

```text
Action
Affected objects
Current state
Requested state
Potential impact
Required permissions
Confirmation
```

---

# Safe Change System

The application should support a preview mode for destructive or high-impact changes.

Example:

```text
CHANGE PREVIEW

Action:
Disable Conditional Access Policy

Affected users:
1,284

Current:
MFA required

New:
MFA not required

Risk:
HIGH

[Cancel]
[Require Approval]
[Apply Change]
```

Where practical, support:

* Preview
* Approval
* Execution
* Result
* Audit entry

---

# Audit Logging

Every important administrative action must be auditable.

Record:

* Tenant
* User
* Action
* Resource
* Resource ID
* Timestamp
* IP where appropriate
* Previous state where appropriate
* New state where appropriate
* Result
* Error information
* Correlation ID

Example:

```text
Admin changed group membership

User:
admin@company.com

Target:
john@company.com

Group:
Finance

Action:
Added member

Time:
2026-08-10 09:30

Result:
Success
```

Audit logs should be append-only from the application's normal administrative interface.

---

# Automation Engine

Automation is a core differentiator.

Automations should use:

```text
Trigger
    ↓
Conditions
    ↓
Actions
```

Example:

```text
TRIGGER

Device becomes non-compliant

CONDITIONS

OS = Windows
AND
BitLocker = Disabled

ACTIONS

1. Notify administrator
2. Notify user
3. Trigger device sync
4. Wait 30 minutes
5. Re-check compliance
6. Create remediation task if still failing
```

Automation definitions should be stored in the database.

Do not hard-code individual automation workflows.

---

# Automation Safety

Automation must support:

* Dry runs
* Execution limits
* Approval requirements
* Retry limits
* Failure handling
* Audit logging
* Tenant isolation
* Action timeouts

Never create an automation that can recursively trigger itself without safeguards.

---

# Dashboard

The dashboard should focus on operational information.

Example:

```text
DEVICES
1,248

COMPLIANT
1,189

NON-COMPLIANT
42

UNKNOWN
17

APPLICATION FAILURES
12

SECURITY WARNINGS
8
```

The dashboard should answer:

> "What requires my attention right now?"

Avoid filling the dashboard with meaningless statistics.

---

# Global Search

Global search is a major feature.

Administrators should be able to search for:

* Users
* Devices
* Groups
* Applications
* Policies

Example:

```text
Search:
Daniel Green
```

Results:

```text
USER
Daniel Green

DEVICES
DESKTOP-1042
LAPTOP-8831

GROUPS
IT
Employees

APPLICATIONS
Microsoft 365
Chrome
```

Search should be fast and tenant-scoped.

---

# User Management

User pages should provide a unified view.

Example:

```text
USER

Daniel Green
daniel@company.com

STATUS
Enabled

MFA
Enabled

LICENSES
Microsoft 365 Business Premium

GROUPS
IT
Employees

DEVICES
4

SIGN-IN ACTIVITY
Available

ACTIONS

Reset authentication
Revoke sessions
Disable account
Manage groups
Manage licenses
```

Do not expose actions the current administrator is not authorized to perform.

---

# Device Management

Device pages should provide:

* Device name
* Manufacturer
* Model
* Serial number
* OS
* OS version
* Primary user
* Compliance state
* Encryption state
* Defender state
* Last check-in
* Applications
* Policies
* Hardware information where available

Actions:

* Sync
* Restart
* Lock
* Wipe
* Retire
* Rename
* Collect diagnostics where supported

---

# Application Management

Applications should have a unified view:

```text
Application

Google Chrome

Installed:
1,184

Failed:
12

Pending:
32

Not Installed:
20
```

Provide deployment visibility.

Where Microsoft Graph exposes sufficient information, provide useful failure analysis.

Do not invent diagnostics that Microsoft does not provide.

---

# Policy Management

Policies should be presented in a simplified format.

Avoid unnecessarily exposing Microsoft portal complexity.

Use:

* Templates
* Categories
* Recommended configurations
* Assignments
* Scope
* Conflicts
* Status

Example:

```text
Windows Security Baseline

✓ Defender
✓ Firewall
✓ BitLocker
✓ Secure Boot
✓ Password policy

Assigned to:
Windows Devices

Status:
94.8% compliant
```

---

# Conflict Detection

Where possible, identify conflicting policies.

Example:

```text
POLICY CONFLICT

Policy A:
Require BitLocker = Enabled

Policy B:
Require BitLocker = Disabled

Affected devices:
32

[ View Devices ]
[ Resolve Conflict ]
```

Never automatically change policies to resolve a conflict unless explicitly authorized.

---

# Notifications

Support:

* In-app notifications
* Email
* Microsoft Teams webhook where appropriate
* Webhooks

Examples:

```text
42 devices became non-compliant.

12 application deployments failed.

3 devices have not checked in for 14 days.
```

---

# Reporting

Reports should be exportable.

Initial formats:

* CSV
* PDF

Potential reports:

* Device compliance
* Application deployment
* User inventory
* License inventory
* Security posture
* Non-compliant devices
* Audit history
* Automation history

Reports should support filters.

---

# UI/UX Principles

The UI must feel like a professional enterprise product.

Use:

* Clean layouts
* Consistent spacing
* Clear hierarchy
* Tables for operational data
* Filters
* Search
* Pagination
* Bulk actions
* Confirmation dialogs
* Status indicators
* Empty states
* Loading states
* Error states

Avoid:

* Excessive gradients
* Excessive animations
* Giant dashboard cards
* Unnecessary decorative elements
* Dark patterns

The application should feel closer to a serious enterprise administration console than a consumer SaaS dashboard.

---

# API Design

Use RESTful APIs.

Example:

```text
GET    /api/tenants
GET    /api/users
GET    /api/users/{id}
POST   /api/users/{id}/disable

GET    /api/devices
GET    /api/devices/{id}
POST   /api/devices/{id}/sync
POST   /api/devices/{id}/wipe

GET    /api/applications
GET    /api/policies

GET    /api/automations
POST   /api/automations
PUT    /api/automations/{id}
DELETE /api/automations/{id}

GET    /api/audit
```

Use consistent response structures.

---

# Background Jobs

Graph synchronization should not block HTTP requests.

Use Laravel queues for:

* Device synchronization
* User synchronization
* Group synchronization
* Application synchronization
* Policy synchronization
* Report generation
* Automation execution
* Notifications

Example:

```text
Graph API
    ↓
Queue
    ↓
Sync Job
    ↓
Database
    ↓
Dashboard
```

---

# Local Data vs Microsoft Source of Truth

Microsoft remains the authoritative source for Microsoft-managed objects.

The local database is used for:

* Cached data
* Search
* Reporting
* Historical information
* Automation state
* Audit records
* Application configuration

Do not assume local data is always current.

Display synchronization timestamps where appropriate.

Example:

```text
Last synchronized:
2 minutes ago
```

---

# Error Handling

Errors must be useful.

Bad:

```text
Error 400
```

Good:

```text
Unable to update device.

Microsoft Graph rejected the request.

Reason:
Insufficient privileges.

Required permission:
DeviceManagementManagedDevices.PrivilegedOperations.All
```

Never expose:

* Access tokens
* Secrets
* Passwords
* Internal stack traces
* Sensitive Graph responses

to end users.

---

# Security Requirements

Security is a first-class requirement.

Implement:

* Strong authentication
* MFA support
* RBAC
* Tenant isolation
* Encryption at rest
* TLS
* Secure token storage
* CSRF protection
* Input validation
* Output escaping
* Rate limiting
* Audit logging
* Secure session handling
* Least privilege
* Secret management

Never log access tokens.

Never commit secrets.

Never place Microsoft client secrets in frontend code.

---

# RBAC

Support application-level roles.

Initial roles:

```text
Owner
Administrator
Operator
Auditor
Read Only
```

Permissions should be granular.

Example:

```text
users.read
users.write
devices.read
devices.write
devices.wipe
policies.read
policies.write
automation.read
automation.write
audit.read
reports.read
```

High-impact permissions should be separate.

---

# Testing

Every significant feature must have tests.

Backend:

* Unit tests
* Feature tests
* Authorization tests
* Tenant isolation tests
* Graph integration tests where practical

Frontend:

* Component tests where appropriate
* Critical workflow tests

Critical scenarios:

```text
User from Tenant A cannot access Tenant B
Unauthorized user cannot wipe device
Read-only user cannot modify policies
Automation cannot execute without required permissions
Graph failure is handled correctly
Token expiry is handled correctly
```

---

# Development Rules

Before implementing a feature:

1. Understand the existing architecture.
2. Search for existing functionality.
3. Reuse existing services/components.
4. Check Microsoft Graph API requirements.
5. Check required permissions.
6. Consider tenant isolation.
7. Consider authorization.
8. Consider audit logging.
9. Consider failure states.
10. Write tests.

Do not duplicate functionality unnecessarily.

---

# Code Quality

Prefer:

* Small focused classes
* Clear naming
* Strong typing
* Dependency injection
* Interfaces where useful
* Reusable services
* Reusable React components
* Domain-oriented organization

Avoid:

* Massive controllers
* Massive React components
* Raw Graph calls everywhere
* Business logic inside Blade/UI components
* Hard-coded tenant IDs
* Hard-coded Microsoft IDs
* Hard-coded credentials
* Global mutable state
* Copy/paste implementations

---

# Database

Use UUIDs/ULIDs where appropriate for public identifiers.

All tenant-owned tables should contain a tenant identifier.

Example:

```text
tenants
users
devices
groups
applications
policies
automations
automation_runs
audit_logs
notifications
reports
```

Use indexes for:

* tenant_id
* external Microsoft IDs
* frequently searched fields
* timestamps
* status fields

Do not store unnecessary Microsoft Graph response payloads indefinitely.

---

# Microsoft IDs

Always preserve Microsoft identifiers required for synchronization.

Example:

```text
local_id
tenant_id
microsoft_id
```

Do not use email addresses as permanent identifiers.

Users can change email addresses.

---

# Synchronization

Synchronization should be incremental where possible.

Support:

* Initial tenant synchronization
* Incremental synchronization
* Scheduled synchronization
* Manual synchronization

Example:

```text
Every 15 minutes:

Users
Groups
Devices

Every hour:

Applications
Policies

On demand:

Specific device
Specific user
Specific application
```

Actual synchronization frequency should be configurable.

---

# Product Roadmap

## Phase 1 — Foundation

* Laravel application
* React frontend
* Authentication
* Tenant creation
* Microsoft OAuth
* Microsoft Graph client
* Tenant isolation
* RBAC
* Audit foundation

## Phase 2 — Entra

* Users
* Groups
* Devices
* Licenses
* MFA visibility
* User actions
* Bulk operations
* Search

## Phase 3 — Intune

* Devices
* Compliance
* Applications
* Policies
* Device actions
* Synchronization

## Phase 4 — Dashboard

* Global dashboard
* Health overview
* Compliance overview
* Application deployment overview
* Security warnings

## Phase 5 — Automation

* Triggers
* Conditions
* Actions
* Scheduled jobs
* Notifications
* Remediation workflows

## Phase 6 — Reporting

* Reports
* CSV export
* PDF export
* Scheduled reports

## Phase 7 — Enterprise

* Advanced RBAC
* Approval workflows
* Change preview
* Advanced audit
* Multi-admin controls
* MSP capabilities
* Custom branding

---

# MVP Definition

The MVP must NOT attempt to implement the entire roadmap.

The first usable MVP should contain:

### Authentication

* Microsoft login
* Tenant connection

### Users

* List users
* Search users
* User details
* Enable/disable
* Revoke sessions where supported

### Devices

* Device list
* Search devices
* Device details
* Compliance status
* Last check-in
* Sync

### Dashboard

* Total users
* Total devices
* Compliance
* Non-compliance
* Recent activity

### Audit

* Record administrative actions

### Search

* Search users
* Search devices
* Search groups

That is enough to demonstrate the product.

---

# What NOT to Build Initially

Do not initially build:

* Full Microsoft 365 administration
* Exchange management
* SharePoint management
* Teams management
* Defender management
* Billing
* Marketplace
* AI assistant
* Complex ticketing
* Remote desktop
* Full RMM
* Full PSA
* Full MDM replacement

These may be considered later.

---

# AI Features

AI is optional and should not be a core dependency of the MVP.

Potential future features:

```text
"Why is this device non-compliant?"

"What changed in the last 24 hours?"

"Which policies conflict?"

"Which devices are at risk?"

"Summarize today's IT activity."
```

AI must never independently perform high-impact administrative actions.

AI-generated recommendations must be clearly identified.

High-impact actions require explicit administrator confirmation.

---

# Commercial Strategy

The platform is intended to become a commercial SaaS.

Potential customers:

* Small/medium businesses
* Internal IT departments
* MSPs
* Managed service providers
* Enterprise IT teams

Potential pricing model:

```text
Free
Small environments

Professional
Per tenant / device

Business
Higher limits + automation

Enterprise
Custom pricing
```

Do not implement billing until the core product has a usable MVP.

---

# Development Priority

When choosing between features, prioritize:

1. User value
2. Enterprise safety
3. Microsoft Graph reliability
4. Simplicity
5. Performance
6. Maintainability

Do not optimize for feature count.

A small number of excellent workflows is better than dozens of mediocre ones.

---

# Claude Instructions

When working on this project, Claude must:

* Act as a senior enterprise software engineer.
* Prefer simple maintainable solutions.
* Follow the architecture defined in this document.
* Never invent Microsoft Graph endpoints.
* Verify Microsoft Graph permissions and API behavior before implementing uncertain functionality.
* Never bypass Microsoft security controls.
* Never expose credentials or tokens.
* Never weaken tenant isolation.
* Never implement destructive operations without authorization and confirmation.
* Add audit logging to administrative operations.
* Add tests for security-sensitive functionality.
* Preserve existing functionality when modifying code.
* Avoid unnecessary dependencies.
* Avoid unnecessary rewrites.
* Explain significant architectural changes before implementing them.
* Keep the MVP focused.
* Use Microsoft Graph as the integration layer.
* Treat Microsoft as the source of truth for Microsoft-managed resources.

---

# Definition of Done

A feature is not complete until:

* It works for the intended use case.
* It respects tenant isolation.
* It respects RBAC.
* Microsoft Graph errors are handled.
* Loading states exist.
* Empty states exist.
* Error states exist.
* Audit logging exists where appropriate.
* Tests exist for critical behavior.
* No secrets are exposed.
* No unnecessary duplication exists.
* The UI is consistent with the rest of the application.
* Documentation is updated where necessary.

---

# Final Product Vision

The long-term product should feel like:

```text
                 ENTERPRISE IT
                       │
                       ▼
              ┌─────────────────┐
              │    YOUR APP     │
              │                 │
              │ Visibility      │
              │ Automation      │
              │ Governance      │
              │ Reporting       │
              │ Safe Changes    │
              └────────┬────────┘
                       │
                MICROSOFT GRAPH
                       │
        ┌──────────────┼──────────────┐
        ▼              ▼              ▼
     ENTRA          INTUNE         DEFENDER
```

The product's purpose is not to replace Microsoft's infrastructure.

Its purpose is to make enterprise IT administration **faster, safer, easier, and more automated**.
