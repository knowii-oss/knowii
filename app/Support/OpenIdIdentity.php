<?php

namespace App\Support;

/**
 * What the ID token of an OpenID Connect provider says about the person, after the checks of the client (#1385, ADR
 * 0045). Nothing here is stored except the subject (and the issuer, which the client knows): the address and the name
 * only serve to find or create the platform account.
 */
final readonly class OpenIdIdentity
{
    public function __construct(
        public string $issuer,
        public string $subject,
        public ?string $email,
        /** True only when the token says `email_verified: true` (a boolean). Anything else is false. */
        public bool $emailVerified,
        public ?string $name,
    ) {}
}
