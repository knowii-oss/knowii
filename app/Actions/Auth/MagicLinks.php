<?php

namespace App\Actions\Auth;

use App\Contracts\Auth\ConsumesMagicLinks;
use App\Contracts\Auth\SendsMagicLinks;
use App\Jobs\SendSignInLink;
use App\Mail\MagicLink;
use App\Models\User;
use App\Support\AccountMailCaps;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Sign-in links (#703, ADR 0023): a random token, stored hashed in the cache for VALIDITY_MINUTES, used once. The page
 * of a community that the person was reading is kept beside it, for the same time (#1612, ADR 0057).
 *
 * A link works for the address that it was sent to (ADR 0058): its entry holds the account and an HMAC of that address,
 * and the link gives no account when the account has another address now. So a link always shows that the person reads
 * the mailbox of the address that the account has.
 */
class MagicLinks implements ConsumesMagicLinks, SendsMagicLinks
{
    public const int VALIDITY_MINUTES = 15;

    /**
     * At most one sign-in link per account in this time
     */
    public const int SECONDS_BETWEEN_LINKS = 60;

    /**
     * The form of the sign-in link gives the same answer for every address (documentation/architecture/backend.md): the
     * request checks the format of the address and queues one job for it (App\Jobs\SendSignInLink), which calls deliver().
     * The request never reads an account.
     *
     * @throws ValidationException
     */
    final public function send(array $input, ?string $landing = null): void
    {
        /** @var array{email: string} $validated */
        $validated = Validator::make($input, ['email' => ['required', 'string', 'email', 'max:255']])->validate();

        SendSignInLink::dispatch(mb_strtolower(trim($validated['email'])), $landing);
    }

    /**
     * The work of the job: when an account has the address, without regard to the case, a new link goes to the address of
     * the account, at most one a minute per account. An address without an account is one query
     */
    final public function deliver(string $email, ?string $landing = null): void
    {
        $user = User::query()->whereRaw('lower(email) = ?', [mb_strtolower(trim($email))])->first();
        if ($user === null) {
            // Same answer as for an existing account: the form never tells who has an account
            Log::info('Sign-in link asked for an unknown address');

            return;
        }

        // At most one link a minute per account, whatever the IP address: a second request in that minute sends nothing
        if (! Cache::add(self::sentKey($user), true, self::SECONDS_BETWEEN_LINKS)) {
            Log::info('Sign-in link not sent: one was sent less than a minute ago', ['user' => $user->cuid]);

            return;
        }

        // At most a few sign-in links a day per address (App\Support\AccountMailCaps): a link over the cap is not sent
        if (! AccountMailCaps::take(AccountMailCaps::SIGN_IN_LINK, $user->email)) {
            Log::info('Sign-in link not sent: the address got all its sign-in links of the day', ['user' => $user->cuid]);

            return;
        }

        $token = Str::random(64);
        // The account, and an HMAC of the address that gets the mail (ADR 0058): never the address itself
        Cache::put(self::key($token), ['user' => $user->id, 'address' => self::addressHash($user->email)], now()->addMinutes(self::VALIDITY_MINUTES));
        // The page of a community that the person was reading (#1612, ADR 0057): a second entry with the life of the token
        if ($landing !== null) {
            Cache::put(self::landingKey($token), $landing, now()->addMinutes(self::VALIDITY_MINUTES));
        }

        Mail::to($user->email)->queue((new MagicLink($token))->recordDelivery($user));
        Log::info('Sign-in link sent', ['user' => $user->cuid]);
    }

    final public function peek(string $token): ?User
    {
        return self::account($token, false);
    }

    final public function consume(string $token): ?User
    {
        $user = self::account($token, true);
        // Nothing stays in the cache after a link is used
        Cache::forget(self::landingKey($token));

        return $user;
    }

    final public function pullLanding(string $token): ?string
    {
        $landing = Cache::pull(self::landingKey($token));

        return is_string($landing) ? $landing : null;
    }

    /**
     * The account of a link, or null: no entry (an unknown token, a link that was used, a link that expired), an entry in
     * another shape, an account that is gone, or an account whose address is not the one that the link was sent to.
     */
    private static function account(string $token, bool $useUp): ?User
    {
        // pull(): read and delete at once, so a link works once
        $entry = $useUp ? Cache::pull(self::key($token)) : Cache::get(self::key($token));
        if (! is_array($entry)) {
            return null;
        }

        $userId = $entry['user'] ?? null;
        $address = $entry['address'] ?? null;
        if (! is_int($userId) || ! is_string($address)) {
            return null;
        }

        $user = User::query()->find($userId);

        return $user !== null && hash_equals($address, self::addressHash($user->email)) ? $user : null;
    }

    /**
     * The address of an account as an entry keeps it (ADR 0058): an HMAC-SHA256 with the key of the application, compared
     * character for character. Without the key, the value tells nothing about the address, also to a list of addresses
     */
    private static function addressHash(string $email): string
    {
        return hash_hmac('sha256', $email, config()->string('app.key'));
    }

    private static function sentKey(User $user): string
    {
        return 'magic-link-sent:'.$user->cuid;
    }

    private static function key(string $token): string
    {
        return 'magic-link:'.hash('sha256', $token);
    }

    private static function landingKey(string $token): string
    {
        return 'magic-link-landing:'.hash('sha256', $token);
    }
}
