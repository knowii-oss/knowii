<?php

namespace App\Http\Responses;

use App\Support\BrowserHistory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Support\Header;
use Laravel\Fortify\Http\Responses\LogoutResponse as FortifyLogoutResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * The answer of the sign-out: the one of Fortify, and the next page tells the browser to forget the pages of the member
 * that its history keeps (App\Support\BrowserHistory). The controller of Fortify invalidated the session before this
 * answer, so the mark is in the new session.
 *
 * A sign-out from a page of the web application (an Inertia request) loads the next page in full (ADR 0075): the
 * page starts again, with no data of the member in its memory and with a new live connection, so the next account in the
 * same tab gets its own connection. Another request keeps the redirect of Fortify.
 */
class LogoutResponse extends FortifyLogoutResponse
{
    /**
     * @param  Request  $request
     */
    public function toResponse($request): Response
    {
        BrowserHistory::clear($request);

        $response = parent::toResponse($request);
        if ($response instanceof RedirectResponse && $request->hasHeader(Header::INERTIA)) {
            return Inertia::location($response->getTargetUrl());
        }

        return $response;
    }
}
