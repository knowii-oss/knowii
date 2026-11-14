# 0035. The owner transfers a community to an admin and becomes an admin

- Status: Accepted
- Date: 2026-11-14
- Issue: #1339
- Pull request: #1357

## Context

A community has one owner (`communities.owner_id`). The owner is not a row of the member table, and the role hierarchy gives them rank 4 ([ADR 0017](0017-roles-with-configurable-moderator-rights.md)). Until now the owner could not give the community to someone else, and they cannot leave it (#1338): a person who stops running a community could only delete it.

The change is sensitive. The new owner can delete the community and remove the former owner, and the former owner cannot undo the transfer by themselves.

## Decision

1. `POST /api/v1/communities/{community}/ownership` (ability `communities:write`) with `member` (the user cuid of the new owner) and `password` (of the current owner). `App\Actions\Communities\TransferCommunityOwnership` does it behind `TransfersCommunityOwnership` ([ADR 0006](0006-actions-behind-contracts.md)).
2. Only the owner transfers (`CommunityPolicy::transferOwnership`). A personal community never changes its owner: it is the private space of one member.
3. Only an admin can receive the community. The new owner then already manages the community, and the owner chose them for it. Any other member gets a 422; a user who is not a member gets a 404.
4. The owner confirms with their password (a 400 on `password` when it is wrong). The route has its own rate limit (`ownership-transfers`, 5 per minute per user), so it cannot be used to guess a password. The deletion of a community asks for its name only, but a deletion gives nobody control over the members and their content.
5. One transaction: the new owner leaves the member table, `owner_id` changes, and the former owner becomes an admin. Access groups and course progress do not change. The name and the address of the community do not change.
6. The new owner gets an email (`App\Mail\CommunityOwnershipTransferred`). The members get `community.members.changed`, like any role change.
7. The transfer applies at once. The new owner does not accept it first: they are an admin, they can give the community back the same way, and they can leave after that.

## Consequences

1. The owner can leave a community in two steps: transfer the ownership, then leave (#1338).
2. A community always has exactly one owner. Nothing in the code may set `owner_id` except this action and the creation of the community.
3. An owner who wants to give the community to a plain member makes them an admin first.
4. An account without a usable password (a future sign-in with an identity provider only, [ADR 0010](0010-own-accounts-optional-oidc.md)) cannot transfer a community. That work must add another confirmation, for example a sign-in link.
5. An API token with `communities:write` is not enough to transfer a community: the caller also needs the password of the owner.

## Alternatives considered

1. The new owner accepts the transfer first (a pending transfer with an email link): safer against a mistake, but it adds a state, a table and an expiry for a rare action between two people who already run the community together. To add if owners ask for it.
2. Any member can receive the community: one wrong choice in a long list gives the community to a stranger.
3. No password, the name of the community instead (like the deletion): a stolen session could give the community away for good.
4. The former owner leaves the community: they would lose their access with one click. They can leave after the transfer if they want to.
