<?php

namespace App\Http\Controllers\Inertia;

use App\Constants;
use App\Contracts\Users\DeletesAccounts;
use App\Enums\KnowiiAccountDeletionProof;
use App\Exceptions\BusinessException;
use App\Models\User;
use App\Support\AccountDeletion;
use App\Support\SecondFactor;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Laravel\Fortify\Actions\ConfirmPassword;
use Symfony\Component\HttpFoundation\Response;

/**
 * Deletes the account of the member (#1261, ADR 0024), with the option to delete their posts, comments and messages
 * too (#1344, ADR 0043). It takes the place of the Jetstream controller on the same route (`current-user.destroy`),
 * which cannot pass an option to the action. The action validates the option and does the work.
 *
 * The proof of the intent (#1521, ADR 0052): the password for an account that has one, with the code of two-factor
 * authentication when it is on, as on the API. An account without a password
 * uses the proof of a new sign-in at the provider (made a moment ago from the same screen, recorded on the account),
 * and the two-factor code when two-factor authentication is on. An account without a password and without a way to make
 * the proof is refused.
 */
class DeleteAccountController extends Controller
{
    final public function destroy(Request $request, StatefulGuard $guard): Response
    {
        $user = $request->user();
        $proof = $user === null ? KnowiiAccountDeletionProof::Password : AccountDeletion::proofOf($user);

        if ($user !== null && $proof !== KnowiiAccountDeletionProof::Password) {
            $this->deleteAfterSignIn($request, $user, $proof);
        } else {
            $password = $request->input('password');

            // A few tries a minute, as on the API: a session that someone else holds cannot guess the password
            $key = 'account-deletion-password:'.($user->cuid ?? $request->ip());
            if (RateLimiter::tooManyAttempts($key, Constants::ACCOUNT_DELETIONS_PER_MINUTE)) {
                throw ValidationException::withMessages(['password' => __('Too many tries. Please wait a minute, then try again.')]);
            }

            if ($user === null || ! is_string($password) || ! app(ConfirmPassword::class)($guard, $user, $password)) {
                RateLimiter::hit($key, 60);
                if ($user !== null) {
                    Log::warning('Account deletion refused', ['reason' => 'wrong_password', 'user' => $user->cuid]);
                }
                throw ValidationException::withMessages([
                    'password' => __('The password is incorrect.'),
                ]);
            }

            // The option is valid before a recovery code can be used up
            Validator::make($request->only(['delete_content', 'code']), [
                'delete_content' => ['sometimes', 'boolean'],
                'code' => ['nullable', 'string', 'max:64'],
            ])->validate();
            $this->checkSecondFactor($request, $user);

            app(DeletesAccounts::class)->delete($user->fresh() ?? $user, $request->only(['delete_content']));
        }

        $guard->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Inertia::location(url('/'));
    }

    /**
     * An account without a password: the recorded proof of a new sign-in, then the two-factor code when it is on. The
     * action checks the proof again and uses it up in the transaction of the deletion. A password sent along is ignored.
     *
     * @throws ValidationException
     */
    private function deleteAfterSignIn(Request $request, User $user, KnowiiAccountDeletionProof $proof): void
    {
        if ($proof === KnowiiAccountDeletionProof::Unavailable) {
            Log::warning('Account deletion refused', ['reason' => 'no_way_to_prove', 'user' => $user->cuid]);
            throw ValidationException::withMessages(['sign_in' => AccountDeletion::UNAVAILABLE_MESSAGE]);
        }
        if (AccountDeletion::confirmedUntil($user) === null) {
            Log::warning('Account deletion refused', ['reason' => 'no_valid_proof', 'user' => $user->cuid]);
            throw ValidationException::withMessages(['sign_in' => AccountDeletion::webSignInNeededMessage()]);
        }

        // The option is valid before a recovery code can be used up
        Validator::make($request->only(['delete_content', 'code']), [
            'delete_content' => ['sometimes', 'boolean'],
            'code' => ['nullable', 'string', 'max:64'],
        ])->validate();

        $this->checkSecondFactor($request, $user);

        try {
            app(DeletesAccounts::class)->deleteAfterSignIn($user->fresh() ?? $user, $request->only(['delete_content']));
        } catch (BusinessException) {
            // The proof expired or was used in the meantime
            throw ValidationException::withMessages(['sign_in' => AccountDeletion::webSignInNeededMessage()]);
        }
    }

    /**
     * The code of two-factor authentication, when it is on, for every account: with a password or with the proof of a
     * new sign-in. A few tries a minute, as on the API.
     *
     * @throws ValidationException
     */
    private function checkSecondFactor(Request $request, User $user): void
    {
        if ($user->hasEnabledTwoFactorAuthentication()) {
            $key = 'account-deletion-code:'.$user->cuid;
            if (RateLimiter::tooManyAttempts($key, Constants::ACCOUNT_DELETIONS_PER_MINUTE)) {
                throw ValidationException::withMessages(['code' => __('Too many tries. Please wait a minute, then try again.')]);
            }
            $code = $request->input('code');
            if (! is_string($code) || $code === '') {
                throw ValidationException::withMessages(['code' => __('Enter the code of your authenticator app or a recovery code.')]);
            }
            if (! SecondFactor::passesEither($user, $code)) {
                RateLimiter::hit($key, 60);
                Log::warning('Account deletion refused', ['reason' => 'wrong_code', 'user' => $user->cuid]);
                throw ValidationException::withMessages(['code' => __('The provided two factor authentication code was invalid.')]);
            }
        }
    }
}
