<?php

namespace App\Http\Responses;

use App\Support\BrowserHistory;
use Illuminate\Http\Request;
use Laravel\Fortify\Http\Responses\LogoutResponse as FortifyLogoutResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * The answer of the sign-out: the one of Fortify, and the next page tells the browser to forget the pages of the member
 * that its history keeps (App\Support\BrowserHistory). The controller of Fortify invalidated the session before this
 * answer, so the mark is in the new session.
 */
class LogoutResponse extends FortifyLogoutResponse
{
    /**
     * @param  Request  $request
     */
    public function toResponse($request): Response
    {
        BrowserHistory::clear($request);

        return parent::toResponse($request);
    }
}
