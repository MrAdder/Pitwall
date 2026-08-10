<?php

declare(strict_types=1);

namespace App\Domain\Auth\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Sign-in with Microsoft could not be completed.
 *
 * The reason code is kept for logs. Users are shown a single generic message,
 * because distinguishing "nonce mismatch" from "audience mismatch" tells an
 * attacker how far a forged token got and tells a legitimate user nothing
 * useful.
 */
final class MicrosoftSignInFailed extends RuntimeException
{
    public function __construct(
        string $message,
        private readonly string $reason = 'sign_in_failed',
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public function reason(): string
    {
        return $this->reason;
    }

    public function userMessage(): string
    {
        return 'Sign-in with Microsoft could not be completed. Please try again.';
    }
}
