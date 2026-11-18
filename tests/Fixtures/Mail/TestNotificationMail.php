<?php

namespace Tests\Fixtures\Mail;

use App\Mail\NotificationMail;

/**
 * A notification mail for the tests of the email delivery (#1477): no subclass of NotificationMail ships before slice 4c.
 */
class TestNotificationMail extends NotificationMail
{
    public function __construct(public string $text = 'A test notification') {}

    /**
     * @return $this
     */
    public function build(): static
    {
        return $this->html('<p>'.e($this->text).'</p>')->subject($this->text);
    }
}
