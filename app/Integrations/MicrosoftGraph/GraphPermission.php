<?php

declare(strict_types=1);

namespace App\Integrations\MicrosoftGraph;

/**
 * The Microsoft Graph application permissions this platform requests.
 *
 * Every constant here must have a corresponding row in
 * docs/graph-permissions.md stating why it is needed and what it exposes.
 * Nothing is requested "just in case": each one widens what a compromise of
 * this platform would grant across every connected customer directory.
 *
 * Referenced from call sites via GraphRequestOptions so a 403 can tell the
 * administrator exactly which consent is missing.
 */
final class GraphPermission
{
    /** Read user profiles. */
    public const UsersRead = 'User.Read.All';

    /** Enable/disable accounts and update profile attributes. */
    public const UsersReadWrite = 'User.ReadWrite.All';

    /** Read groups and their properties. */
    public const GroupsRead = 'Group.Read.All';

    /** Read group membership. */
    public const GroupMemberRead = 'GroupMember.Read.All';

    /** Add and remove group members. */
    public const GroupMemberReadWrite = 'GroupMember.ReadWrite.All';

    /** Read the organisation profile, used to confirm the connected tenant. */
    public const OrganizationRead = 'Organization.Read.All';

    /**
     * Read sign-in activity and the authentication method registration report.
     * Also required for the signInActivity property on user objects.
     */
    public const AuditLogRead = 'AuditLog.Read.All';

    /** Revoke a user's refresh tokens and sign them out everywhere. */
    public const UserAuthenticationMethodReadWrite = 'UserAuthenticationMethod.ReadWrite.All';

    /** Read Intune managed devices. */
    public const ManagedDevicesRead = 'DeviceManagementManagedDevices.Read.All';

    /** Trigger non-destructive device actions such as sync. */
    public const ManagedDevicesReadWrite = 'DeviceManagementManagedDevices.ReadWrite.All';

    /**
     * Wipe and retire devices.
     *
     * The highest-risk permission the platform requests. It is separated from
     * the read/write permission by Microsoft for good reason, and the platform
     * keeps that separation: only the devices.wipe and devices.retire
     * application permissions can reach the calls that use it.
     */
    public const ManagedDevicesPrivileged = 'DeviceManagementManagedDevices.PrivilegedOperations.All';

    /** Read compliance and configuration policies. */
    public const DeviceConfigurationRead = 'DeviceManagementConfiguration.Read.All';

    /** Read application inventory and deployment status. */
    public const DeviceAppsRead = 'DeviceManagementApps.Read.All';
}
