<?php

namespace App\Http\Responses;

use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\FailedPasswordResetResponse as FailedPasswordResetResponseContract;
use Symfony\Component\HttpFoundation\Response;

/**
 * The answer of the page of a password reset when the link does not work: the same words and the same status for every
 * case (no account has the address, a wrong token, a token that is too old or was used), as an error of `email`. The
 * request does not say which case it was.
 */
class FailedPasswordResetResponse implements FailedPasswordResetResponseContract
{
    public const string MESSAGE = 'This password reset link expired or was already used. Ask for a new one.';

    /**
     * @param  Request  $request
     *
     * @throws ValidationException For a request that expects JSON
     */
    final public function toResponse($request): Response
    {
        if ($request->wantsJson()) {
            throw ValidationException::withMessages(['email' => [self::MESSAGE]]);
        }

        return back()
            ->withInput($request->only('email'))
            ->withErrors(['email' => self::MESSAGE]);
    }
}
