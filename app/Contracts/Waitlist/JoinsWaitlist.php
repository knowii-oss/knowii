<?php

namespace App\Contracts\Waitlist;

interface JoinsWaitlist
{
    /**
     * Add an email address to the waitlist.
     *
     * @param  array<mixed>  $input
     */
    public function join(array $input, ?string $ipAddress): void;
}
