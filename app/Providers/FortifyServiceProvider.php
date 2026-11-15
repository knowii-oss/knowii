<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Actions\Fortify\UpdateUserPassword;
use App\Actions\Fortify\UpdateUserProfileInformation;
use App\Support\OpenIdConnect;
use App\Support\Turnstile;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Fortify::createUsersUsing(CreateNewUser::class);

        // The sign-up page shows the Turnstile widget when the instance has its keys (#737). Replaces the view of
        // Jetstream, which boots before this provider
        Fortify::registerView(fn () => Inertia::render('Auth/Register', ['turnstileSiteKey' => Turnstile::siteKey()]));
        // The sign-in page: `?provider=1` goes straight to the OpenID Connect provider when the instance has one (#1385).
        // Replaces the view of Jetstream. The page gets the provider (button, error) from the shared props
        Fortify::loginView(function (Request $request) {
            if (OpenIdConnect::enabled() && $request->boolean('provider')) {
                return redirect()->route('oidc.redirect');
            }

            return Inertia::render('Auth/Login', [
                'canResetPassword' => Route::has('password.request'),
                'status' => session('status'),
            ]);
        });
        Fortify::updateUserProfileInformationUsing(UpdateUserProfileInformation::class);
        Fortify::updateUserPasswordsUsing(UpdateUserPassword::class);
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);

        RateLimiter::for('login', function (Request $request) {
            $username = $request->input(Fortify::username());

            if (! is_string($username)) {
                $username = '';
            }

            $username = Str::lower($username);

            $throttleKey = Str::transliterate($username.'|'.$request->ip());

            return Limit::perMinute(5)->by($throttleKey);
        });

        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by($request->session()->get('login.id'));
        });
    }
}
