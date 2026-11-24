<?php

namespace Tests\Fixtures\MailSearch\Sends;

use Illuminate\Mail\Message;
use Illuminate\Support\Facades\Mail;

/**
 * A mail of a view and a closure: the search of the mails sent without a class finds it (ADR 0069)
 */
class ViewAndClosure
{
    public function send(): void
    {
        Mail::send('emails.magic-link', ['url' => 'https://knowii.test', 'minutes' => 5], static fn (Message $message) => $message->to('reader@example.com'));
    }
}
