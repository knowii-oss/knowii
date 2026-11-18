<?php

namespace App\Enums;

/**
 * How an account proves that it wants to be deleted (#1521, ADR 0052)
 *
 * WARNING: Must remain in sync with AccountDeletionProof in jetstream-inertia.intf.ts
 */
enum KnowiiAccountDeletionProof: string
{
    /** The account has a password: the password, and the two-factor code when it is on */
    case Password = 'password';
    /** No password, a connected account of the provider of the instance: a new sign-in there, and the two-factor code when it is on */
    case SignIn = 'sign_in';
    /** No password and no way to make the proof: the member writes to the operator of the site */
    case Unavailable = 'unavailable';
}
