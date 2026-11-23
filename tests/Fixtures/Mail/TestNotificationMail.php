<?php

namespace Tests\Fixtures\Mail;

use App\Enums\KnowiiNotificationEmailGroup;
use App\Mail\NotificationMail;
use App\Models\User;

/**
 * A notification mail for the tests of the email delivery (#1477, #1478): no subclass of NotificationMail ships before
 * slice 4c. Without an account, it makes one.
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
        return $this->html('<p>'.e($this->text).'</p>')->subject($this->text);
    }
}
