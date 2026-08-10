# Microsoft Graph permissions

Every permission this platform requests is listed here. Nothing is requested
"just in case": each one widens what a compromise of this platform would grant
across **every** connected customer directory, so each has to earn its place.

The platform is registered once as a **multi-tenant** Entra ID application.
Customer tenants grant admin consent to that single registration. We never hold
a customer's own credentials, and never see a user's password.

## Application permissions (app-only, granted by admin consent)

| Permission | Purpose | Risk | Required by |
| --- | --- | --- | --- |
| `User.Read.All` | Read user profiles for the cached projection, search and reporting. | Medium — full read of the directory's people. | `UsersResource::list`, `delta`, `find` |
| `User.ReadWrite.All` | Enable and disable accounts. | **High** — can disable any account, including administrators. | `UsersResource::setAccountEnabled` |
| `Group.Read.All` | Read groups and their properties. | Medium | `GroupsResource::list`, `delta`, `find` |
| `GroupMember.Read.All` | Read group membership. | Medium | `GroupsResource::userMembers`, `UsersResource::memberOf` |
| `GroupMember.ReadWrite.All` | Add and remove group members. | **High** — group membership frequently confers access. | `GroupsResource::addMember`, `removeMember` |
| `Organization.Read.All` | Confirm a newly consented tenant is reachable; read its real name and verified domains. | Low | `OrganizationResource::profile` |
| `AuditLog.Read.All` | Sign-in activity, and the authentication method registration report. | Medium — reveals sign-in patterns. | `UsersResource::signInActivity`, `ReportsResource::userRegistrationDetails` |
| `UserAuthenticationMethod.ReadWrite.All` | Revoke a user's sessions. | **High** | `UsersResource::revokeSignInSessions` |
| `DeviceManagementManagedDevices.Read.All` | Read Intune managed devices. | Medium | `ManagedDevicesResource::list`, `find` |
| `DeviceManagementManagedDevices.ReadWrite.All` | Trigger a device check-in. | Medium | `ManagedDevicesResource::sync` |
| `DeviceManagementManagedDevices.PrivilegedOperations.All` | Wipe and retire devices. | **Highest** — irreversible destruction of data on end-user machines. | `ManagedDevicesResource::wipe`, `retire` |

### Not yet requested

`DeviceManagementConfiguration.Read.All` and `DeviceManagementApps.Read.All` are
declared as constants in `GraphPermission` for the policy and application
features on the roadmap. They are **not** part of the consent request today, and
must not be added until the features that need them ship.

## Delegated permissions (interactive sign-in only)

`openid`, `profile`, `email`, `offline_access`, `User.Read`.

These identify the administrator signing in to the platform. They grant no
access to customer data — that comes from tenant membership plus the application
permissions above.

## Reducing the request

`DeviceManagementManagedDevices.PrivilegedOperations.All` is the one to think
hardest about. A tenant that will never use wipe or retire through this platform
should not consent to it, and the platform degrades cleanly without it: the
affected calls return `GraphPermissionDenied`, which the UI renders as an
explanation naming the missing permission.

The same is true of `AuditLog.Read.All`: without it, MFA registration and
sign-in activity show as **Unknown** rather than being guessed at.

## API version

All calls use `v1.0`. Nothing in the platform currently uses `beta`.

`beta` is outside Microsoft's support and deprecation guarantees, so anything
built on it can break without notice. If a feature genuinely requires it, use
`GraphClient::beta()` and add a row here recording the endpoint, why `v1.0`
cannot serve it, and what breaks if Microsoft changes it.

## Things Graph does not tell us

Recorded so nobody builds a feature on an assumption the API does not support:

- **MFA enforcement.** The registration report says whether a user has
  registered methods, not whether MFA is *required* — that is a Conditional
  Access decision. The UI says "registered" throughout, never "enabled".
- **Device compliance for `unknown` devices.** Intune genuinely does not know.
  These are never counted as compliant.
- **Whether a device action succeeded.** `syncDevice`, `retire` and `wipe` are
  asynchronous. A 2xx means the request was queued, not that the device acted on
  it. Audit entries for these are recorded as `pending`.
- **Delta for managed devices.** There is no delta endpoint, so device
  synchronisation is a full enumeration each run.
