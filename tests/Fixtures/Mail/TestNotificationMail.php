<?php

namespace Tests\Fixtures\Mail;

use App\Enums\KnowiiNotificationEmailGroup;
use App\Mail\NotificationMail;
use App\Models\User;
use Symfony\Component\Mime\Email;

/**
 * A notification mail for the tests of the email delivery (#1477, #1478): no subclass of NotificationMail ships before
 * slice 4c. Without an account, it makes one. Its HTML and its plain text end with the footer of every notification mail.
 */
class TestNotificationMail extends NotificationMail
{
    public User $user;

    public function __construct(
        public string $text = 'A test notification',
        ?User $user = null,
        public KnowiiNotificationEmailGroup $group = KnowiiNotificationEmailGroup::RepliesAndMentions,
    ) {
        $this->user = $user ?? User::factory()->create();
    }

    public function account(): User
    {
        return $this->user;
    }

    public function emailGroup(): KnowiiNotificationEmailGroup
    {
        return $this->group;
    }

    /**
     * @return $this
     */
    public function build(): static
    {
        $footer = $this->footerData();
        $html = '<p>'.e($this->text).'</p>'.view('emails.partials.notification-footer', $footer)->render();
        $plain = $this->text."\n\n".view('emails.partials.notification-footer', $footer + ['plain' => true])->render();

        return $this->html($html)
            ->subject($this->text)
            ->withSymfonyMessage(static fn (Email $message) => $message->text($plain));
    }
}
