<?php

namespace App\Http\Responses;

use App\Models\User;
use App\Support\Landing;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Fortify\Contracts\TwoFactorLoginResponse as TwoFactorLoginResponseContract;
use Laravel\Fortify\Fortify;
use Symfony\Component\HttpFoundation\Response;

/**
 * The answer of the challenge of the second factor, after a code or a recovery code was accepted (#1612, ADR 0057). It is
 * the answer of Fortify, with one step before the redirect: the landing that a sign-in link left in the session for this
 * challenge is read and removed, and it becomes the intended address of the session for the account that passed the
 * challenge (Landing::rememberForMember). Nothing is decided for an account before its code is accepted. A sign-in with a
 * password or with a provider leaves no landing here, and gets the answer of before.
 */
class TwoFactorLoginResponse implements TwoFactorLoginResponseContract
{
    /**
     * @param  Request  $request
     */
    final public function toResponse($request): Response
    {
        // Read and removed in every case: nothing stays in the session after the challenge
        $landing = Landing::pullHeld($request);

        if ($request->wantsJson()) {
            return new JsonResponse('', 204);
        }

        // The guard that the challenge of Fortify signed in
        $user = app(StatefulGuard::class)->user();
        if ($user instanceof User) {
            Landing::rememberForMember($request, $user, $landing);
        }

        return redirect()->intended(Fortify::redirects('login'));
    }
}
