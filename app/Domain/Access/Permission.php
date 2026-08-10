<?php

declare(strict_types=1);

namespace App\Domain\Access;

/**
 * Every authorisation decision in the application resolves to one of these
 * strings. High impact operations are deliberately separated from their
 * read/write siblings so a role can be granted day-to-day access without
 * inheriting the ability to destroy data.
 */
enum Permission: string
{
    // Tenant administration.
    case TenantRead = 'tenant.read';
    case TenantManage = 'tenant.manage';
    case MembersManage = 'members.manage';

    // Entra ID users.
    case UsersRead = 'users.read';
    case UsersWrite = 'users.write';
    case UsersDisable = 'users.disable';
    case UsersRevokeSessions = 'users.revoke_sessions';

    // Entra ID groups.
    case GroupsRead = 'groups.read';
    case GroupsWrite = 'groups.write';

    // Intune managed devices.
    case DevicesRead = 'devices.read';
    case DevicesWrite = 'devices.write';
    case DevicesSync = 'devices.sync';
    case DevicesRetire = 'devices.retire';
    case DevicesWipe = 'devices.wipe';

    // Applications and policies.
    case ApplicationsRead = 'applications.read';
    case PoliciesRead = 'policies.read';
    case PoliciesWrite = 'policies.write';

    // Automation.
    case AutomationRead = 'automation.read';
    case AutomationWrite = 'automation.write';

    // Audit and reporting.
    case AuditRead = 'audit.read';
    case ReportsRead = 'reports.read';

    /**
     * Permissions that authorise an irreversible or wide-blast-radius change.
     * These always require the safe-change confirmation workflow in addition to
     * the permission itself.
     *
     * @return list<self>
     */
    public static function highImpact(): array
    {
        return [
            self::UsersDisable,
            self::DevicesRetire,
            self::DevicesWipe,
            self::PoliciesWrite,
            self::TenantManage,
        ];
    }

    public function isHighImpact(): bool
    {
        return in_array($this, self::highImpact(), strict: true);
    }

    public function label(): string
    {
        return match ($this) {
            self::TenantRead => 'View tenant',
            self::TenantManage => 'Manage tenant connection',
            self::MembersManage => 'Manage platform members',
            self::UsersRead => 'View users',
            self::UsersWrite => 'Modify users',
            self::UsersDisable => 'Enable and disable user accounts',
            self::UsersRevokeSessions => 'Revoke user sign-in sessions',
            self::GroupsRead => 'View groups',
            self::GroupsWrite => 'Modify groups and membership',
            self::DevicesRead => 'View devices',
            self::DevicesWrite => 'Modify devices',
            self::DevicesSync => 'Trigger device synchronisation',
            self::DevicesRetire => 'Retire devices',
            self::DevicesWipe => 'Wipe devices',
            self::ApplicationsRead => 'View applications',
            self::PoliciesRead => 'View policies',
            self::PoliciesWrite => 'Modify policies',
            self::AutomationRead => 'View automations',
            self::AutomationWrite => 'Create and modify automations',
            self::AuditRead => 'Read the audit log',
            self::ReportsRead => 'Run and export reports',
        };
    }
}
