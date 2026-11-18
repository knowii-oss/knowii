<?php

namespace Tests\Fixtures\Mail;

use Resend\Exceptions\ErrorException;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;

/**
 * A mail transport that refuses every mail like the Resend transport of Laravel does: a TransportException whose previous
 * exception is the error of the provider (#1477). Without an error type, it is a plain transport error.
 */
class RefusingTransport extends AbstractTransport
{
    public function __construct(private readonly ?string $errorType)
    {
        parent::__construct();
    }

    protected function doSend(SentMessage $message): void
    {
        $previous = $this->errorType === null
            ? null
            : new ErrorException(['message' => 'The provider refused the mail.', 'name' => $this->errorType, 'statusCode' => 429]);

        throw new TransportException('Request to Resend API failed. Reason: The provider refused the mail..', 429, $previous);
    }

    public function __toString(): string
    {
        return 'refusing';
    }
}
