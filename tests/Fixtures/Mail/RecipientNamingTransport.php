<?php

namespace Tests\Fixtures\Mail;

use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Address;

/**
 * A mail transport that refuses every mail with an error text that names each recipient, as a mail server does
 * ("550 5.1.1 <ada@example.com>: Recipient address rejected"). For the tests of what a failed mail leaves behind.
 */
class RecipientNamingTransport extends AbstractTransport
{
    protected function doSend(SentMessage $message): void
    {
        $recipients = implode('>, <', array_map(static fn (Address $address): string => $address->getAddress(), $message->getEnvelope()->getRecipients()));

        throw new TransportException("550 5.1.1 <{$recipients}>: Recipient address rejected");
    }

    public function __toString(): string
    {
        return 'recipient-naming';
    }
}
