<?php

namespace Tests\Fixtures\MailSearch\Elsewhere;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * A notification with a mail, in a folder of its own: the search of the mail classes finds it (ADR 0069)
 */
class NestedMailNotification extends Notification
{
    public function toMail(object $notifiable): MailMessage
    {
        return new MailMessage;
    }
}
