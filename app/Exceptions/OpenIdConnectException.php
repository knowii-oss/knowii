<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * A sign-in with an OpenID Connect provider failed (#1385, ADR 0045). `reason` is a short code for the logs (never a
 * token, a code, a subject or an address). The message is a plain sentence for the member: it never repeats what the
 * provider or the token said.
 */
final class OpenIdConnectException extends RuntimeException
{
    public const string GENERIC_MESSAGE = 'We could not sign you in with this account. Please try again, or use another way to sign in.';

    public function __construct(public readonly string $reason, string $message = self::GENERIC_MESSAGE)
    {
        parent::__construct($message);
    }
}
