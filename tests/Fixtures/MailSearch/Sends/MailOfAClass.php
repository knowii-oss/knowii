<?php

namespace Tests\Fixtures\MailSearch\Sends;

use Illuminate\Support\Facades\Mail;
use Tests\Fixtures\MailSearch\Deep\Nested\NestedMailable;

/**
 * A mail of a class: the search of the mails sent without a class does not take it (ADR 0069)
 */
class MailOfAClass
{
    public function send(): void
    {
        Mail::to('reader@example.com')->send(new NestedMailable);
    }
}
