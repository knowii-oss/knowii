<?php

namespace App\Contracts\Auth;

interface SendsMagicLinks
{
    /**
     * Email a sign-in link when an account has this address. Says nothing about whether it exists.
     *
     * @param  array<mixed>  $input
     */
    public function send(array $input): void;
}
