<?php

namespace App\Contracts\Auth;

use App\Enums\KnowiiAddressProof;
use App\Models\User;
use SensitiveParameter;

/**
 * The first proof of an address takes the account over (ADR 0059): a sign-in with a provider, a sign-in link or a password
 * reset shows, for the first time, that a person reads the mailbox of an account whose address was never verified
 * (User::addressWasNeverVerified()). The account is the account of that person from then on. A verified account is never
 * taken over: a change of its address applies only after the new address is confirmed (ADR 0058).
 */
interface TakesOverUnverifiedAccounts
{
    /**
     * Marks the address as verified, and removes everything that signed in or acted for the account before: the password,
     * the remember token, the second factor, the sessions (the version of the sessions goes up by 1, so a session of every
     * driver ends), the API tokens, the password reset tokens, the connections to a provider, the proof of a deletion and a
     * pending change of the address. After the commit, the live connections of the account on the broadcaster end. The
     * name, the username and the profile stay. The action reads the account again, locked, and works on that row; the given
     * model holds the values of that row after the call.
     *
     * @param  KnowiiAddressProof  $proof  The way that proved the address: it names the line of the log
     * @param  string  $provedAddress  The address that the proof was for: the address of the account when the link was
     *                                 checked, or the address that the provider vouched for
     * @param  string|null  $newPassword  The password that the person chose in a password reset, as they typed it: the
     *                                    account keeps it. Null: the account has no password after the call
     * @return bool Whether the account was taken over. False when the stored row has a verified address (another proof of
     *              the same address came first: the caller goes on as for a verified account), or another address than the
     *              proved one, without regard to the case (the caller does not go on: the proof is for an address that the
     *              account no longer has). Nothing changes then
     */
    public function takeOver(User $user, KnowiiAddressProof $proof, string $provedAddress, #[SensitiveParameter] ?string $newPassword = null): bool;
}
