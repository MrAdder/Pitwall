<?php

declare(strict_types=1);

namespace App\Domain\Audit;

use App\Domain\Access\Permission;

/**
 * The closed set of auditable administrative actions.
 *
 * An enum rather than free text so the audit log stays queryable and so a new
 * action cannot be logged under a typo'd name that reporting silently misses.
 */
enum AuditAction: string
{
    // Tenant lifecycle.
    case TenantConnected = 'tenant.connected';
    case TenantDisconnected = 'tenant.disconnected';
    case TenantConsentGranted = 'tenant.consent_granted';
    case TenantSettingsUpdated = 'tenant.settings_updated';

    // Platform membership.
    case MemberInvited = 'member.invited';
    case MemberRoleChanged = 'member.role_changed';
    case MemberRemoved = 'member.removed';

    // Authentication into the platform.
    case SignIn = 'auth.sign_in';
    case SignOut = 'auth.sign_out';

    // An authorisation check refused an action. Recorded so that repeated
    // attempts to exceed a role are visible.
    case PermissionDenied = 'access.denied';

    // Entra ID users.
    case UserEnabled = 'user.enabled';
    case UserDisabled = 'user.disabled';
    case UserSessionsRevoked = 'user.sessions_revoked';
    case UserGroupAdded = 'user.group_added';
    case UserGroupRemoved = 'user.group_removed';

    // Intune devices.
    case DeviceSyncRequested = 'device.sync_requested';
    case DeviceRetired = 'device.retired';
    case DeviceWiped = 'device.wiped';

    // Synchronisation initiated by a person, not the scheduler.
    case SyncRequested = 'sync.requested';

    // Reporting.
    case ReportExported = 'report.exported';

    public function label(): string
    {
        return match ($this) {
            self::TenantConnected => 'Connected tenant',
            self::TenantDisconnected => 'Disconnected tenant',
            self::TenantConsentGranted => 'Granted admin consent',
            self::TenantSettingsUpdated => 'Updated tenant settings',
            self::MemberInvited => 'Invited member',
            self::MemberRoleChanged => 'Changed member role',
            self::MemberRemoved => 'Removed member',
            self::SignIn => 'Signed in',
            self::SignOut => 'Signed out',
            self::PermissionDenied => 'Action denied',
            self::UserEnabled => 'Enabled user account',
            self::UserDisabled => 'Disabled user account',
            self::UserSessionsRevoked => 'Revoked sign-in sessions',
            self::UserGroupAdded => 'Added user to group',
            self::UserGroupRemoved => 'Removed user from group',
            self::DeviceSyncRequested => 'Requested device sync',
            self::DeviceRetired => 'Retired device',
            self::DeviceWiped => 'Wiped device',
            self::SyncRequested => 'Requested synchronisation',
            self::ReportExported => 'Exported report',
        };
    }

    /**
     * The permission that authorises this action, where one applies. Used to
     * cross-check that every high-impact action has an audit entry.
     */
    public function permission(): ?Permission
    {
        return match ($this) {
            self::UserEnabled, self::UserDisabled => Permission::UsersDisable,
            self::UserSessionsRevoked => Permission::UsersRevokeSessions,
            self::UserGroupAdded, self::UserGroupRemoved => Permission::GroupsWrite,
            self::DeviceSyncRequested => Permission::DevicesSync,
            self::DeviceRetired => Permission::DevicesRetire,
            self::DeviceWiped => Permission::DevicesWipe,
            self::TenantConnected, self::TenantDisconnected,
            self::TenantConsentGranted, self::TenantSettingsUpdated => Permission::TenantManage,
            self::MemberInvited, self::MemberRoleChanged, self::MemberRemoved => Permission::MembersManage,
            self::ReportExported => Permission::ReportsRead,
            default => null,
        };
    }
}
