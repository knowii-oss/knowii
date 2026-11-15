<?php

namespace App\Http\Controllers\Inertia;

use App\Contracts\Users\DeletesAccounts;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Laravel\Fortify\Actions\ConfirmPassword;
use Symfony\Component\HttpFoundation\Response;

/**
 * Deletes the account of the member (#1261, ADR 0024), with the option to delete their posts, comments and messages
 * too (#1344, ADR 0043). It takes the place of the Jetstream controller on the same route (`current-user.destroy`),
 * which cannot pass an option to the action. The action validates the option and does the work.
 */
class DeleteAccountController extends Controller
{
    final public function destroy(Request $request, StatefulGuard $guard): Response
    {
        $user = $request->user();
        $password = $request->input('password');

        if ($user === null || ! is_string($password) || ! app(ConfirmPassword::class)($guard, $user, $password)) {
            throw ValidationException::withMessages([
                'password' => __('The password is incorrect.'),
            ]);
        }

        app(DeletesAccounts::class)->delete($user->fresh() ?? $user, $request->only(['delete_content']));

        $guard->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Inertia::location(url('/'));
    }
}
