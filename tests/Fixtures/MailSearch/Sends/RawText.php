<?php

namespace Tests\Fixtures\MailSearch\Sends;

use Illuminate\Mail\Message;
use Illuminate\Support\Facades\Mail;

/**
 * A mail of a text with no class: the search of the mails sent without a class finds it (ADR 0069)
 */
class RawText
{
    public function send(): void
    {
        Mail::raw('Hello', static fn (Message $message) => $message->to('reader@example.com'));
    }
}
