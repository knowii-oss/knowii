<?php

namespace App\Http\Controllers\Inertia;

use App\Actions\Fortify\CreateNewUser;
use App\Http\Responses\SignUpRequestResponse;
use App\Jobs\SendSignUpMail;
use App\Support\Landing;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\CreatesNewUsers;
use Laravel\Fortify\Contracts\RegisterResponse;
use Laravel\Fortify\Http\Controllers\RegisteredUserController as FortifyRegisteredUserController;

/**
 * The sign-up form gives the same answer for every address (ADR 0065). The request checks the fields that do not depend on
 * the address (CreateNewUser::validateForm()), queues one job (SendSignUpMail: the mail that finishes the sign-up, or the
 * mail of an address that has an account) and answers at once: it does the same work for every address, never reads an
 * account, makes no account and signs nobody in. The account is made when the person opens the link of the mail
 * (SignUpLinkController). The limiter `sign-up` has the numbers of the password reset form.
 */
class RegisteredUserController extends FortifyRegisteredUserController
{
    public function __construct(StatefulGuard $guard)
    {
        parent::__construct($guard);
        $this->middleware('throttle:sign-up')->only('store');
    }

    public function store(Request $request, CreatesNewUsers $creator): RegisterResponse
    {
        $input = $request->all();
        // The address in lower case, as Fortify does
        if (config()->boolean('fortify.lowercase_usernames') && is_string($input['email'] ?? null)) {
            $input['email'] = Str::lower($input['email']);
        }

        $fields = ($creator instanceof CreateNewUser ? $creator : app(CreateNewUser::class))->validateForm($input);

        // The page of a community that the session holds travels with the pending sign-up (ADR 0057)
        SendSignUpMail::dispatch($fields['email'], $fields['name'], Landing::fromSession());

        return new SignUpRequestResponse;
    }
}
