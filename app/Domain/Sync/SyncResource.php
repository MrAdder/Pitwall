<?php

declare(strict_types=1);

namespace App\Domain\Sync;

enum SyncResource: string
{
    case Users = 'users';
    case Groups = 'groups';
    case GroupMembers = 'group_members';
    case ManagedDevices = 'managed_devices';
    case AuthenticationMethods = 'authentication_methods';

    public function label(): string
    {
        return match ($this) {
            self::Users => 'Users',
            self::Groups => 'Groups',
            self::GroupMembers => 'Group membership',
            self::ManagedDevices => 'Devices',
            self::AuthenticationMethods => 'Authentication methods',
        };
    }

    /**
     * Whether Microsoft Graph offers a delta endpoint for this resource.
     *
     * Where it does not, synchronisation is a full enumeration each run, which
     * is slower and heavier on the tenant's throttling budget.
     */
    public function supportsDelta(): bool
    {
        return match ($this) {
            self::Users, self::Groups => true,
            self::GroupMembers, self::ManagedDevices, self::AuthenticationMethods => false,
        };
    }

    /**
     * Configured interval in minutes.
     */
    public function intervalMinutes(): int
    {
        return match ($this) {
            self::Users => (int) config('graph.sync.users'),
            self::Groups, self::GroupMembers => (int) config('graph.sync.groups'),
            self::ManagedDevices => (int) config('graph.sync.devices'),
            self::AuthenticationMethods => (int) config('graph.sync.users'),
        };
    }
}
