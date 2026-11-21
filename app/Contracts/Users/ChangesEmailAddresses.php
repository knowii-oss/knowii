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
     * Keeps the new address as the pending address of the account, with the time of the request, and sends the link to
     * it. A new request replaces the pending address of before: the link of that address does not work any more. The
     * caller validated the address (its form, its length, no other account has it) and the password.
     */
    public function request(User $user, string $address): void;

    /**
     * Sends the link to the pending address again. The pending address keeps the time of its request.
     *
     * @throws ValidationException When no change waits
     */
    public function resend(User $user): User;

    /**
     * Removes the pending address. Nothing happens when no change waits.
     */
    public function cancel(User $user): User;

    /**
     * Applies the change of the link. In one transaction, on the row of the account read again and locked: the pending
     * address must be the one of the link, and no other account may use it. Then the account has the new address,
     * verified, and a notice goes to the address of before. When another account uses the address now, the change is
     * cancelled. The link of a replaced or cancelled address changes nothing.
     *
     * @param  string  $addressHash  The hash of the pending address that the link holds
     */
    public function confirm(User $user, string $addressHash): KnowiiEmailChangeResult;
}
