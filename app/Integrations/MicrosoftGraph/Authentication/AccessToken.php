<?php

declare(strict_types=1);

namespace App\Integrations\MicrosoftGraph\Authentication;

use Carbon\CarbonImmutable;
use SensitiveParameter;

/**
 * An OAuth access token for one tenant.
 *
 * The token value is deliberately awkward to get at: it is readable only
 * through {@see self::value()}, and the object hides it from var_dump, logs
 * and exception traces via __debugInfo().
 */
final class AccessToken
{
    public function __construct(
        #[SensitiveParameter]
        private readonly string $token,
        public readonly CarbonImmutable $expiresAt,
    ) {}

    public function value(): string
    {
        return $this->token;
    }

    public function authorizationHeader(): string
    {
        return 'Bearer '.$this->token;
    }

    /**
     * Treats a token as expired slightly early so it cannot lapse between the
     * check and Graph receiving the request.
     */
    public function isExpired(?int $earlySeconds = null): bool
    {
        $earlySeconds ??= (int) config('graph.token_cache.early_expiry_seconds', 300);

        return $this->expiresAt->subSeconds($earlySeconds)->isPast();
    }

    /**
     * Seconds remaining before this token should be replaced. Never negative.
     */
    public function secondsUntilRefresh(?int $earlySeconds = null): int
    {
        $earlySeconds ??= (int) config('graph.token_cache.early_expiry_seconds', 300);

        return max(0, (int) now()->diffInSeconds($this->expiresAt->subSeconds($earlySeconds), absolute: false));
    }

    /**
     * @return array<string, mixed>
     */
    public function __debugInfo(): array
    {
        return [
            'token' => '[redacted]',
            'expiresAt' => $this->expiresAt->toIso8601String(),
        ];
    }

    public function __toString(): string
    {
        return '[redacted access token]';
    }
}
