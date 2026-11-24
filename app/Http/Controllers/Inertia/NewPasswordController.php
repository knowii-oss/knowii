<?php

namespace App\Http\Controllers\Inertia;

use App\Support\PasswordResetBroker;
use Illuminate\Contracts\Auth\PasswordBroker;
use Illuminate\Contracts\Auth\StatefulGuard;
use Laravel\Fortify\Http\Controllers\NewPasswordController as FortifyNewPasswordController;

/**
 * The page of a password reset (`POST /reset-password`): the step of Fortify, behind the limiter `password-update` (per IP
 * address, the numbers of the use of a sign-up link), with a broker whose check of a link does the same work for every
 * address (App\Support\PasswordResetBroker). Every link that does not work gets the same answer
 * (App\Http\Responses\FailedPasswordResetResponse).
 */
class NewPasswordController extends FortifyNewPasswordController
{
    public function __construct(StatefulGuard $guard)
    {
        parent::__construct($guard);
        $this->middleware('throttle:password-update')->only('store');
    }

    protected function broker(): PasswordBroker
    {
        return PasswordResetBroker::make();
    }
}
