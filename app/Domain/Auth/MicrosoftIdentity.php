<?php

declare(strict_types=1);

namespace App\Domain\Auth;

/**
 * Verified claims from a Microsoft id token.
 *
 * Only claims that have survived signature and issuer verification are placed
 * in here, so anything holding one of these can trust its contents.
 */
final readonly class MicrosoftIdentity
{
    public function __construct(
        /** The `oid` claim: immutable, and the only safe permanent identifier. */
        public string $objectId,

        /** The `tid` claim: the Entra directory the account belongs to. */
        public string $tenantId,

        /** Usually the UPN. May change over the account's lifetime, so it is never used as an identifier. */
        public string $email,

        public string $name,
    ) {}

    public function displayName(): string
    {
        return $this->name !== '' ? $this->name : $this->email;
    }
}
