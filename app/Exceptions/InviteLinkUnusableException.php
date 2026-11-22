<?php

namespace App\Exceptions;

use App\Support\InviteLinkTexts;

/**
 * An invite link that does not work (#1391, ADR 0062): unknown, off, ended, full, deleted, or of a personal community.
 * Every case gives the same answer (a 404 with InviteLinkTexts::UNUSABLE), so a person learns nothing about a link.
 */
final class InviteLinkUnusableException extends KnowiiException
{
    public function __construct()
    {
        parent::__construct(InviteLinkTexts::UNUSABLE);
    }
}
