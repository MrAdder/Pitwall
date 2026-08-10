<?php

declare(strict_types=1);

namespace App\Domain\Devices;

/**
 * Mirrors the Microsoft Graph `complianceState` enum on managedDevice.
 *
 * Values come from Microsoft and are stored verbatim. Nothing is collapsed or
 * reinterpreted here: "unknown" means Intune does not know, and the UI says so
 * rather than guessing.
 */
enum ComplianceState: string
{
    case Unknown = 'unknown';
    case Compliant = 'compliant';
    case NonCompliant = 'noncompliant';
    case Conflict = 'conflict';
    case Error = 'error';
    case InGracePeriod = 'inGracePeriod';
    case ConfigManager = 'configManager';

    public function label(): string
    {
        return match ($this) {
            self::Unknown => 'Unknown',
            self::Compliant => 'Compliant',
            self::NonCompliant => 'Non-compliant',
            self::Conflict => 'Conflict',
            self::Error => 'Error',
            self::InGracePeriod => 'In grace period',
            self::ConfigManager => 'Managed by Configuration Manager',
        };
    }

    /**
     * Whether this state should be surfaced on the dashboard as needing
     * attention.
     */
    public function needsAttention(): bool
    {
        return match ($this) {
            self::NonCompliant, self::Conflict, self::Error => true,
            default => false,
        };
    }
}
