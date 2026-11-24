<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Fortify\Contracts\RegisterResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * The answer of the sign-up form (ADR 0065): the same answer, the same status and the same words for every address. The
 * request does not know if the address has an account: the queued job `App\Jobs\SendSignUpMail` sends the mail that
 * finishes the sign-up, or the mail of an address that has an account. Only a field that fails its check gets an error: the
 * validation of the form comes first.
 */
class SignUpRequestResponse implements RegisterResponse
{
    public const string MESSAGE = 'Check your inbox: we sent you an email with the next step. If nothing comes in a few minutes, check your spam folder and the address, then try again.';

    /**
     * @param  Request  $request
     */
    final public function toResponse($request): Response
    {
        if ($request->wantsJson()) {
            return new JsonResponse(['message' => self::MESSAGE], 200);
        }

        return redirect()->route('register')->with('status', self::MESSAGE);
    }
}
