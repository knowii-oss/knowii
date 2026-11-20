<?php

namespace App\Contracts\Auth;

interface SendsMagicLinks
{
    /**
     * Email a sign-in link when an account has this address. Says nothing about whether it exists.
     *
     * @param  array<mixed>  $input
     * @param  string|null  $landing  The page of a community that the person was reading (Landing::fromSession()), kept
     *                                beside the link for the life of the link (#1612, ADR 0057). It counts when the link is
     *                                used, and only for a member of that community. Null: none
     */
    public function send(array $input, ?string $landing = null): void;
}
