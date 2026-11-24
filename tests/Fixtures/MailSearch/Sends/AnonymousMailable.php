<?php

namespace Tests\Fixtures\MailSearch\Sends;

use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Mail;

/**
 * A mailable of an anonymous class: the search of the mails sent without a class finds it (ADR 0069)
 */
class AnonymousMailable
{
    public function send(): void
    {
        Mail::to('reader@example.com')->send(new class extends Mailable {});
    }
}
