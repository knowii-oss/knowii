<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Traits\ApiResponses;
use Dedoc\Scramble\Attributes\BodyParameter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Laravel\Fortify\Fortify;

class LoginApiController extends Controller
{
    use ApiResponses;

    /**
     * Log in through the API. When the account has two-factor authentication, send `code` (from the authenticator
     * app) or `recovery_code` too: without one, the answer is a 401 with `metadata.two_factor_required` (#1256).
     */
    #[BodyParameter('email', description: 'Email address of the account', required: true, type: 'string', format: 'email')]
    #[BodyParameter('password', description: 'Password of the account', required: true, type: 'string')]
    #[BodyParameter('code', description: 'Code of the authenticator app, for an account with two-factor authentication', required: false, type: 'string', example: '123456')]
    #[BodyParameter('recovery_code', description: 'A recovery code instead of the code (each one works once)', required: false, type: 'string')]
    #[BodyParameter('includeToken', description: 'Return an API token valid for 12 hours', required: false, type: 'bool')]
    final public function login(Request $request): JsonResponse
    {
        /** @var array{email: string, password: string, code?: string|null, recovery_code?: string|null} $input */
        $input = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'code' => ['nullable', 'string', 'max:16'],
            'recovery_code' => ['nullable', 'string', 'max:64'],
        ]);

        // Check the password without logging in: the session starts only after every factor (#1256)
        $user = User::query()->where('email', $input['email'])->first();
        if ($user === null || ! Hash::check($input['password'], $user->password)) {
            return self::authenticationIssue('The provided credentials do not match our records.');
        }

        if ($user->hasEnabledTwoFactorAuthentication()) {
            $code = $input['code'] ?? null;
            $recoveryCode = $input['recovery_code'] ?? null;

            if (($code === null || $code === '') && ($recoveryCode === null || $recoveryCode === '')) {
                return self::authenticationIssue('Enter the code of your authenticator app or a recovery code.', ['two_factor_required' => true]);
            }

            if (! $this->passesSecondFactor($user, $code, $recoveryCode)) {
                Log::warning('API login: wrong two-factor code', ['user' => $user->cuid]);

                return self::authenticationIssue('The two-factor code is not valid.', ['two_factor_required' => true]);
            }
        }

        Auth::login($user);
        Log::info('Authenticated through the API', ['user' => $user->cuid]);

        // Initialize the session
        session()->regenerate();

        if ($request->boolean('includeToken')) {
            $tokenValidUntil = now()->addHours(12);
            $token = $user->createToken('api', [], $tokenValidUntil)->plainTextToken;

            return self::success("Welcome to Knowii's API 🎉", null, [
                'token' => $token,
                'tokenValidUntil' => $tokenValidUntil,
            ]);
        }

        return self::success("Welcome to Knowii's API 🎉", null, null);
    }

    /**
     * The code of the authenticator app, or a recovery code that is then replaced (the same rules as the web login).
     */
    private function passesSecondFactor(User $user, ?string $code, ?string $recoveryCode): bool
    {
        if ($code !== null && $code !== '') {
            $secret = Fortify::currentEncrypter()->decrypt((string) $user->two_factor_secret);

            return is_string($secret) && app(TwoFactorAuthenticationProvider::class)->verify($secret, $code);
        }

        foreach ($user->recoveryCodes() as $stored) {
            if (is_string($stored) && hash_equals($stored, (string) $recoveryCode)) {
                $user->replaceRecoveryCode($stored);

                return true;
            }
        }

        return false;
    }
}
