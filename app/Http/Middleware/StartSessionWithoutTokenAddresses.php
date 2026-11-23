<?php

namespace App\Http\Middleware;

use App\Support\SecretAddresses;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\StartSession;

/**
 * The session middleware of the application, which keeps no address that holds a link token or a signature as the
 * previous address of the session (`_previous.url`): the rule is App\Support\SecretAddresses (ADR 0064). So the address
 * of an invite link (#1391, ADR 0062), a sign-in link, a password reset, a signed link of a mail, the callback of the
 * OpenID Connect provider and an address near one of them never stay in the session. AppServiceProvider binds it in place
 * of the middleware of the framework, with the same arguments, so that the web group and the first-party requests of the
 * API (Sanctum) use it. Everything else is the middleware of the framework.
 */
class StartSessionWithoutTokenAddresses extends StartSession
{
    protected function storeCurrentUrl(Request $request, $session): void
    {
        if (SecretAddresses::holdsASecret($request)) {
            return;
        }

        parent::storeCurrentUrl($request, $session);
    }
}
