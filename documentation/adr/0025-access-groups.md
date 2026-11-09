# 0025. Access groups open spaces to some members, managed by hand or by a sync through the API

- Status: Accepted
- Date: 2026-11-09
- Issue: #1241

## Context

The Knowii Community needs paid spaces: some spaces must open only to some members, for example the members with a paid plan or the buyers of a course. Circle has the same concept. Until now, a space was either `public` or `members` (every member of the community).

The members of a paid group come from purchases. The Knowii account (ADR 0010) will know them, but its sign-in does not exist yet. A community owner also needs groups without any purchase (a team, beta testers).

## Decision

1. An access group is a named group of members inside a community (`access_groups`, unique name per community), with its members (`access_group_members`) and the spaces it opens (`access_group_space`). All links are explicit foreign keys (ADR 0014).
2. Spaces get a third visibility, `groups`: the members of at least one of the access groups of the space can read it. A `groups` space needs at least one group of its own community.
3. The owner and the admins see every space, so that they can manage it. Moderators and members need a group.
4. A member who cannot read a space cannot post in it, whatever its posting policy.
5. A restricted space is hidden from the members outside its groups, in the navigation and everywhere else: every read goes through `SpacePolicy::view` (navigation, pages, API, channels).
6. The owner and the admins create, rename and delete groups (groups decide paid access). The owner, the admins and the moderators with the "manage members" right add and remove group members. Those who manage spaces see the list of groups, to give a space its groups.
7. Only members of the community join its groups. A member removed from the community leaves its groups. Deleting a group closes its spaces to its members.
8. The API lets another system keep a group in step (Sébastien, 2026-11-09):
   - `GET`, `POST /communities/{community}/access-groups`, `PATCH`, `DELETE /access-groups/{group}`.
   - `GET /access-groups/{group}/members` (paginated).
   - `POST /access-groups/{group}/members` with exactly one of `member` (user cuid), `username` or `email`. Adding a member twice changes nothing, so a sync can send its list again. Someone who is not a member of the community gives a validation issue that does not say whether the person has an account.
   - `DELETE /access-groups/{group}/members/{member}`.
   - New token abilities `groups:read` and `groups:write` (ADR 0022): a sync token gets `groups:write` only, not `communities:write`.
9. Changes broadcast `community.access_groups.changed` (settings pages) and `community.space_tree.changed` (navigation) on the community channel.

## Consequences

1. Paid access (#869) becomes: a purchase adds the buyer to a group, a refund or the end of a subscription removes them. The Knowii account can do it through the API with a `groups:write` token, or later directly (entitlement sync, ADR 0010).
2. A purchase by someone who is not yet a member cannot be recorded yet: the sync must first get them into the community (invitation, #1242). Pending grants by email can come with the entitlement sync.
3. Every new way to read space content must go through `SpacePolicy::view` (search, notifications, feeds, exports), else it leaks restricted content.
4. Mentions still suggest every member of the community. A member mentioned in a restricted space cannot open it.

## Alternatives considered

1. Groups as roles (a "paid member" role): roles are a hierarchy of rights (ADR 0017), not a set of memberships; a member can be in several groups.
2. A list of members per space: no reuse between spaces, and a purchase would have to update every space.
3. Show restricted spaces as locked (name and lock, call to action): useful to sell access, but it shows the names of the restricted spaces. Logged as an option for later (#869, #1241).
