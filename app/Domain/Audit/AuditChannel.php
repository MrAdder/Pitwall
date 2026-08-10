<?php

declare(strict_types=1);

namespace App\Domain\Audit;

/**
 * How an action reached the platform. Distinguishing an automation's change
 * from a person's is essential when reconstructing what happened.
 */
enum AuditChannel: string
{
    case Web = 'web';
    case Api = 'api';
    case Automation = 'automation';
    case Console = 'console';

    public function label(): string
    {
        return match ($this) {
            self::Web => 'Web',
            self::Api => 'API',
            self::Automation => 'Automation',
            self::Console => 'Console',
        };
    }
}
