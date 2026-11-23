# 0062. Invite links: a shared link to join a community, with set access groups and tags

- Status: Accepted
- Date: 2026-11-22
- Issue: #1391
- Pull request: #1717
- Extends: [0019](0019-community-invitations-by-email.md) (a second way in by invitation, for anyone who holds the link), [0044](0044-public-spaces-for-visitors.md) ("Who can join": a join through a link is a join by oneself), [0057](0057-the-landing-after-the-address-check-travels-in-the-link.md) (`Landing::KEPT_ROUTES`: the page of an invite link is kept like the link of an invitation)
- Uses: [0061](0061-invitations-carry-access-groups-and-tags-and-come-from-a-file.md) (`InvitationGrants`, `JoinGrants`), [0060](0060-member-tags-labels-on-members.md) ("The contract for #1390 and #1391"), [0047](0047-entitlement-rules-feed-access-groups.md), [0030](0030-retention-periods-enforced-by-scheduled-tasks.md)

## Context

A manager shares one address on a sales page, in a newsletter or in a course, and the people who open it join the community with set access groups and tags (#1391). Until now a person joins only through an invitation to their own address (ADR 0019) or by themselves in an open community (ADR 0044). A link works for anyone who holds it, as long as it is on: a link that gives a paid group is a free pass. The person who signs up is not always the person who owns the address (ADR 0057, consequence 5).

## Decision

### The link

1. `App\Models\InviteLink` (`invite_links`): `cuid`, the community, a `name` (2 to 64 characters, cleaned as a tag name, not unique, for the managers only), `active` (true by default), `expires_at` and `max_uses` (1 to 10000, both optional), and `uses_count`. A link always gives the role `member`: no field sets a role, and the other roles stay for an invitation to one address. A link has no column for its creator: less data, and the audit log (#934) is the place for that.
2. The token is `Str::random(40)` (letters and digits, about 238 bits). The table keeps its sha256 (`token_hash`, unique) to find the link, and the token itself encrypted with the key of the application, so that the managers copy the link again from the list: a link is shared again and again, and a copy of the database without the key gives no working link. No query reads a link by its token: `InviteLink::findByToken()` hashes it first.
3. The address is `/invite/{token}`. It holds no slug: a rename of the community keeps every link.
4. `InviteLink::status()` is the one place of the state, in this order: `off` (not active), `ended` (the end is now or past), `full` (`uses_count` at least `max_uses`), else `active`. Only `active` lets a person join. An update may set a maximum at or below the count: the link is then full. An ended or full link stays in the list with its state.
5. The groups and the tags of a link go through `InvitationGrants` (ADR 0061): cuids of the community, 50 groups and 20 tags at most, `manageAccessGroupMembers` for groups and `assignMemberTags` for tags, and `groups:write` and `member-tags:write` for a personal token. A tag of any visibility can be on a link (the contract of ADR 0060, point 5). The link tables `invite_link_access_groups` and `invite_link_member_tags` have the shape of those of the invitations; a deleted group or tag leaves every link.
6. A group that a rule on an entitlement feeds is refused on a link ("A link cannot give a group that a rule on an entitlement feeds"), whether the feature of ADR 0047 is on or not: a paid group follows what a person owns. A group that a rule feeds at the moment of a join is not given, also when the rule came after the save.
7. A community has at most 50 links, whatever their state. A personal community has no link (a validation error on `community`).
8. A change of the groups or the tags of a link changes nothing for the people who joined before. A delete changes nothing for its members: they keep their groups and tags. It takes the list of who joined.

### Who manages the links

9. `CommunityPolicy::manageInviteLinks`: the owner and the admins, as "Who can join". A link opens the community to anyone who holds it, and it can give paid groups. A moderator, also with `manage_members`, gets 403 on each route of the managers and sees no section of links. The create and the update also check the rights of the groups and the tags, so that a later change of this rule keeps them.

### The join through a link

10. `JoinCommunityThroughInviteLink` (contract `JoinsCommunitiesThroughInviteLinks`), not `JoinCommunity`, which refuses a community that is not open: a join through a link works the same in a community by invitation and in an open one. The checks, in this order: a verified address; a token that finds a link; then, in one transaction of 3 attempts that locks the community (`for key share`) and then the link (`for update`), the order of the accept of an invitation: a link that works of a community that is not personal; a person who is not a member (the owner counts); a person without a join block. Then the person becomes a member, `uses_count` goes up by 1, the join is recorded, and `JoinGrants` gives the groups (by hand) and the tags. After the commit: a log line of cuids, `CommunityMembersChanged`, `EntitlementReads::afterJoin`, and the events of `JoinGrants`.
11. A member who opens a link gets "You are already a member of this community", and nothing changes: else every member who sees a leaked link gives themself its paid groups. A person whom a manager removed gets the text of the block: a link is a door for anyone, so it is a join by oneself in the sense of ADR 0044; an invitation by email or "May join again" lifts the block. Neither is counted.
12. Two clicks of one person give one membership and one count. Two people at the last use: one joins, the other gets the answer of a link that does not work. Nobody joins without a `POST` of that person: opening a link writes no membership, no count and no row.
13. An unknown token, a link that is off, ended, full or deleted, and a link of a personal community give the same 404 with one message: "This invite link does not work. Ask the person who gave it to you for a new one." A person learns nothing about a link that does not work.

### The way in

14. `GET /invite/{token}` (`invite-links.open`) keeps the sha256 of the token in the session and answers a redirect to `GET /invite` (`invite-links.show`), the page of the link. So the token does not stay in the address: after the first request, every address of a page is `/invite`. The session never holds the token: it keeps the hash only. Its middleware (`StartSessionWithoutTokenAddresses`, bound in place of the session middleware of the framework, for the web group and for the first-party requests of the API) keeps no address under `/invite/` (also an address with a character too many, which answers 404) and no address of the API read of a link (`/api/v1/invite-links/...`) as the previous address. Since [ADR 0064](0064-the-session-and-the-statistics-keep-no-address-that-holds-a-secret.md) these paths are part of one rule, `SecretAddresses::holdsASecret()`, which also covers every signed route and every route with a token parameter. A session that ends because its account was taken over (ADR 0059) and that opens a link keeps the hash and the page of the link as the intended address, and goes to the sign-in page. The page reads the link of the session. "Join" is a `POST /invite` (`invite-links.join`, with the CSRF token and the limiter `joins`) that reads the link of the session; the page sends the cuid of its community, and a link that does not work gives one refusal, whatever cuid the page sends; the join is refused with a clear text when the link of the session is of another community (another tab opened a newer link since): the page then shows the newer link. After a join, and when the page finds a member, the session forgets the link. A token of another shape gives the page of a link that does not work.
15. The page is in the group `auth.optional`, in a group of its own: a visitor reads it, and the session keeps it as the intended address; an account whose address is not verified goes to the address check, which keeps it too. After a sign-up, a sign-in, the second factor, a provider or a sign-in link in the same browser, and after the address check in the same browser, the person is back on the page and presses "Join".
16. The page is not a landing (ADR 0057): it is not in `Landing::ROUTES`, and its token never goes in the link of the address check, in a sign-in link or in any mail: the person who signs up is not always the owner of the address, so a token in that mail gives the link to another person. `Landing::intend()` keeps an intended address of a route of `Landing::KEPT_ROUTES` (the link of an invitation and the page of a link): a link of the address check with the landing of another page does not replace it. A person who confirms the address in another browser lands as before, and opens the link again: the page tells a visitor so, and the link works many times.
17. The page loads no statistics script and sends no request to another host (the prop `statistics` of `Analytics::pageProp()` is null for both routes of a link, which stay in `Analytics::PAGES_WITHOUT_STATISTICS`; since [ADR 0064](0064-the-session-and-the-statistics-keep-no-address-that-holds-a-secret.md) no page loads a statistics script, and the prop is also null at every address that holds a secret). `robots.txt` blocks `/invite` and every address under `/invite/` (`Disallow: /invite$` and `Disallow: /invite/`), and the page answers `noindex, nofollow`. No log line, error, broadcast, export or prop of another page holds a token or its hash. The props of the page hold the community (its public shape, or the shape of a member for a member) and the state of the person (`join`, `member`, `blocked` or `unusable`) only: never the token, the name of the link, its groups, its tags, its counts, its end, the member count or a member.
18. The page and the API read of a link use the limiter `invite-links` (20 a minute per account, per IP address for a visitor), and the address of a link has its own limiter `invite-link-openings` (20 a minute), so that one opening counts once in each: 20 openings a minute. The joins use `joins`. The rate limits and the bot check of the sign-up apply as for every sign-up.

### The record of the joins

19. `invite_link_uses`: the link, the member, `created_at`, a unique pair and an index on the member. No `cuid` and no `updated_at` (an exception to data rule 1, as `access_group_members`): the row is never public on its own and never changes. After a leak, the managers find who used the link and take the groups away; the audit log (#934) does not exist yet. The owner and the admins read the list (newest first, paged).
20. A row goes when the member leaves the community or is removed (`Community::removeUser`), with the link, with the account, and `retention.invite_link_uses_days` (365 by default) after the join (`retention:prune`). The count of the link never goes down: a member who leaves stays counted. The link itself holds no personal data of a member and has no retention period.
21. The member export lists the joins of the member (`invite_link_uses`: the community, the cuid and the name of the link, the date). The community export lists the links (`invite_links`: the state, the end, the maximum, the count, the cuids of the groups and the tags, and who joined), never the token, its hash or the address. The privacy policy names the data, its readers and its period.

### The API

22. Routes of the owner and the admins: `GET`, `POST communities/{community}/invite-links`, `GET`, `PATCH`, `DELETE .../invite-links/{inviteLink}`, `GET .../invite-links/{inviteLink}/uses`, with the abilities `invite-links:read` and `invite-links:write`, which are not in the default set of a token: a token for the invitations must not open a door for anyone (ADR 0022). The refusals come in this order: 404 (a link of another community), 403, 400 for a field, then the limit of 50 (422). The resource gives `url` (with the token) to the managers only, and never the hash.
23. Routes of the person: `GET invite-links/{token}` (`communities:read`) gives the community and the state; `POST invite-links/{token}/membership` (`communities:write`) joins and answers 201 with the community as a member sees it.

## Consequences

1. A manager shares one address, and the managers see who joined through it. A leaked link is turned off, and a new link is made: there is no "Reset the link".
2. The texts of the refusals of the server are in `App\Support\InviteLinkTexts`; the words of the screens are in `invite-link-words.ts`. The screens say "invite link", never "invitation link", which names the links of the invitation mails.
3. When a member limit per community comes (#1454), a join through a link counts like every other join.

## Alternatives considered

1. A hash only, the address shown once at the create: a link is shared again and again, and the managers would make a new link each time they lose the address.
2. Moderators with `manage_members` manage the links too: a link opens the community to anyone, as "Who can join", which is for the owner and the admins.
3. A group that a rule feeds accepted with a required end and a required maximum: still a free pass to a paid group while the link works.
4. The groups and the tags of the link added to a person who is already a member: every member who sees a leaked link would take its paid groups.
5. A link that brings back a person whom a manager removed: the block is about joining by oneself, and a link is open to anyone.
6. The count only, without a record of who joined: after a leak nobody could find who took the groups.
7. The token in the link of the address check, to come back to the page in another browser: the token would go to the owner of the address, who is not always the person who signed up.
8. The page at the address with the token: the token would stay in the address, in the history of the browser and in a referrer. The redirect to `/invite` keeps its hash in the session only.
9. An end or a maximum required on every link that gives a group: groups without a rule are often free groups (a cohort, a team), and the list shows each active link with what it gives.
10. The token in the props of the page, and a join through the API with it: the token would be in the HTML, in the history state of the page and in the address of each join request. The `POST /invite` reads the link of the session instead.
