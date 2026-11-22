<?php

namespace App\Enums;

/**
 * What the person who opens an invite link can do (#1391, ADR 0062). A link that does not work has no state for the API
 * (it answers 404); the page shows `unusable`.
 * WARNING: aligned with INVITE_LINK_JOIN_STATES (invite-link.schema.ts)
 */
enum KnowiiInviteLinkJoinState: string
{
    // The person can join
    case Join = 'join';
    // The person is a member of the community already (the owner too)
    case Member = 'member';
    // A manager removed the person: a link does not bring them back
    case Blocked = 'blocked';
    // The page only: the link does not work (unknown, off, ended, full, or of a personal community)
    case Unusable = 'unusable';
}
