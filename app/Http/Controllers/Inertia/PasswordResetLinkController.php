<?php

namespace App\Http\Controllers\Inertia;

use App\Http\Responses\PasswordResetLinkRequestResponse;
use App\Jobs\SendPasswordResetLink;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Support\Str;
use Laravel\Fortify\Fortify;
use Laravel\Fortify\Http\Controllers\PasswordResetLinkController as FortifyPasswordResetLinkController;
use Laravel\Fortify\Http\Requests\SendPasswordResetLinkRequest;

/**
 * The password reset form gives the same answer for every address (documentation/architecture/backend.md). The request
 * checks the format of the address, queues one job for it (SendPasswordResetLink: the broker runs there) and answers
 * at once: it does the same work for every address. The limiter `password-reset` has the numbers of the form of the
 * sign-in link.
 */
class PasswordResetLinkController extends FortifyPasswordResetLinkController
{
    public function __construct()
    {
        $this->middleware('throttle:password-reset')->only('store');
    }

    public function store(SendPasswordResetLinkRequest $request): Responsable
    {
        $email = $request->string(Fortify::email())->toString();
        if (config()->boolean('fortify.lowercase_usernames')) {
            $email = Str::lower($email);
        }

        SendPasswordResetLink::dispatch($email);

        return new PasswordResetLinkRequestResponse;
    }
}
