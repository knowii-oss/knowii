<?php

namespace App\Actions\Waitlist;

use App\Actions\EmailOctopus\AddsEmailOctopusContact;
use App\Contracts\Waitlist\JoinsWaitlist;

/**
 * Adds an email address to the waitlist (an EmailOctopus list). The protection against bots is in the parent class.
 */
class JoinWaitlist extends AddsEmailOctopusContact implements JoinsWaitlist
{
    /**
     * @param  array<mixed>  $input
     */
    final public function join(array $input, ?string $ipAddress): void
    {
        $this->addContact($input, $ipAddress);
    }

    protected function listName(): string
    {
        return 'waitlist';
    }

    protected function listIdConfigKey(): string
    {
        return 'services.emailoctopus.waitlist_list_id';
    }

    protected function contactStatusConfigKey(): string
    {
        return 'services.emailoctopus.waitlist_contact_status';
    }
}
