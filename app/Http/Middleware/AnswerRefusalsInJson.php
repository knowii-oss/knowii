<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A route of the API that a browser opens with a plain link (the calendar file of an event, #1515) answers a refusal as
 * the JSON error of the API, never as a page or as a redirect to the sign-in page: the browser then shows a download that
 * failed, and never saves a page of the application as the file. The request is marked as one that wants JSON before
 * the end of an outdated session (EndOutdatedSessions, ADR 0059) and the sign-in check run (see the priority in
 * bootstrap/app.php). The answer of a success does not change.
 */
class AnswerRefusalsInJson
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $request->headers->set('Accept', 'application/json');

        return $next($request);
    }
}
