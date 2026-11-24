<?php

namespace App\Contracts\Users;

use App\Enums\KnowiiEmailChangeResult;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * A change of the email address applies after the new address is confirmed (#1706, ADR 0058). The account keeps its
 * current, verified address, and every mail goes there, until the person opens the link that goes to the new address.
 */
interface ChangesEmailAddresses
{
    /**
     * Keeps the new address as the pending address of the account, with the time of the request, and queues the mail of
     * the new address (deliver()): the same work for a free address and for one that another account has. A new request
     * replaces the pending address of before: the link of that address does not work any more. The caller validated the
     * address (its form, its length) and the password; it does not check whether another account has the address.
     */
    public function request(User $user, string $address): void;

    /**
     * Queues the mail of the pending address again. The pending address keeps the time of its request.
     *
     * @throws ValidationException When no change waits
     */
    public function resend(User $user): User;

    /**
     * The work of the queued job, on the row of the account read again: nothing when the account has no pending address or
     * another one than the hash of the job; the mail "This address already has an account" (no link) when another account
     * has the pending address, which then stays; else the link to the pending address.
     *
     * @param  string  $addressHash  The hash of the pending address of the request
     */
    public function deliver(int $userId, string $addressHash): void;

    /**
     * Removes the pending address. Nothing happens when no change waits.
     */
    public function cancel(User $user): User;

    /**
     * Applies the change of the link. In one transaction, on the row of the account read again and locked: the pending
     * address must be the one of the link, and no other account may use it. Then the account has the new address,
     * verified, and a notice goes to the address of before when it was verified. When another account uses the address
     * now, the change is cancelled. The link of a replaced or cancelled address changes nothing.
     *
     * @param  string  $addressHash  The hash of the pending address that the link holds
     */
    public function confirm(User $user, string $addressHash): KnowiiEmailChangeResult;
}
