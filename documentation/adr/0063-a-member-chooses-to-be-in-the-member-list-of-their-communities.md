# 0063. A member chooses to be in the member list of their communities

- Status: Accepted
- Date: 2026-11-23
- Issue: #1466
- Amends: [0056](0056-the-member-directory-every-member-reads-the-member-list.md) (decisions 7, 10, 13 and 23: an entry of the member list has nine fields, the list leaves out the hidden members for a member who reads it, a manager can get more entries than a member, and the privacy policy says how to hide)

## Context

Every member of a community read the whole member list of that community: on the page "Members" (#148, ADR 0056), in the member search, in the member fields and in the mention suggestions. Nobody could leave it.

1. One query gives every member list: `CommunityMemberQuery::for()` gives the owner and every person with a membership row of the community, with `community_role` and `community_member_since`. `team()` is the same list with the owner, the admins and the moderators only. The query had no reader: every caller got every member.
2. The callers on the server: the API list `GET /api/v1/communities/{community}/members` (ability `communities:read`, limiter `member-lists`), the answer of a role change, the directory page, and the team of the page "About" through `team()`.
3. The callers of the API list on the client: the directory ("Load more"), the member management of the settings, the transfer of the ownership, the member field of a new conversation and of the host of an event, the mention suggestions, and "Send a message" on a member page: the link `?to=<username>` made the Messages page search the member list for that username.
4. An entry is a `MemberWithRoleResource` with eight fields: `cuid`, `name`, `username`, `avatar_url`, `headline`, `role`, `member_since`, `tags` ([ADR 0060](0060-member-tags-labels-on-members.md)).
5. The managers of the members are the owner, the admins and the moderators with the right `manage_members` (ADR 0060 decision 12): `CommunityPolicy::addCommunityMember` and `viewMemberTags` both use `CommunityRoles::allows(..., ManageMembers)`.
6. The member page answers 200 for any person of the community and 404 for anyone else. It never reads the member list.
7. A typed mention links `@username` for the owner and every person with a membership row, and the mentioned people get a notification (`Markdown::mentionableMembers()`). It does not read the member list.
8. "Online now" reads the presence channel of the community. A member who hides the online status never joins it ([ADR 0027](0027-presence-channels.md)). It does not read the member list.
9. The member count of a community counts the membership rows and the owner. A visitor never gets it ([ADR 0044](0044-public-spaces-for-visitors.md)).

In the live community of the owner, 117 of 610 people hid from the member list of the old tool. The import of that community (#1396) must carry their choice, so this decision lands before the import.

The owner decided on 2026-11-23: one switch per account, the team always listed, the managers see the hidden members with a marker, a typed mention and the online list do not change, the member count counts everyone, a section of the profile page, shown by default, the hidden member sees their own entry, and the community export does not hold the choice.

## Decision

### The setting

1. One switch per account: the column `users.show_in_member_list`, boolean, not null, default `true`. There is no choice per community, as ADR 0020 point 3 and the online status.
2. An account of today and a new account are shown, through the default of the column. The model reads it with `User::showsInMemberList()`, which gives `true` when the attribute is not loaded.
3. The action `App\Actions\Users\UpdateMemberListVisibility`, behind `App\Contracts\Users\UpdatesMemberListVisibility`, is the one writer of the column: the rule `show_in_member_list` `required`, `boolean`, and one log line `Member list visibility changed` with the cuid of the account and `shown`.
4. The route `PATCH /api/v1/user/member-list` (`ApiRoutes::USER_MEMBER_LIST`), for an account with a verified address, with the ability `user:write`. It answers 200 with the current user, and 400 for a missing or a wrong value (the validation issue of every route).
5. `UserResource` gives `show_in_member_list` to the person only. No other resource reads the column: another reader learns its effect only through `in_member_list` (decision 14).

### Who is in the member list

6. A hidden member is a member whose `show_in_member_list` is `false`, in a community where the member is not the owner, an admin or a moderator.
7. The team is always listed: in a community where a person is the owner, an admin or a moderator (with or without a moderator right), that person is in its member list whatever the switch says. In another community where the same person is a member, the switch applies.
8. `CommunityMemberQuery::for(Community $community, User $reader, string $search = '', KnowiiMemberSort $sort = Role)`: the reader is required, so each new caller decides who reads the list. When the reader cannot see the hidden members (decision 12), the query keeps only the people who show themselves, or who are the owner, or whose membership row has the role `admin` or `moderator`, or who are the reader. It is one condition in SQL: no query per member.
9. `team()` builds on the same private builder as `for()`, and needs no reader (decision 7).
10. For a reader who is a plain member, or a moderator without `manage_members`, no list holds a hidden member: the directory and its count, the API list with and without `query`, with each `sort` and each `offset`, and its `totalCount`, the search (also for the exact username), the member fields of a conversation and of an event, and the mention suggestions. The team of the page "About" does not change. The answer of a role change reads the list as the caller, who manages the members.
11. What does not change, for every reader: the member page of a hidden member answers 200 for a member of the community; a typed mention `@username` of a hidden member still makes a link and a notification (only the suggestions leave the person out); the online list follows its own switch of ADR 0027; the member count of the community counts every member (#1451 point 5 is the later switch that hides the counts); the role badge; the access group lists, the member list of a tag, the event answers, the reactions, the authors, and the participants of a conversation; a block (ADR 0028 point 5).

### Who still sees a hidden member

12. `CommunityPolicy::viewHiddenMembers()`: the owner, the admins and the moderators with `manage_members`, the same people as `addCommunityMember` and `viewMemberTags`. `CommunityMemberQuery` checks it once per query, never once per member.
13. A manager gets every member in every list of decision 10, the member management of the settings and the transfer of the ownership too. The person themself gets their own entry in each list and in the search.
14. An entry has a ninth field, `in_member_list`: whether the other members of this community find the person in the member list (decisions 6 and 7). The query computes it in SQL (`community_in_member_list`). It is `false` only on the entry of a hidden member, and only a manager and the hidden member themself get such an entry.
15. The directory and the member management show the marker "Hidden from the list" on such an entry. A hidden reader of the directory (not on the team) gets the line "You are hidden from this list. Only you and the managers see your entry." with a link to the profile (the prop `hiddenFromList`, computed on the server).

### "Send a message" to a hidden member

16. The link `?to=<username>` of a member page no longer reads the member list. The Messages page gets the prop `recipient`: the person of the community (the owner or a person with a membership row) whose username is equal to `to` without the case, as an author (`cuid`, `name`, `username`, `avatar_url`), or `null` for the reader, for a person outside the community, for an unknown username and for a value that is not a text. It ignores the switch: the reader already holds the username, and the member page answers for it.
17. The page opens the dialog with `recipient` when the community allows direct messages. `StartConversation` still checks the block and the setting of the community.

### Personal data

18. The choice is personal data of the account (data rule 10). The export of the account holds `show_in_member_list` in `account` of `account.json`, and its read-me names "your choice to be in the member lists". The column goes with the account on a deletion ([ADR 0024](0024-account-deletion-removes-personal-data.md)).
19. The community export does not hold the choice: it is a choice of the account, like the online status, and that export holds no profile field of the members.
20. No retention period: the choice lives as long as the account.
21. The privacy policy, in the text of the hosted instance and in its template, names the choice in the data of the account ("and your choice to show or hide yourself in the member lists of your communities,") and, after the sentence of ADR 0056 decision 23: "You can hide yourself from the member lists in your profile. The owner, the administrators and the moderators who manage members still see you there, with your choice, and the owner, the administrators and the moderators of a community are always in its member list." The owner approved these words on 2026-11-23.

### What the import of #1396 gets

22. The column and its one writer: the import calls `UpdatesMemberListVisibility::update($user, ['show_in_member_list' => false])`.
23. A choice that an import carries only ever hides. It never turns the switch on: the switch is per account, and the person may have chosen in another community.
24. The import creates invitations, never accounts. So #1396 adds the flag to the invitation (its own column, its own migration) and applies it in the transaction of the join, as ADR 0060 does for the tags.
25. A question for #1396: which words the join shows to a person whom the import hides.

## Consequences

1. A new member list reads `CommunityMemberQuery::for()` with a reader, so it gets the rule. The reader is a required parameter: a caller cannot forget it.
2. Hiding stops discovery, and it is not a privacy wall: the member page answers for a known username, a typed mention links and notifies, the posts show the author, and "Send a message" works from the member page. The text of the switch says so.
3. The member count of the community still counts everyone until #1451 point 5. The directory counts what the reader gets, so its number can be lower than the count of the community.
4. A manager can get more entries than a member, as ADR 0060 made a manager get more tags.
5. No index on `users.show_in_member_list`: the query starts from the people of one community (ADR 0056 consequence 4), and the condition runs on those rows. The page and the API list cost the same number of queries with 3 members and with 30, for a member and for a manager: the check of the reader (`viewHiddenMembers`) costs at most two more queries for each request, never one for each member: for a plain member, `CommunityRoles::allows()` reads the membership row two times (for the role admin, then for the role moderator).

## Alternatives considered

1. A choice per community, on `community_members`: refused. The owner has no membership row, a transfer moves the rows, and ADR 0020 point 3 chose no choice per community. A choice per community can come later on top of the switch.
2. The switch on `user_profiles`: refused. It is not a profile field, and it changes no field of an entry.
3. The hidden members out of the team too: refused. The team is a public function of the community, and members need to find who runs the place.
4. A hidden member out of the online list too: refused. The two switches stay apart, and one switch never changes another in secret.
5. A 404 on the member page of a hidden member: refused. It breaks every link of their posts, comments and mentions.
