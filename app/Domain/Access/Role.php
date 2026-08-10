<?php

declare(strict_types=1);

namespace App\Domain\Access;

/**
 * Application-level roles. A user holds exactly one role per tenant, recorded
 * on the tenant membership, so the same person can be an Administrator of one
 * customer tenant and Read Only on another.
 *
 * These roles are independent of Entra ID directory roles. Holding Global
 * Administrator in Microsoft 365 grants nothing here.
 */
enum Role: string
{
    case Owner = 'owner';
    case Administrator = 'administrator';
    case Operator = 'operator';
    case Auditor = 'auditor';
    case ReadOnly = 'read_only';

    public function label(): string
    {
        return match ($this) {
            self::Owner => 'Owner',
            self::Administrator => 'Administrator',
            self::Operator => 'Operator',
            self::Auditor => 'Auditor',
            self::ReadOnly => 'Read Only',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Owner => 'Full control, including the tenant connection and platform membership.',
            self::Administrator => 'Full operational control. Cannot change the tenant connection or membership.',
            self::Operator => 'Day-to-day operations. Cannot wipe devices or modify policies.',
            self::Auditor => 'Read-only access plus the audit log and reports.',
            self::ReadOnly => 'Read-only access to operational data.',
        };
    }

    /**
     * The permissions granted by this role.
     *
     * @return list<Permission>
     */
    public function permissions(): array
    {
        return match ($this) {
            self::Owner => Permission::cases(),

            self::Administrator => [
                Permission::TenantRead,
                Permission::UsersRead,
                Permission::UsersWrite,
                Permission::UsersDisable,
                Permission::UsersRevokeSessions,
                Permission::GroupsRead,
                Permission::GroupsWrite,
                Permission::DevicesRead,
                Permission::DevicesWrite,
                Permission::DevicesSync,
                Permission::DevicesRetire,
                Permission::DevicesWipe,
                Permission::ApplicationsRead,
                Permission::PoliciesRead,
                Permission::PoliciesWrite,
                Permission::AutomationRead,
                Permission::AutomationWrite,
                Permission::AuditRead,
                Permission::ReportsRead,
            ],

            // An Operator runs the help desk: they can act on users and sync
            // devices, but cannot wipe a machine or change a policy.
            self::Operator => [
                Permission::TenantRead,
                Permission::UsersRead,
                Permission::UsersWrite,
                Permission::UsersDisable,
                Permission::UsersRevokeSessions,
                Permission::GroupsRead,
                Permission::GroupsWrite,
                Permission::DevicesRead,
                Permission::DevicesWrite,
                Permission::DevicesSync,
                Permission::ApplicationsRead,
                Permission::PoliciesRead,
                Permission::AutomationRead,
                Permission::ReportsRead,
            ],

            self::Auditor => [
                Permission::TenantRead,
                Permission::UsersRead,
                Permission::GroupsRead,
                Permission::DevicesRead,
                Permission::ApplicationsRead,
                Permission::PoliciesRead,
                Permission::AutomationRead,
                Permission::AuditRead,
                Permission::ReportsRead,
            ],

            self::ReadOnly => [
                Permission::TenantRead,
                Permission::UsersRead,
                Permission::GroupsRead,
                Permission::DevicesRead,
                Permission::ApplicationsRead,
                Permission::PoliciesRead,
            ],
        };
    }

    public function grants(Permission $permission): bool
    {
        return in_array($permission, $this->permissions(), strict: true);
    }

    /**
     * Permission strings, for serialising to the frontend so the UI can hide
     * actions the administrator is not authorised to perform.
     *
     * @return list<string>
     */
    public function permissionValues(): array
    {
        return array_map(static fn (Permission $p): string => $p->value, $this->permissions());
    }
}
