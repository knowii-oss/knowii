# 0056. The member directory: every member reads the member list of the community

- Status: Accepted, amended by [0063](0063-a-member-chooses-to-be-in-the-member-list-of-their-communities.md) (a member can hide from the member list: decisions 7, 10, 13 and 23)
- Date: 2026-11-20
- Issue: #148
- Pull request: #1701

## Context

A member found another member only through a post, a mention, the online list or a search field of a form. No screen listed all the members of a community for a member (#148).

The data was already open to every member:

1. `GET /api/v1/communities/{community}/members` gives every member of the community to every member of the community (`CommunityPolicy::view`), with the token ability `communities:read`, which is in the default set of a new token ([ADR 0022](0022-api-token-abilities.md)). An entry is a `MemberWithRoleResource`: `cuid`, `name`, `username`, `avatar_url`, `headline`, `role` and `member_since`. The headline is its only profile field (#1688, [ADR 0020](0020-members-choose-visible-profile-fields.md)): an empty text unless the member wrote one and shows it, for every reader.
2. The member management of the settings, the member field of a conversation and of an event, and the mention list read that list. Its only order was the roles: the owner, the admins, the moderators, the members.
3. A visitor never gets a member list, the team, the online members or a profile field ([ADR 0044](0044-public-spaces-for-visitors.md)). Every member page is behind the sign-in, and a signed-in person who is not a member gets the page without access (#1561).
4. The privacy policy said: "The owner and the administrators of a community see its member list." A screen that shows the list to every member makes this sentence wrong.

The owner decided on 2026-11-20: the directory is a page for the members, with a search by name, the sorts "A to Z" (the default) and "Newest", a role label only for the owner, the admins and the moderators, and no change for two people with a block. The setting that lets a member hide from the list (#1466) and the setting that closes the directory to the members (#1451) are later slices. The first one lands before the data of a live community is imported.

## Decision

### Who opens the directory

1. The page is `GET /community/{communitySlug}/members` (`members.index`, `MemberController::index()`), in the group of the pages that need an account with a verified address.
2. A visitor goes to the sign-in page and comes back to the directory. An account without a verified address goes to the page of the address check.
3. A signed-in person who is not a member gets 403 with the page without access (`NoAccess::authorizeView()`). The answer holds no member.
4. Every member of the community opens the directory, with any role. No community setting changes this yet.
5. A community that does not exist answers 404.
6. The API list keeps its access: 401 without an account, 403 for a person who is not a member, 403 for a token without `communities:read`. There is no new ability and no new endpoint.

### What an entry holds

7. The directory uses `MemberWithRoleResource` as it is, in the props of the page and in the API: the seven fields of the context, and nothing else.
8. An entry never holds an email address, another profile field (bio, location, phone, links), the online status, the date of a visit, an access group, an internal identifier, or the state of a block.
9. A member who hides every profile field has the same entry as any member, with an empty headline. A manager gets what a member gets.
10. The list holds the owner and every person with a membership row of this community, and nobody else. A person who left, a person whom a manager removed, a person with a pending invitation and a deleted account are not in it ([ADR 0024](0024-account-deletion-removes-personal-data.md)). The count follows.
11. A block changes nothing ([ADR 0028](0028-direct-messages.md)): each of the two people sees the other, and no entry tells that a block exists.
12. An access group changes nothing: a member is listed with or without a group, to every member.
13. The owner, the admins and the moderators get the same entries as a member. A person who can add members (`CommunityPolicy::addCommunityMember`) also gets the prop `canManageMembers`, and the page shows a link to the settings. The management itself stays in the settings.

### The search and the sorts

14. One search for the directory, the settings, the member fields and the mention list: `CommunityMemberQuery::for()`. It keeps the members whose username starts with the text, or whose name has a word that starts with it, without the case, with the accents as typed, and with the wildcards of the input as plain characters.
15. The search reads `users.username` and `users.name` only. A text that is equal to an email address, or to a word of a headline, a bio or a location, finds nobody (unless the name or the username starts with it).
16. The search never leaves the community: the query reads only the people of one community (its membership rows and its owner), so a person outside it is never in the result or in the count.
17. The search text is trimmed, and the server keeps its first 100 characters (`Constants::MEMBER_SEARCH_MAX_LENGTH`). A value that is not a text is an empty search.
18. `App\Enums\KnowiiMemberSort` has three orders. `role`: the owner, the admins, the moderators, the members, each by username; with a search, the usernames that start with the text come first. `name`: the name from A to Z without the case, then the username. `newest`: the date of joining from the newest to the oldest, then the username; the owner has no membership row and joined at the date of the community. The username is unique, so each order is total.
19. The API list takes `sort`. Without it, or with any other value, the order is `role`: no client of before changes. The page takes `sort` from its address. Without it, or with any other value (also `role`), the order is `name`.
20. Offset pagination, as every list ([ADR 0011](0011-rest-api-design-guide.md)). The page gives the first 20 members (`Constants::COMMUNITY_MEMBERS_PAGE_SIZE`), and loads the next ones through the API list.

### The limit

21. A limiter of its own, `member-lists`: 60 requests a minute for each account (`Constants::MEMBER_LISTS_PER_MINUTE`), shared by the page and by the API list. The API list leaves the limiter of the Markdown preview, so a search no longer uses the budget of the preview. The 61st request of a minute answers 429. A request is counted after its answer, and an answer of the page that read no member list (a reload for another prop of the page) is not counted. The page handles the 429 of a search, of a sort and of "Load more" itself: the list stays as it is, and a line says "Too many searches. Wait a minute, then try again.".
22. The directory gives no way to find an account outside the community (decisions 10, 15 and 16). Inside the community, the list of all members is the feature itself, and the limiter bounds its cost.

### The privacy policy

23. The sentence about who sees the member list becomes, in the text of the hosted instance and in its template: "The members of a community see its member list: your name, your username, your picture, your headline, your role and the date you joined." The owner approved these words on 2026-11-20.

## Consequences

1. An entry holds one profile field, the headline, under the rule of ADR 0020. A field that enters an entry later (a location, a tag) needs the same check: the member chooses who sees it, and a test proves that a hidden value is in no answer of the list.
2. A member cannot hide from the list until the slice of #1466. That slice is a gate: it lands before the data of a live community is imported.
3. A new member list (a filter, a view, a space type) reads `CommunityMemberQuery`, so it gets the same search and the same people.
4. No migration and no new index. The query starts from the people of the community: the membership rows of the unique index `(community_id, user_id)` of `community_members`, and the owner (`users.id in (select user_id from community_members where community_id = ? union all select <the owner>)`). The database then reads their accounts by the primary key, so a request does not read every account of the instance, and the sort runs on the members of one community. A left join of the membership row gives the role and the date of joining. Measured with 200 033 accounts and 32 listed people: 0.2 ms for a page and 0.1 ms for the count, with an index scan of `users` (the shape of before, "the owner, or a person with a membership row" over a left join, read all of `users`: 31 ms and 22 ms). On a table of a few accounts the planner still reads the whole table, because that costs less. A page costs the same number of queries with 3 members and with 30: the count, the page, and one query for the headlines of the page.
5. The sort by name follows the collation of the database. With `en_US.utf8`, a letter with an accent sorts with its base letter ("Élodie" between "Desmet" and "Eve"); with a `C` collation, a name that starts with an accent comes after Z. Only the search does not fold accents: "elodie" does not find "Élodie". A search without accents is a later change, with an index.
6. A member who joins or leaves between two pages can move one entry. The page drops an entry that it already shows; it does not follow the changes live.

## Alternatives considered

1. A new endpoint for the directory: refused. The list exists, and architecture rule 4 asks for one API that the screens use.
2. A directory for the managers only: refused. It exists in the settings, and it does not answer #148.
3. A cursor in place of offsets: refused. Every list uses offsets (ADR 0011), and the sort by name has no natural cursor.
4. The start of the bio in an entry: refused. The headline (#1688) is the one line of an entry, and it has its own choice of ADR 0020.
5. The order of the roles as a third choice of the page: refused. The team is on the page "About", and a member looks for a person by name or looks at who joined.
