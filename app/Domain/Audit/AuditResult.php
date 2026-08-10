<?php

declare(strict_types=1);

namespace App\Domain\Audit;

enum AuditResult: string
{
    /** The action completed and Microsoft accepted it. */
    case Success = 'success';

    /** The action was attempted and failed. */
    case Failure = 'failure';

    /**
     * The action was accepted by Microsoft but is asynchronous, so the outcome
     * is not yet known. Device wipes and retires behave this way.
     */
    case Pending = 'pending';

    /** The actor was not authorised. Recorded so denials are visible too. */
    case Denied = 'denied';

    public function label(): string
    {
        return match ($this) {
            self::Success => 'Success',
            self::Failure => 'Failed',
            self::Pending => 'In progress',
            self::Denied => 'Denied',
        };
    }
}
