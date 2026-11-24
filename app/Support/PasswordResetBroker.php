<?php

namespace App\Support;

use Illuminate\Auth\Passwords\DatabaseTokenRepository;
use Illuminate\Auth\Passwords\PasswordBroker;
use Illuminate\Contracts\Auth\CanResetPassword;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Password;
use RuntimeException;

/**
 * The broker of the page of a password reset (`POST /reset-password`): the broker of the framework, with one more rule. Its
 * check of a link does the same work for every address: one hash check, against the token of the reset row of the account,
 * or against App\Support\DummyPassword when no account has the address, when the account has no reset row, or when the row
 * is too old. The framework compares no hash in these cases. The timebox of the broker stays around it.
 *
 * The page answers the same words for every link that does not work (App\Http\Responses\FailedPasswordResetResponse).
 */
final class PasswordResetBroker extends PasswordBroker
{
    /**
     * The broker of the configuration of Fortify, with the token store, the accounts and the timebox of the framework
     */
    public static function make(): self
    {
        $name = config()->string('fortify.passwords', 'users');
        $broker = Password::broker($name);
        if (! $broker instanceof PasswordBroker) {
            throw new RuntimeException('The password broker is not the broker of the framework');
        }
        $provider = config('auth.passwords.'.$name.'.provider');
        $users = app('auth')->createUserProvider(is_string($provider) ? $provider : null);
        if ($users === null) {
            throw new RuntimeException('The password broker has no user provider');
        }
        $timebox = config('auth.timebox_duration', 200000);

        return new self(
            $broker->getRepository(),
            $users,
            app('events'),
            timeboxDuration: is_int($timebox) && $timebox > 0 ? $timebox : 200000,
        );
    }

    /**
     * @param  array<string, array<array-key, array<array-key, scalar|null>|scalar|null>|scalar|null>  $credentials  The request input: the token can be a value that is no text
     * @return CanResetPassword|string
     */
    protected function validateReset(#[\SensitiveParameter] array $credentials)
    {
        $tokens = $this->tokens;
        if (! $tokens instanceof DatabaseTokenRepository) {
            return parent::validateReset($credentials);
        }

        $token = $credentials['token'] ?? null;
        $token = is_string($token) ? $token : '';
        $user = $this->getUser($credentials);
        $stored = $user === null ? null : $this->storedToken($tokens, $user);

        // One hash check in every case: the stored token, or the dummy hash when there is none to compare
        $matches = $tokens->getHasher()->check($token, $stored ?? DummyPassword::hash());

        if ($user === null) {
            return self::INVALID_USER;
        }

        return $stored !== null && $matches ? $user : self::INVALID_TOKEN;
    }

    /**
     * The hash of the token of the reset row of the account, while the row is not too old
     */
    private function storedToken(DatabaseTokenRepository $tokens, CanResetPassword $user): ?string
    {
        $name = config()->string('fortify.passwords', 'users');
        $table = config('auth.passwords.'.$name.'.table');
        $expire = config('auth.passwords.'.$name.'.expire', 60);
        if (! is_string($table)) {
            return null;
        }

        $row = $tokens->getConnection()->table($table)->where('email', $user->getEmailForPasswordReset())->first();
        $hash = $row->token ?? null;
        $createdAt = $row->created_at ?? null;
        if (! is_string($hash) || ! is_string($createdAt)) {
            return null;
        }

        $minutes = is_numeric($expire) ? (int) $expire : 60;

        return Carbon::parse($createdAt)->addMinutes($minutes)->isPast() ? null : $hash;
    }
}
