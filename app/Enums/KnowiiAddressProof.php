<?php

namespace App\Enums;

/**
 * A way to prove that a person reads the mailbox of the address of an account (ADR 0059). The first proof of an address
 * takes the account over: the proof names the way in the line of the log. Only the server uses it.
 */
enum KnowiiAddressProof: string
{
    /** A sign-in with the OpenID Connect provider of the instance, which says that the address is verified (ADR 0045) */
    case Provider = 'provider';
    /** A sign-in link of the mail (ADR 0023) */
    case SignInLink = 'sign_in_link';
    /** A password reset with the token of the mail */
    case PasswordReset = 'password_reset';

    /**
     * The line of the log of a takeover by this way. The one of the provider is the line of before (ADR 0045), so a filter
     * on it still matches.
     */
    public function takeoverLogLine(): string
    {
        return match ($this) {
            self::Provider => 'OpenID Connect: took over an account whose address was never verified',
            self::SignInLink => 'Sign-in link: took over an account whose address was never verified',
            self::PasswordReset => 'Password reset: took over an account whose address was never verified',
        };
    }
}
