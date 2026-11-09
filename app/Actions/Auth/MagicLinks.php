<?php

namespace App\Actions\Auth;

use App\Contracts\Auth\ConsumesMagicLinks;
use App\Contracts\Auth\SendsMagicLinks;
use App\Mail\MagicLink;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Sign-in links (#703, ADR 0023): a random token, stored hashed in the cache for VALIDITY_MINUTES, used once.
 */
class MagicLinks implements ConsumesMagicLinks, SendsMagicLinks
{
    public const int VALIDITY_MINUTES = 15;

    /**
     * @throws ValidationException
     */
    final public function send(array $input): void
    {
        /** @var array{email: string} $validated */
        $validated = Validator::make($input, ['email' => ['required', 'string', 'email', 'max:255']])->validate();

        $user = User::query()->whereRaw('lower(email) = ?', [mb_strtolower(trim($validated['email']))])->first();
        if ($user === null) {
            // Same answer as for an existing account: the form never tells who has an account
            Log::info('Sign-in link asked for an unknown address');

            return;
        }

        $token = Str::random(64);
        Cache::put(self::key($token), $user->id, now()->addMinutes(self::VALIDITY_MINUTES));

        Mail::to($user->email)->queue(new MagicLink($token));
        Log::info('Sign-in link sent', ['user' => $user->cuid]);
    }

    final public function peek(string $token): ?User
    {
        $userId = Cache::get(self::key($token));

        return is_int($userId) ? User::query()->find($userId) : null;
    }

    final public function consume(string $token): ?User
    {
        // pull(): read and delete at once, so a link works once
        $userId = Cache::pull(self::key($token));

        return is_int($userId) ? User::query()->find($userId) : null;
    }

    private static function key(string $token): string
    {
        return 'magic-link:'.hash('sha256', $token);
    }
}
