<?php

namespace App\Support;

use App\Models\User;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Laravel\Fortify\Fortify;

/**
 * The second factor of an account with two-factor authentication (#1256, #1492): the code of the authenticator app, or a
 * recovery code. A recovery code that passes is replaced, so it works once. Shared by the API sign-in and by the
 * deletion of the own account.
 */
final class SecondFactor
{
    /**
     * Check a code of the authenticator app, or, when `$code` is empty, a recovery code.
     */
    public static function passes(User $user, ?string $code, ?string $recoveryCode): bool
    {
        if ($code !== null && $code !== '') {
            return self::passesCode($user, $code);
        }

        return $recoveryCode !== null && $recoveryCode !== '' && self::consumeRecoveryCode($user, $recoveryCode);
    }

    /**
     * One field that holds either kind: the code of the authenticator app, else a recovery code.
     */
    public static function passesEither(User $user, string $value): bool
    {
        return self::passesCode($user, $value) || self::consumeRecoveryCode($user, $value);
    }

    private static function passesCode(User $user, string $code): bool
    {
        $secret = Fortify::currentEncrypter()->decrypt((string) $user->two_factor_secret);

        return is_string($secret) && app(TwoFactorAuthenticationProvider::class)->verify($secret, $code);
    }

    private static function consumeRecoveryCode(User $user, string $recoveryCode): bool
    {
        foreach ($user->recoveryCodes() as $stored) {
            if (is_string($stored) && hash_equals($stored, $recoveryCode)) {
                $user->replaceRecoveryCode($stored);

                return true;
            }
        }

        return false;
    }
}
