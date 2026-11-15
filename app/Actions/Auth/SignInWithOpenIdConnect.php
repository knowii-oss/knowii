<?php

namespace App\Actions\Auth;

use App\Actions\Fortify\CreateNewUser;
use App\Constants;
use App\Contracts\Auth\LinksIdentities;
use App\Contracts\Auth\SignsInWithOpenIdConnect;
use App\Exceptions\OpenIdConnectException;
use App\Models\User;
use App\Models\UserIdentity;
use App\Support\OpenIdIdentity;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Which platform account does an OpenID Connect identity sign in (#1385, ADR 0045)? The link is the pair issuer and
 * subject, never the address, except for the first sign-in:
 *
 * 0. A signed-in member who started the flow from the profile settings connects the account (the first case below
 *    would otherwise sign the member in as someone else). Refused when the provider account is connected to another
 *    member, or when the member already has an account at this provider.
 * 1. The pair exists: sign in its account.
 * 2. The provider does not say `email_verified: true` (the boolean), or sends no usable address: refused.
 * 3. A platform account has this address (compared without case) and verified it: connect and sign in.
 * 4. A platform account has this address and never verified it: connect, mark the address verified, remove the password
 *    and the second factor, and close the sessions and the API tokens. The provider proved the address, the person who
 *    created that account never did.
 * 5. Otherwise create the account through the normal creation path, without a password and with a verified address.
 *
 * An address that matches more than one account, or an account that already has another account at this provider,
 * is refused: never a guess.
 */
class SignInWithOpenIdConnect implements SignsInWithOpenIdConnect
{
    /**
     * The name of the provider goes into the name of the personal community too ("<name>'s Space"): a long name is cut
     */
    private const int MAX_NAME_LENGTH = 100;

    public function __construct(
        private readonly LinksIdentities $linksIdentities,
        private readonly CreateNewUser $createsUsers,
    ) {}

    final public function resolve(OpenIdIdentity $identity, ?User $linking): User
    {
        if ($linking !== null) {
            $this->linksIdentities->link($linking, $identity)->forceFill(['last_used_at' => now()])->save();

            return $linking;
        }

        $existing = UserIdentity::query()->with('user')->where('issuer', $identity->issuer)->where('subject', $identity->subject)->first();
        if ($existing !== null) {
            $existing->forceFill(['last_used_at' => now()])->save();

            return $existing->user;
        }

        if (! $identity->emailVerified) {
            throw new OpenIdConnectException('email_not_verified', 'The provider has not verified your email address. Verify it there, then try again.');
        }
        $email = self::usableEmail($identity->email);
        if ($email === null) {
            throw new OpenIdConnectException('no_email', 'The provider did not send an email address that we can use. Add one to your account there, then try again.');
        }

        try {
            return DB::transaction(fn (): User => $this->signInByAddress($identity, $email));
        } catch (UniqueConstraintViolationException) {
            // Two sign-ins at once for the same person: the indexes refused the second one
            throw new OpenIdConnectException('conflict', 'We could not finish this sign-in. Please try again.');
        }
    }

    private function signInByAddress(OpenIdIdentity $identity, string $email): User
    {
        $accounts = User::query()->whereRaw('lower(email) = ?', [mb_strtolower($email)])->lockForUpdate()->get();
        if ($accounts->count() > 1) {
            Log::warning('OpenID Connect: an address matches several accounts', ['issuer' => $identity->issuer]);
            throw new OpenIdConnectException('ambiguous_address', 'More than one account has this address. Please contact the administrators of this site.');
        }

        $user = $accounts->first();
        if ($user === null) {
            return $this->createAccount($identity, $email);
        }

        if (UserIdentity::query()->where('user_id', $user->id)->where('issuer', $identity->issuer)->exists()) {
            // The account is connected to another account at this provider: the address alone proves nothing here
            throw new OpenIdConnectException('issuer_already_linked', 'The account with this address is connected to another account at this provider. Sign in the way you did before, or ask the administrators for help.');
        }

        $takeOver = ! $user->hasVerifiedEmail();
        $this->linksIdentities->link($user, $identity);

        if ($takeOver) {
            $this->takeOver($user);
        }

        return $user;
    }

    /**
     * An account whose address was never verified: whoever created it never proved that the address is theirs. The
     * owner of the address proved it at the provider, so the account is theirs now (decision of the owner, 2026-11-15).
     */
    private function takeOver(User $user): void
    {
        $user->forceFill([
            'email_verified_at' => now(),
            // The old password cannot be used any more
            'password' => null,
            'remember_token' => Str::random(60),
            // A second factor that the creator set up is theirs too
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();

        DB::table('sessions')->where('user_id', $user->id)->delete();
        $user->tokens()->delete();
        DB::table('password_reset_tokens')->where('email', $user->email)->delete();

        Log::notice('OpenID Connect: took over an account whose address was never verified', ['user' => $user->cuid]);
    }

    private function createAccount(OpenIdIdentity $identity, string $email): User
    {
        $name = self::cleanName($identity->name) ?? self::cleanName(Str::before($email, '@')) ?? 'Member';
        $user = $this->createsUsers->createAccount($name, $email, null, true);

        $this->linksIdentities->link($user, $identity);
        Log::info('OpenID Connect: created an account', ['user' => $user->cuid, 'issuer' => $identity->issuer]);

        return $user;
    }

    /**
     * The address of the provider, if it is a plain address that fits the column
     */
    private static function usableEmail(?string $email): ?string
    {
        $email = $email === null ? '' : trim($email);

        return $email !== '' && mb_strlen($email) <= Constants::MAX_LENGTH_USER_EMAIL && filter_var($email, FILTER_VALIDATE_EMAIL) !== false ? $email : null;
    }

    private static function cleanName(?string $name): ?string
    {
        $name = trim(preg_replace('/[\p{C}]+/u', ' ', (string) $name) ?? '');
        $name = mb_substr($name, 0, self::MAX_NAME_LENGTH);

        return $name === '' ? null : $name;
    }
}
