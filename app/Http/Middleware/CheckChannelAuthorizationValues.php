<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

/**
 * The two values of the authorization of a live channel (`POST /broadcasting/auth`, routes/web.php) are checked before
 * the broadcaster reads them: the id of the connection and the name of the channel are two texts in the form that the live
 * server gives them. A request with another value gets the validation error of ADR 0011, never a server error. It is the
 * last middleware of the route, after the sign-in checks: a visitor gets the refusal of a visitor. The rules of the
 * channels (routes/channels.php) then decide, as before.
 */
class CheckChannelAuthorizationValues
{
    /**
     * @param  Closure(Request): Response  $next
     *
     * @throws ValidationException
     */
    public function handle(Request $request, Closure $next): Response
    {
        $request->validate([
            'socket_id' => ['required', 'string', 'regex:/\A\d+\.\d+\z/'],
            'channel_name' => ['required', 'string', 'max:164', 'regex:/\A[-a-zA-Z0-9_=@,.;]+\z/'],
        ]);

        return $next($request);
    }
}
