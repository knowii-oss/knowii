# 0060. Member tags: labels on members, for the members or for the managers only

- Status: Accepted
- Date: 2026-11-22
- Issue: #1389
- Pull request: #1710 (part T1)
- Amends: [0056](0056-the-member-directory-every-member-reads-the-member-list.md) (decisions 7, 9 and 13: an entry of the member list has eight fields now, with its tags, and a manager can get more tags than a member)

## Context

The managers of a community want to label its members, for example "Founder" or "Onboarded" (#1389). The platform had no such label. Three other slices of the launch scope need one: the member directory (#148), the bulk invitations from a CSV file (#1390) and the invite links (#1391). So the data model comes first, with a contract for those slices.

The facts that shaped the design:

1. Access groups ([ADR 0025](0025-access-groups.md)) are named sets of members that open spaces. Their link is on the user (`access_group_members`), because the owner of a community has no row in `community_members` and a transfer of the ownership moves those rows ([ADR 0035](0035-ownership-transfer.md)). Their names are unique with the case.
2. `GET /api/v1/communities/{community}/members` answers every member of the community ([ADR 0056](0056-the-member-directory-every-member-reads-the-member-list.md)). A field on its entries reaches every member.
3. `AuthorResource` knows no community, and a notification crosses communities. A tag belongs to one community.
4. A visitor never gets a member list or a profile field ([ADR 0044](0044-public-spaces-for-visitors.md)).
5. A tag that the managers write about a member is personal data that the member did not give (data rules 8 to 10).

The owner decided every question of the design on 2026-11-20 (each recommendation is the decision) and approved word for word the four sentences of the privacy policy and the text of the form on 2026-11-22.

## Decision

The work has four parts: T1 (the tags, their API and the manager screens, this record), T2 (tags next to the author), T3 (bulk tagging and the filter of the manager list) and T4 (tags in the directory). The decisions are numbered as in the design.

### The data model

1. Two tables: `member_tags` (the tags of a community) and `member_tag_members` (which member has which tag), with the model `MemberTag`.
2. The link is `(member_tag_id, user_id)`, and the community comes from the tag. The owner can get a tag, and a transfer of the ownership keeps the tags. `member_tag_members` has `id`, the two foreign keys (both `cascadeOnDelete`), `created_at`, a unique `(member_tag_id, user_id)` and an index on `user_id`. It has no `cuid` and no `updated_at`, like `access_group_members`: the row is never public. This is an exception to data rule 1, for a link table that no route names.
3. `naming.md` has the row "member tag" (do not use: label, badge, tag alone). The word "tag" alone stays free of the meaning "mention".
4. The name has 2 to 32 characters, trimmed, with each run of spaces made one, no control character (no line break, no tab) and no format character (`\p{Cf}`: no invisible space, no direction mark), so two tags never look the same. It is unique per community without the case: the unique index `member_tags_community_name_unique` on `(community_id, lower(name))`. The name is stored as typed. The error is "A tag with this name exists already."
5. A tag has no description and no text beside its name. A link row has no note: one short name limits what a manager can write about a member.
6. Limits: 100 tags per community, 20 tags per member in one community (`Constants`, mirrored in `constants.ts`).
7. The color is one key of a fixed palette of 8: `gray` (the default), `red`, `orange`, `yellow`, `green`, `teal`, `blue`, `purple`. The database checks the key.
8. The palette is 8 tokens per theme, `--color-tag-<key>`, in `styles.css`. `Badge` has an optional `color`: it colors the border and a small mark before the text. The text keeps the color of all text, so its contrast never depends on the tag. Each token reaches 3:1 on `background`, `surface`, `surface-elevated` and `well` of its theme (`contrast.spec.ts`). The name always shows: the color alone never carries a meaning.
9. No emoji and no icon in T1. T2 adds one optional emoji of the allow-list of the reactions.
10. A tag is a label and never an access rule. It opens no space and gives no right; access groups do that.

### Visibility

11. Two values: `members` (the members of the community see the tag) and `managers`. The default is `managers`. The word "public" is not used: for a space it means every person on the internet.
12. "Managers" are the owner, the admins and the moderators with the right `manage_members`. A moderator without the right reads tags as a member does. No fifth moderator right.
13. A `managers` tag does not exist for another reader: it is in no list, no count and no filter, and a route with its cuid answers 404 with the body of a tag that does not exist, with a session and with any token: the binding of `{memberTag}` checks it before the token abilities.
14. A member sees their own `members` tags, as the other members do. A member does not see their own `managers` tags on a screen, and reads them in the export of their data. The form of a tag tells the manager so.
15. A visitor and a person who is not a member get no tag, in no response.
16. A change from `managers` to `members` shows the tag on every member who has it. The dialog says the number of members and asks again. Only the owner and the admins can do it.

### Who does what

17. The owner and the admins create a tag, rename it, change its color and its visibility, and delete it (`CommunityPolicy::manageMemberTags`).
18. The owner, the admins and the moderators with `manage_members` put a tag on a member and take it off (`CommunityPolicy::assignMemberTags`), and read every tag (`viewMemberTags`). The role hierarchy of #1242 does not apply: a moderator can tag any member, also themself.
19. A delete removes the tag from every member, and from every invitation and invite link that carries it later (a cascade). The dialog says the number of members. Nothing can undo it.
20. Nothing tells a member that they got or lost a tag: no notification, no email, no broadcast.
21. The platform does not store who put a tag. The audit log (#934) is the place for that.

### Where a tag shows

22. One component, `MemberTagList`, renders the tags of one member as `Badge` items in a list named "Tags". For a manager, a `managers` tag has a lock and the hidden words "managers only".
23. The member page shows every tag that the reader can see, under the name (T1).
24. The member list of the settings shows the tags on each row (T1). The directory shows at most three tags per entry, then "+N" (T4).
25. Next to the name of the author of a post and of a comment: at most two tags, and only the tags that a manager marked "Show next to the name in posts and comments" (T2, off by default). Not in chat messages, notifications or conversations.
26. `AuthorResource` does not change. T2 gives `PostResource` and `CommentResource` a field `author_tags`.

### The filter

27. `GET .../members` gets the query parameter `memberTag` (T3 for the manager list, T4 for the directory). A reader who cannot see the tag gets the validation error of a tag that does not exist.

### The API

28. Seven routes under `communities/{community}/member-tags`, in `scopeBindings`: list, create, update, delete, the members of a tag (paged, managers only), add one member, remove one member. The refusals of a write come in this order: 404 (decision 13), 403, 400 for a wrong field, then the limit (422).
29. New abilities `member-tags:read` and `member-tags:write`, not in the default set of a token: a token that syncs tags must not manage paid groups. The field `tags` of a member entry follows the policy of the reader, with `communities:read`.
30. A member is named by `member` (the user cuid) or `username`. The routes take no email address.

### Personal data

31. The community owner decides which tags exist and who gets them; the operator stores them for the owner. The privacy policy names the tags among the data that we get from other people, who sees them, and when they end. No new legal basis is needed.
32. The member export lists every tag of the member, of both visibilities (`member_tags` in `memberships.json`). The community export lists the tags with the cuids of their members (`member_tags` in `community.json`).
33. A leave and a removal delete the tags of the member in that community (`Community::removeUser`). A deleted account loses all its tags. A member who comes back starts with none.
34. A tag has no time limit, so `config/retention.php` has no entry and no prune step; the policy says when a tag ends.
35. The form of a tag shows: "A tag is personal data about each member who has it. Do not record health, religion, political opinions or other sensitive facts in a tag. Each member can read all their tags in the export of their data."
36. The log lines hold cuids, never the name of a tag.

### The code of T1

1. `App\Support\MemberTagsOfMembers` is the one class that decides which tags a reader gets: `for($community, $users, $reader)` with one query for a whole page, `attach()` for `MemberWithRoleResource`, `ofCommunity($community, $reader)` for the list of the tags, `exists($tag, $reader)` for the 404 of the routes, and `bindRoute()`, the route binding of `{memberTag}` that applies `exists()` (it runs before the token abilities). No resource reads the relation by itself.
2. `App\Support\MemberTagAssignments` is the one writer of the links. It checks that the person is a member of the community of the tag, holds the limit of 20, changes nothing the second time, and checks no gate.
3. The actions `CreateMemberTag`, `UpdateMemberTag`, `DeleteMemberTag`, `AddMemberTagMember` and `RemoveMemberTagMember` (behind `App\Contracts\MemberTags`) check their gate first.

## The contract for #1390 and #1391

T1 gives these slices five things, and adds no column to an invitation.

1. `App\Support\MemberTagAssignments::add(MemberTag $tag, User $member): bool` and `remove(...)`: the one writer of the link table. It checks that the member is in the community of the tag, holds the limit of 20, and changes nothing the second time. It checks no gate: the caller did.
2. `MemberTag::findByName(Community $community, string $name): ?MemberTag`: the lookup of the CSV file, without the case, after the same cleaning as the name rule.
3. `CommunityPolicy::assignMemberTags`: who can put a tag on an invitation, on a link or on a row of a file. An invitation holds at most 20 tags.
4. Their link tables (an invitation to a tag, an invite link to a tag) are theirs. Each has a foreign key to `member_tags` with `cascadeOnDelete`: a deleted tag leaves the invitation.
5. The tags apply when the person joins, through point 1, in the transaction of the join. A tag of any visibility can come this way. Whether a file can create a tag that does not exist is a decision of #1390; this design recommends no: an unknown name is a refused row in the preview.

## Consequences

1. A new reader of the tags (a list, a resource, a prop) must go through `MemberTagsOfMembers`, and a new writer of the links through `MemberTagAssignments`. The leak tests of `MemberTagApiTest` scan every answer of a member, a moderator without the right, the tagged member and a person who is not a member for the name and the cuid of a tag of the managers.
2. An entry of the member list has an eighth field, `tags`. The member list costs one more query per page, the same with 3 members and with 30.
3. The eight color tokens are new brand tokens for labels only. A new token value must pass `contrast.spec.ts`.
4. A change of the value lists of the color or the visibility needs a migration: the checks hold literals.

## Alternatives considered

1. One JSON column of names on the membership row: no rename, no count, no filter by index.
2. A key on `community_members.id`: the owner has no such row, and a transfer of the ownership moves the rows.
3. The case-sensitive unique index of the access groups: "Founder" and "founder" as two tags make wrong imports in #1390.
4. A free color: no promise of contrast. The magenta of the brand: it marks actions. The five variants of `Badge`: they mean a state.
5. A set of icons, or a pill with an emoji and no name: a second icon map, no name for a screen reader, no meaning for a new member.
6. The word "public" for a tag that the members see: for a space it means every person on the internet.
7. A fifth moderator right for the tags: ADR 0017 keeps four switches, and the people who manage members are the ones who label them.
8. The signal `community.access_groups.changed` for the tags: it would tell every member the moment of a change of a tag of the managers.
9. The choice "label, icon, or both" per tag of the issue: the label always shows (T2).
10. A screen that shows a member their own tags of the managers: it would make "managers only" mean nothing for a label such as "Onboarded". The export gives the member their data.
