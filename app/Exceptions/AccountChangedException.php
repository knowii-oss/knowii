<?php

namespace App\Exceptions;

use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * A change of an account was not written, because the account changed elsewhere after the request read it (ADR 0066): the
 * row holds another version of the sessions than the model of the request. Thrown by App\Contracts\Auth\EndsOtherSessions
 * for a request without a session of the account. The reset of the password turns it into the error of its form; anywhere
 * else it is a 409. A request with a session of the account gets the end of that session instead
 * (App\Support\EndedSession).
 */
final class AccountChangedException extends ConflictHttpException
{
    public const string MESSAGE = 'Your account changed during this request. Nothing was saved. Please try again.';

    public function __construct()
    {
        parent::__construct(self::MESSAGE);
    }
}
