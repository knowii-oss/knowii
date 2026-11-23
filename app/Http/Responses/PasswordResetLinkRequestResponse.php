<?php

namespace App\Http\Responses;

use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The answer of the password reset form (documentation/architecture/backend.md): the same answer, the same status and
 * the same words for every address, as the form of the sign-in link. The request does not know if the address has an
 * account: the broker runs later, in the queued job `App\Jobs\SendPasswordResetLink`. Only a malformed address gets an
 * error: the validation of the form comes first.
 */
class PasswordResetLinkRequestResponse implements Responsable
{
    /**
     * @param  Request  $request
     */
    final public function toResponse($request): Response
    {
        if ($request->wantsJson()) {
            return new JsonResponse(['message' => self::message()], 200);
        }

        return back()->with('status', self::message());
    }

    public static function message(): string
    {
        $minutes = config('auth.passwords.'.config()->string('fortify.passwords', 'users').'.expire', 60);

        return sprintf(
            'If an account has this address, we sent it a password reset link. The link works for %d minutes.',
            is_int($minutes) ? $minutes : 60,
        );
    }
}
