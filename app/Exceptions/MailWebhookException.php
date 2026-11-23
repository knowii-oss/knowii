<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * A call of the webhook of the mail provider was refused (#1478, ADR 0054): 401 for a missing or malformed header, a
 * signature or a time that is not valid. `reason` is a short code for the log: never an address, a header, the secret
 * or the body. The answer has an empty body.
 */
final class MailWebhookException extends RuntimeException
{
    public function __construct(public readonly string $reason, public readonly int $status)
    {
        parent::__construct($reason);
    }
}
