<?php

namespace App\Actions\Fortify;

use App\Support\DummyPassword;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Timebox;
use Laravel\Fortify\Actions\RedirectIfTwoFactorAuthenticatable as FortifyRedirectIfTwoFactorAuthenticatable;
use Laravel\Fortify\Fortify;

/**
 * The first step of every password sign-in of the web (Fortify runs it because the second factor is on). It is the step of
 * Fortify, with one more check: a sign-in takes the same time for an address with and without an account
 * (documentation/architecture/backend.md). When no account has the address, or the account has no password (a provider
 * made it), the framework compares no hash; the step then checks the password against App\Support\DummyPassword, so every
 * failed sign-in costs one hash check at the cost of the application. Everything else is the step of Fortify: its timebox
 * of 200 ms, the rehash on login, the event `Failed`, the limiter `login` and the error "These credentials do not match our
 * records.".
 */
class RedirectIfTwoFactorAuthenticatable extends FortifyRedirectIfTwoFactorAuthenticatable
{
    /**
     * @param  Request  $request
     */
    protected function validateCredentials($request)
    {
        if (Fortify::$authenticateUsingCallback) {
            return parent::validateCredentials($request);
        }

        $provider = $this->guard->getProvider();

        return (new Timebox)->call(function () use ($request, $provider) {
            return tap($provider->retrieveByCredentials($request->only(Fortify::username(), 'password')), function ($user) use ($provider, $request) {
                $password = $request->input('password');
                if (! $user instanceof Authenticatable || ! $provider->validateCredentials($user, ['password' => $password])) {
                    // The framework compared no hash: no account, or an account without a password
                    if (! $user instanceof Authenticatable || blank($user->getAuthPassword())) {
                        DummyPassword::check(is_string($password) ? $password : '');
                    }

                    $this->fireFailedEvent($request, $user);

                    $this->throwFailedAuthenticationException($request);
                }

                if ($user instanceof Authenticatable && config('hashing.rehash_on_login', true)) {
                    $provider->rehashPasswordIfRequired($user, ['password' => $password]);
                }
            });
        }, 200000);
    }
}
