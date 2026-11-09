<?php

namespace App\Contracts\Newsletter;

interface SubscribesToNewsletter
{
    /**
     * Add an email address to the newsletter.
     *
     * @param  array<mixed>  $input
     */
    public function subscribe(array $input, ?string $ipAddress): void;
}
