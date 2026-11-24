<?php

namespace Tests\Fixtures\MailSearch\Sends;

use Illuminate\Mail\Message;
use Illuminate\Support\Facades\Mail;

/**
 * A mail of HTML with no class, through a named mailer: the search of the mails sent without a class finds it (ADR 0069)
 */
class HtmlThroughAMailer
{
    public function send(): void
    {
        Mail::mailer('array')->html('<p>Hello</p>', static fn (Message $message) => $message->to('reader@example.com'));
    }
}
