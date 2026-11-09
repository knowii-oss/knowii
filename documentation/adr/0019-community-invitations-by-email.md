# 0019. Community invitations by email, for one address, with a signed link

- Status: Accepted
- Date: 2026-11-09
- Issue: #1242

## Context

Owners, admins and moderators with the `manage_members` right (ADR 0017) add people to a community. The person may not have an account yet. The link that lets someone join a community gives access to its content, so a leaked or forwarded link is a risk.

## Decision

1. An invitation is an email address and a role (`community_invitations`). The role follows the role hierarchy: lower than the role of the person who invites.
2. The invitation email has a temporary signed link (`URL::temporarySignedRoute`, `community-invitations.accept`), valid for 7 days (`CommunityInvitation::VALIDITY_DAYS`). The link works only when the signed-in account has the invited email address (case-insensitive). The routes of the app require a verified email address, so the person proves that they own it.
3. A guest who follows the link signs in or creates an account first, then comes back to the link.
4. Accepting adds the member with the role of the invitation and deletes the invitation.
5. Inviting the same address again replaces its invitation and sends a new email. An address that is already a member is refused.
6. The email is queued. Invitations are rate limited (10 per minute per user).

## Consequences

1. A forwarded email does not let another account join.
2. An expired invitation stays in the list (marked expired) until someone sends it again or cancels it.
3. Changing the email address of an account after an invitation was sent breaks that invitation: send it again.

## Alternatives considered

1. A link that works for any signed-in account (Jetstream teams): a forwarded or leaked email gives access to the community.
2. Add existing users directly by username: leaks which people have an account, and joins someone without their consent.
3. Invitation codes shared by hand: no control over who uses them. Public join links can come with public communities later.
