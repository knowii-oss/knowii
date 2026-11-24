<?php

namespace App\Http\Controllers\Inertia;

use App\Models\User;
use App\Support\SecondFactorTries;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\FailedTwoFactorLoginResponse;
use Laravel\Fortify\Contracts\TwoFactorLoginResponse;
use Laravel\Fortify\Events\TwoFactorAuthenticationFailed;
use Laravel\Fortify\Events\ValidTwoFactorAuthenticationCodeProvided;
use Laravel\Fortify\Http\Controllers\TwoFactorAuthenticatedSessionController as FortifyTwoFactorAuthenticatedSessionController;
use Laravel\Fortify\Http\Requests\TwoFactorLoginRequest;
use Symfony\Component\HttpFoundation\Response;

/**
 * The challenge of the second factor of the web sign-in (Fortify). The step of Fortify, with the tries of the account: a
 * code or a recovery code counts with every other place that checks a code (App\Support\SecondFactorTries), whatever the
 * IP address and the session of the request. When the account has no try left, the code is refused, also a right one, and
 * the page says how long to wait. The tries of the account are the only limit of the challenge: a request without a code
 * is an error of the form, and counts nothing.
 */
class TwoFactorAuthenticatedSessionController extends FortifyTwoFactorAuthenticatedSessionController
{
    /**
     * @return Response|TwoFactorLoginResponse The answer of a wrong code, or the answer of the sign-in
     *
     * @throws ValidationException When the account has no try left
     */
    public function store(TwoFactorLoginRequest $request)
    {
        /** @var User $user */
        $user = $request->challengedUser();

        // A request without a code is an error of the form: it takes no try
        if (! $request->filled('code') && ! $request->filled('recovery_code')) {
            throw ValidationException::withMessages([
                'code' => __('Enter the code of your authenticator app or a recovery code.'),
            ])->redirectTo(route('two-factor.login'));
        }

        $recoveryCode = null;
        try {
            $right = SecondFactorTries::check($user, static function () use ($request, &$recoveryCode): bool {
                $recoveryCode = $request->validRecoveryCode();

                return $recoveryCode !== null || $request->hasValidCode();
            });
        } catch (ThrottleRequestsException $e) {
            throw ValidationException::withMessages([
                $request->filled('recovery_code') ? 'recovery_code' : 'code' => SecondFactorTries::waitWords($e),
            ])->redirectTo(route('two-factor.login'));
        }

        if (! $right) {
            event(new TwoFactorAuthenticationFailed($user));

            return app(FailedTwoFactorLoginResponse::class)->toResponse($request);
        }

        if (is_string($recoveryCode)) {
            $user->replaceRecoveryCode($recoveryCode);
        }

        event(new ValidTwoFactorAuthenticationCodeProvided($user));

        $this->guard->login($user, $request->remember());

        $request->session()->regenerate();

        return app(TwoFactorLoginResponse::class);
    }
}
