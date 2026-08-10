<?php

declare(strict_types=1);

namespace App\Domain\Tenancy;

enum TenantStatus: string
{
    /** Created, but the customer has not yet granted admin consent. */
    case Pending = 'pending';

    /** Consent granted and Graph is responding. */
    case Active = 'active';

    /** Consent granted, but recent Graph calls are failing. */
    case Degraded = 'degraded';

    /** Disconnected by an owner. No synchronisation runs. */
    case Disabled = 'disabled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Awaiting consent',
            self::Active => 'Connected',
            self::Degraded => 'Connection problem',
            self::Disabled => 'Disconnected',
        };
    }

    /**
     * Whether we hold consent and should attempt Graph calls. Degraded tenants
     * are still attempted: the connection may have recovered.
     */
    public function isConnected(): bool
    {
        return $this === self::Active || $this === self::Degraded;
    }
}
