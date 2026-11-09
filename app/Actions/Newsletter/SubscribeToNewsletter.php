<?php

namespace App\Actions\Newsletter;

use App\Actions\EmailOctopus\AddsEmailOctopusContact;
use App\Contracts\Newsletter\SubscribesToNewsletter;

/**
 * Adds an email address to the newsletter (an EmailOctopus list, #938). The form in the footer exists only when the
 * list is configured (see Newsletter::isEnabled()). The protection against bots is in the parent class.
 */
class SubscribeToNewsletter extends AddsEmailOctopusContact implements SubscribesToNewsletter
{
    /**
     * @param  array<mixed>  $input
     */
    final public function subscribe(array $input, ?string $ipAddress): void
    {
        $this->addContact($input, $ipAddress);
    }

    protected function listName(): string
    {
        return 'newsletter';
    }

    protected function listIdConfigKey(): string
    {
        return 'services.emailoctopus.newsletter_list_id';
    }

    protected function contactStatusConfigKey(): string
    {
        return 'services.emailoctopus.newsletter_contact_status';
    }
}
