# 0024. Deleting an account removes the personal data, the content of the member stays anonymous

- Status: Accepted
- Date: 2026-11-09
- Issue: #1261

## Context

Deleting an account detached the profile (`user_id = null`) instead of deleting it, "so that associated resources are not lost". The profile kept the email address, the name, the bio, the location, the phone and every link, and a new sign-up with the same email took it back. The action also logged the whole user model (email, profile, communities) at INFO level.

Under the GDPR, deleting an account must delete the personal data, and logs must not keep it. But the posts, comments and resources of a member are also part of the threads and collections of other members: deleting them would leave holes in discussions.

## Decision

1. What the member wrote stays, without an author: posts, comments, community resources and the images they show. The pages show "Deleted member". The foreign keys already do this (`author_id`, `curator_id`, `uploads.user_id`: `nullOnDelete`).
2. Everything else goes, in one transaction: the profile, the owned communities (with their content), the memberships, the reactions, the mentions, the invitations sent to the address, the API tokens, the sessions and the password reset tokens. The avatar folder (`avatars/{cuid}`, ADR 0021) is deleted after the commit.
3. A profile always belongs to an account: `user_profiles.user_id` is required, with a foreign key `cascadeOnDelete`. A migration deletes the detached profiles left by the old behavior.
4. A new sign-up always gets a new profile. Nothing is taken back by email.
5. Logs of account and community actions keep the cuid only, never a whole model.

## Consequences

1. A deleted account cannot be restored. The delete form says so.
2. The text of the posts and comments that stay can still name the member (a signature, a mention written by hand). The member can edit or delete their content before they delete the account.
3. The privacy policy (#77) must describe this.
4. The files of a deleted community go after the commit: its uploads, and the images of the resources that no other community shares (#1265, `App\Support\ContentFiles`). Images that the member uploaded to communities of others stay with the content; `uploads:prune` (#1201) deletes the ones that nothing uses.

## Alternatives considered

1. Delete all the content of the member: complete, but it breaks the threads and collections of other members.
2. Anonymize the profile and keep it (no email, no name, no fields): it keeps an empty row for nothing, and the content already works without an author.
3. A grace period (soft delete for 30 days): useful against mistakes, but it keeps personal data longer and needs a purge job. It can come later.
