<?php

namespace App\Support;

use App\Constants;
use App\Enums\KnowiiAccountDeletionProof;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * Which proof the deletion of an account needs, and whether the proof of a new sign-in holds now (#1521, ADR 0052). The
 * proof is recorded by ConfirmAccountDeletion and used by DeleteUser::deleteAfterSignIn. Also the check of the code of
 * two-factor authentication of a deletion, for the page and for the API.
 *
 * 1. An account with a usable password: the password (nothing changes).
 * 2. No password, a connected account of the provider of the instance, and the provider configured: a new sign-in at the
 *    provider, recorded on the account (users.deletion_signed_in_at), so that a web session and an API token read it.
 * 3. No password and no way to make the proof: a refusal that tells the member to write to the operator of the site.
 */
final class AccountDeletion
{
    /**
     * The anchor of the deletion section on the profile page
     */
    public const string SECTION_ANCHOR = 'delete-account';

    public const string UNAVAILABLE_MESSAGE = 'This account has no password and no connected sign-in account, so it cannot confirm its deletion here. Please write to the operator of this site to have it deleted.';

    /**
     * The code of two-factor authentication, when it is on, for every account: with a password or with the proof of a
     * new sign-in, on the page and on the API. A few wrong codes a minute per account, counted together for both. The
     * password, or the proof of the sign-in, is checked before it.
     *
     * A code is counted before it is checked, in one step of the cache, as a try of the password (App\Support\
     * PasswordTries): codes that are sent at the same time cannot pass the limit together. Only a wrong code counts: a
     * right one gives its own place back, and a request without a code takes none.
     *
     * @throws ValidationException
     */
    public static function checkSecondFactor(User $user, ?string $code): void
    {
        if (! $user->hasEnabledTwoFactorAuthentication()) {
            return;
        }

        if ($code === null || $code === '') {
            throw ValidationException::withMessages(['code' => __('Enter the code of your authenticator app or a recovery code.')]);
        }

        // Counted first: this code has its place before it is read
        $key = 'account-deletion-code:'.$user->cuid;
        if (RateLimiter::increment($key, 60) > Constants::ACCOUNT_DELETIONS_PER_MINUTE) {
            throw ValidationException::withMessages(['code' => __('Too many tries. Please wait a minute, then try again.')]);
        }
        if (! SecondFactor::passesEither($user, $code)) {
            Log::warning('Account deletion refused', ['reason' => 'wrong_code', 'user' => $user->cuid]);
            throw ValidationException::withMessages(['code' => __('The provided two factor authentication code was invalid.')]);
        }
        RateLimiter::decrement($key, 60);
    }

    public static function proofOf(User $user): KnowiiAccountDeletionProof
    {
        if ($user->hasPassword()) {
            return KnowiiAccountDeletionProof::Password;
        }

        $issuer = OpenIdConnect::issuer();
        if (OpenIdConnect::enabled() && $issuer !== null && $user->identities()->where('issuer', $issuer)->exists()) {
            return KnowiiAccountDeletionProof::SignIn;
        }

        return KnowiiAccountDeletionProof::Unavailable;
    }

    /**
     * Until when the recorded proof holds: the time of the sign-in plus services.oidc.fresh_sign_in_seconds. Null when
     * there is no proof, or when it expired. Only an account without a password uses a proof.
     */
    public static function confirmedUntil(User $user): ?CarbonImmutable
    {
        $signedInAt = $user->deletion_signed_in_at;
        if ($signedInAt === null || $user->hasPassword()) {
            return null;
        }

        $until = CarbonImmutable::instance($signedInAt)->addSeconds(OpenIdConnect::freshSignInSeconds());

        return $until->isFuture() ? $until : null;
    }

    /**
     * The address of the page where the member makes the proof: the deletion section of the profile settings
     */
    public static function pageUrl(): string
    {
        return route('profile.show').'#'.self::SECTION_ANCHOR;
    }

    /**
     * The answer of the API to an account of case 2 without a valid proof: what to do, and where
     */
    public static function signInNeededMessage(): string
    {
        return sprintf(
            'A new sign-in is needed to delete this account. Open %s, choose "Confirm with %s", then send this request again within %s.',
            self::pageUrl(),
            OpenIdConnect::label(),
            self::duration(OpenIdConnect::freshSignInSeconds()),
        );
    }

    /**
     * The answer of the web deletion to an account of case 2 without a valid proof
     */
    public static function webSignInNeededMessage(): string
    {
        return sprintf(
            'Confirm with %s first. Then you can delete your account for %s.',
            OpenIdConnect::label(),
            self::duration(OpenIdConnect::freshSignInSeconds()),
        );
    }

    /**
     * "5 minutes", "1 minute", "90 seconds"
     */
    public static function duration(int $seconds): string
    {
        if ($seconds % 60 === 0) {
            $minutes = intdiv($seconds, 60);

            return $minutes.($minutes === 1 ? ' minute' : ' minutes');
        }

        return $seconds.' seconds';
    }
}
