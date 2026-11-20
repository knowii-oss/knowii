<?php

namespace App\Http\Responses;

use App\Models\User;
use App\Support\Landing;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Fortify\Contracts\VerifyEmailResponse as VerifyEmailResponseContract;
use Laravel\Fortify\Fortify;
use Symfony\Component\HttpFoundation\Response;

/**
 * The answer of the link of the address check (#1612, ADR 0057). It is the answer of Fortify, with one step before the
 * redirect: the landing of the link becomes the intended address of the session, when the session has none of its own
 * (Landing::remember). The route is signed, so the landing is the one that the application wrote in the mail, in the form
 * of Landing::linkValueOf(). A second click on the link gets the same answer.
 */
class VerifyEmailResponse implements VerifyEmailResponseContract
{
    /**
     * @param  Request  $request
     */
    final public function toResponse($request): Response
    {
        if ($request->wantsJson()) {
            return new JsonResponse('', 204);
        }

        $user = $request->user();
        $landing = $request->query(Landing::LINK_PARAMETER);
        if ($user instanceof User) {
            Landing::remember($request, $user, Landing::pathOfLinkValue(is_string($landing) ? $landing : null));
        }

        return redirect()->intended(Fortify::redirects('email-verification').'?verified=1');
    }
}
