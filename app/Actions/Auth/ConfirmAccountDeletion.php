<?php

namespace App\Actions\Auth;

use App\Contracts\Auth\ConfirmsAccountDeletions;
use App\Enums\KnowiiAccountDeletionProof;
use App\Exceptions\OpenIdConnectException;
use App\Models\User;
use App\Support\AccountDeletion;
use App\Support\OpenIdConnect;
use App\Support\OpenIdIdentity;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;

/**
 * Record the proof of a new sign-in for the deletion of an account without a password (#1521, ADR 0052). The callback of
 * the provider calls it after the checks of the ID token (issuer, audience, signature, expiry, nonce), and after it
 * checked that the member who comes back is the one who started the proof. Then, in this order:
 *
 * 1. The account still has no password and is still connected to an account of the provider of the instance.
 * 2. `auth_time` is present and an integer. This is the proof: the browser can remove `max_age` from the request, and a
 *    provider can ignore it.
 * 3. `auth_time` is at most services.oidc.fresh_sign_in_seconds old, and not more than 60 seconds in the future.
 * 4. `auth_time` is not before the start of this flow (60 seconds of clock difference allowed). A provider that ignores
 *    `max_age` and `prompt` sends the time of an older sign-in: the sign-in that opened the session of the platform a
 *    minute ago is not a new sign-in for this proof.
 * 5. `sub` is exactly the identifier of the identity that is connected to this account.
 *
 * The proof is the time of the sign-in on the account (users.deletion_signed_in_at). It holds for
 * fresh_sign_in_seconds after `auth_time`, and the deletion that uses it clears it. This action deletes nothing: the
 * callback is a GET.
 */
final class ConfirmAccountDeletion implements ConfirmsAccountDeletions
{
    /**
     * The clock of the provider may be a little ahead of ours
     */
    public const int CLOCK_SKEW_SECONDS = 60;

    public const string NO_NEW_SIGN_IN_MESSAGE = 'The sign-in provider did not confirm a new sign-in, so we cannot confirm the deletion. Your account was not changed.';

    final public function confirm(User $member, OpenIdIdentity $identity, int $flowStartedAt): CarbonImmutable
    {
        $proof = AccountDeletion::proofOf($member);
        if ($proof === KnowiiAccountDeletionProof::Password) {
            throw new OpenIdConnectException('has_password', 'This account has a password: confirm the deletion with your password. Your account was not changed.');
        }
        $connected = $member->identities()->where('issuer', $identity->issuer)->first();
        if ($proof !== KnowiiAccountDeletionProof::SignIn || $connected === null) {
            throw new OpenIdConnectException('no_identity', AccountDeletion::UNAVAILABLE_MESSAGE);
        }

        $authTime = $identity->authTime;
        if ($authTime === null) {
            throw new OpenIdConnectException('no_auth_time', self::NO_NEW_SIGN_IN_MESSAGE);
        }
        $now = now()->getTimestamp();
        $seconds = OpenIdConnect::freshSignInSeconds();
        if ($authTime < $now - $seconds) {
            throw new OpenIdConnectException('old_auth_time', 'This sign-in was not new enough to confirm the deletion. Please try again, and sign in when the provider asks you to. Your account was not changed.');
        }
        if ($authTime < $flowStartedAt - self::CLOCK_SKEW_SECONDS) {
            throw new OpenIdConnectException('sign_in_before_flow', 'The sign-in provider did not ask you to sign in again, so we cannot confirm the deletion. Please try again, and sign in when the provider asks you to. Your account was not changed.');
        }
        if ($authTime > $now + self::CLOCK_SKEW_SECONDS) {
            throw new OpenIdConnectException('future_auth_time', 'The time of this sign-in could not be checked. Please try again. Your account was not changed.');
        }

        if (! hash_equals($connected->subject, $identity->subject)) {
            throw new OpenIdConnectException('wrong_subject', 'You signed in with another account than the one that is connected here. Please try again with the connected account. Your account was not changed.');
        }

        $signedInAt = CarbonImmutable::createFromTimestamp($authTime);
        $member->forceFill(['deletion_signed_in_at' => $signedInAt])->save();
        Log::info('Account deletion confirmed with a new sign-in', ['user' => $member->cuid, 'issuer' => $identity->issuer]);

        return $signedInAt->addSeconds($seconds);
    }
}
