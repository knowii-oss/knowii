<?php

namespace App\Providers;

use App\Actions\Fortify\ConfirmTwoFactorAuthentication;
use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\EnableTwoFactorAuthentication;
use App\Actions\Fortify\GenerateNewRecoveryCodes;
use App\Actions\Fortify\ResetUserPassword;
use App\Actions\Fortify\UpdateUserPassword;
use App\Actions\Fortify\UpdateUserProfileInformation;
use App\Http\Controllers\Inertia\PasswordResetLinkController;
use App\Http\Requests\TwoFactorLoginRequest;
use App\Http\Responses\TwoFactorLoginResponse;
use App\Http\Responses\VerifyEmailResponse;
use App\Support\OpenIdConnect;
use App\Support\Turnstile;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Laravel\Fortify\Actions\ConfirmTwoFactorAuthentication as FortifyConfirmTwoFactorAuthentication;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication as FortifyEnableTwoFactorAuthentication;
use Laravel\Fortify\Actions\GenerateNewRecoveryCodes as FortifyGenerateNewRecoveryCodes;
use Laravel\Fortify\Contracts\TwoFactorLoginResponse as TwoFactorLoginResponseContract;
use Laravel\Fortify\Contracts\VerifyEmailResponse as VerifyEmailResponseContract;
use Laravel\Fortify\Fortify;
use Laravel\Fortify\Http\Controllers\PasswordResetLinkController as FortifyPasswordResetLinkController;
use Laravel\Fortify\Http\Requests\TwoFactorLoginRequest as FortifyTwoFactorLoginRequest;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // The link of the address check lands on the page of a community that it carries (#1612, ADR 0057)
        $this->app->singleton(VerifyEmailResponseContract::class, VerifyEmailResponse::class);
        // After the code of the second factor, a sign-in link lands on the page that it carries (#1612, ADR 0057)
        $this->app->singleton(TwoFactorLoginResponseContract::class, TwoFactorLoginResponse::class);
        // The password reset form gives the same answer for every address, and the broker runs in a queued job
        // (documentation/architecture/backend.md)
        $this->app->bind(FortifyPasswordResetLinkController::class, PasswordResetLinkController::class);
        // A challenge of the second factor for an account that has none any more ends on the sign-in page (ADR 0059)
        $this->app->bind(FortifyTwoFactorLoginRequest::class, TwoFactorLoginRequest::class);
        // An account whose address was never verified cannot turn the second factor on (ADR 0059): "Enable", new recovery
        // codes and the confirmation are refused
        $this->app->bind(FortifyEnableTwoFactorAuthentication::class, EnableTwoFactorAuthentication::class);
        $this->app->bind(FortifyGenerateNewRecoveryCodes::class, GenerateNewRecoveryCodes::class);
        $this->app->bind(FortifyConfirmTwoFactorAuthentication::class, ConfirmTwoFactorAuthentication::class);
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
