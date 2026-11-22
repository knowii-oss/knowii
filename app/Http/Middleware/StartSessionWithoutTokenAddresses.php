<?php

namespace App\Http\Middleware;

use App\ApiRoutes;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Str;

/**
 * The session middleware of the application, which keeps no address that holds a token as the previous address of the
 * session (`_previous.url`): the address of an invite link (#1391, ADR 0062) holds its token, and the session keeps only
 * its hash. AppServiceProvider binds it in place of the middleware of the framework, with the same arguments, so that the
 * web group and the first-party requests of the API (Sanctum) use it. Everything else is the middleware of the framework.
 */
class StartSessionWithoutTokenAddresses extends StartSession
{
    /**
     * The paths whose address can hold the token of an invite link: every address under the address of a link (also an
     * address with a character too many, which the fallback route answers with a 404), and the API read of a link. The
     * comparison ignores the case: an address in capital letters also reaches the fallback route
     *
     * @var list<string>
     */
    public const array PATHS_WITH_A_TOKEN = ['invite/*', 'api/'.ApiRoutes::VERSION.'/invite-links/*'];

    protected function storeCurrentUrl(Request $request, $session): void
    {
        if (Str::is(self::PATHS_WITH_A_TOKEN, $request->decodedPath(), ignoreCase: true)) {
            return;
        }

        parent::storeCurrentUrl($request, $session);
    }
}
