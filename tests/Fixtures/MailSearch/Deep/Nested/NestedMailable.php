<?php

namespace Tests\Fixtures\MailSearch\Deep\Nested;

use Illuminate\Mail\Mailable;

/**
 * A mail class two folders down: the search of the mail classes finds it (ADR 0069)
 */
class NestedMailable extends Mailable {}
