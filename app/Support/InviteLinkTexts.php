<?php

namespace App\Support;

use App\Constants;

/**
 * The texts of the server for the invite links (#1391, ADR 0062), in one place. The screens show the texts of the
 * refusals as the API gives them; the client mirrors UNUSABLE for the page of a link (invite-link-words.ts).
 */
final class InviteLinkTexts
{
    public const string TOO_MANY = 'A community can have at most '.Constants::MAX_INVITE_LINKS_PER_COMMUNITY.' invite links';

    public const string PERSONAL_COMMUNITY = 'A personal community has no invite link';

    public const string FED_BY_RULE = 'A link cannot give a group that a rule on an entitlement feeds';

    public const string END_IN_PAST = 'The end must be in the future';

    // The texts of JoinCommunity: a join through a link refuses in the same words
    public const string VERIFY_EMAIL = 'Verify your email address before you join a community';

    public const string ALREADY_MEMBER = 'You are already a member of this community';

    public const string BLOCKED = 'You cannot join this community by yourself. Ask its managers for an invitation.';

    // The join of the page when this browser opened another invite link after the page loaded (another tab): the page
    // now shows the newer link
    public const string ANOTHER_LINK = 'This browser opened another invite link after this page loaded. This page now shows that link. To join through the first link, open it again.';

    // One answer for every link that does not work: unknown, off, ended, full, deleted, or of a personal community.
    // WARNING: aligned with INVITE_LINK_WORDS.unusableMessage (invite-link-words.ts)
    public const string UNUSABLE = 'This invite link does not work. Ask the person who gave it to you for a new one.';
}
