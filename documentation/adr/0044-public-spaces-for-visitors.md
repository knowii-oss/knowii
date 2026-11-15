# 0044. Public spaces can be read by visitors, without an account

- Status: Accepted
- Date: 2026-11-15
- Issue: #1387
- Pull request: #1489

## Context

The policies already answered for a person who is not signed in: `SpacePolicy::view`, `PostPolicy::view`, `CommentPolicy::view` and `CommunityResourcePolicy::view` take a null user and return true for a public space. Only the route middleware (`auth:sanctum`, `verified`) stopped visitors. A signed-in person who is not a member (an "outsider") already read the public spaces of any community. The decision of 2026-11-10 stays: the visibility of the space decides, not the one of the community, so a public space is readable by everyone, also in a private community.

On 2026-11-15 the owner decided which spaces of the Knowii Community are public (the announcements and the "start here" space), approved the sentence of the privacy policy, and decided that a chat space is never readable without an account. This record covers the first pull request of #1387, "the reader": the pages and the API reads for visitors. The public community page (#1485), the "Who can join" setting (#1486) and the indexing setting (#1487) come later.

## Decision

### Three readers

1. A visitor is not signed in. An outsider is signed in and not a member of the community. A member is a member. A visitor and an outsider read the same things.

### The pages

2. The pages of a public space of type discussion, pages, course, resources and link are read by a visitor: the space page, the post page, the page or lesson page, and the resource page. They moved out of the signed-in group of `routes/web.php` into a group behind `auth.optional`.

| Page                                                               | Visitor                                | Outsider          | Member                                            |
| ------------------------------------------------------------------ | -------------------------------------- | ----------------- | ------------------------------------------------- |
| Space, post, page, lesson or resource of a public space (not chat) | reads                                  | reads             | reads                                             |
| Any page of a `members` or `groups` space                          | sign-in page, then back to the address | 403               | reads (`groups`: in a group, or owner and admins) |
| A public chat space                                                | sign-in page, then back to the address | reads (as before) | reads                                             |
| Community home, about, member, messages, settings                  | sign-in page, then back to the address | 403 (as before)   | as before                                         |

3. The community home for a visitor is #1485. Until then a visitor is sent to the sign-in page there too.
4. For a visitor, a page that does not exist gives the same answer as a page that is refused (the sign-in page), so that nobody learns which private spaces exist.

### The reading rule

5. `SpacePolicy::view` is the one place of the reading rule. A public space is readable by anyone, except a chat space: it holds running talk, and it stays for signed-in people, also when it is public. The space form says so when a manager chooses "public" for a chat space. An outsider keeps what they have today.

### What a visitor gets of members

6. On a post, a comment, a page and a resource: the name, the username and the avatar of the author (`AuthorResource`). The name is not a link for a visitor: the member page stays for members.
7. A visitor never gets: a member list, the team, the online members, the profile fields, the member count, who reacted (counts only), the names of access groups, `moderator_permissions`, `direct_messages_enabled`, the captured article of a resource, the progress in a course, the quiz of a lesson and its results.
8. Props are cut on the server, never hidden by the client. `SpaceResource.access_groups` is for the people who manage spaces. `CommunityResource` for someone who is not a member holds only the cuid, name, slug, description, visibility and `is_member: false`. The `permissions` of a page are all false for a visitor, `progress`, `quiz` and `article` are null, `currentUserCuid` is empty, and the shared props hold no profile. `tests/Feature/PublicSpacesTest.php` scans the whole serialized props of each page, and each API answer, for the forbidden fields and for the data of the author.

### The API

9. These `GET` routes accept a visitor, keep their gate (`view`), and are limited per IP address (the `public-reads` limiter, 60 a minute, for requests without an account only): the space, the posts of a space, one post, the comments of a post, the pages of a space, one page, the materials of a course (the list), the resources of a space, one resource, and the comments of a resource.
10. Everything else stays for signed-in people: who reacted, chat messages, online members, member search, the quiz, the download of a course material (the list is visible, the download needs an account), the navigation tree route, and every write.
11. The middleware `auth.optional` (`AuthenticateIfPresent`) does the sign-in. With a session or a token it is `auth:sanctum` followed by `verified`: the same guard, and an unverified account is refused (403 on the API, a redirect on the web). A token that Sanctum refuses (unknown, revoked, expired) gets a 401: it never becomes a visitor. Without credentials the request goes on as a visitor and the middleware marks it (`knowii.visitor`). `RequireTokenAbility` lets only a request with that mark go without a user, so the abilities of a token are always checked when there is a token, and no other route opens by accident. The routes that stay closed keep `auth:sanctum` and answer 401 as before. A visitor refused on a public route gets a 401, an outsider a 403.
12. `ApiTokenAbilitiesTest` lists the public routes by name, checks that each has an `ability:` middleware, is a `GET` and is limited.

### No websocket for a visitor

13. A visitor opens no websocket connection: no Echo, no channel, no presence. The pages are static for them. `useSocket` takes `enabled`, and the pages and components that subscribe pass `false` for a visitor. `window.Echo` is created on its first use, because creating it opens the connection.

### What a visitor sees on the screen

14. The layout shows "Sign in" and "Sign up" in place of the user menu, no link to the dashboard, and keeps the theme switch.
15. In place of each composer: "Sign in to reply" or "Sign in to post". The link goes to the sign-in page, and the sign-in comes back to the page. The server keeps the address of the last page that a visitor read as the intended address, and a redirect to the sign-in page keeps its own. Reactions are counts. The list of materials of a course shows "Sign in to download".

### The notice

16. No notice, by decision of the owner on 2026-11-15 (#1493): a public space shows no "Public" label next to its name and its composers keep their normal button texts. The space form tells the manager: "Anyone on the internet can read this space, without an account, with the names and pictures of the authors." and, for a chat space: "A chat space stays for signed-in people." The texts of the form are in `Components/Spaces/PublicSpaceNotice.tsx`. The routes that write content describe the same thing in the OpenAPI document, and `documentation/api/README.md` lists the public routes.
17. The privacy policy has the approved sentence in the section about who receives the data.

### Search engines

18. Indexing does not change in #1387. `robots.txt` still blocked `/community/`, and the pages of the communities stayed `noindex`, also for a visitor of a public space, also on an instance that allows indexing. The section "Indexing" below (#1487) changes this.

### What comes next

19. #1485 (see "Public community page" below). #1486 (see "Who can join" below). #1487 (see "Indexing" below).

## Public community page

A change inside the same decision (#1485): the community home has a public version, for a visitor and for a signed-in person who is not a member.

1. It exists only for a community with at least one public space that is not a chat space. For any other community a visitor goes to the sign-in page and back (the API answers 404), and a signed-in outsider gets 403, as before. A community that does not exist answers a visitor like one without a public space. A member gets the community home as before.
2. It shows the name and the description of the community, the navigation of the public spaces (never a chat space, also for a signed-in person), the 10 newest posts of the public discussion spaces (title, excerpt, author as name, username and avatar, space, date, a link to the post), and "Sign in" and "Sign up" for a visitor. The place of the "Join" button is filled by #1486 (see "Who can join" below).
3. The props are cut on the server: the community in its public shape, the tree of the public spaces and the newest posts. Never the member count, the team, a member list, the online members, profile fields, the names of access groups, the moderator rights, the direct message setting, anything of a chat space or of a space that is not public, and no permission.
4. API parity: `GET /communities/{community}`, `GET /communities/{community}/space-groups` and the new `GET /communities/{community}/newest-posts` accept a visitor, with the public rate limit and the abilities `communities:read` and `posts:read`. A deleted post is never listed (#1344).
5. For a visitor, "Home" in the breadcrumb goes to `/` and the name of the community goes to this page. No WebSocket is opened. Indexing is decided by the community (see "Indexing" below, #1487).

## Who can join

A change inside the same decision (#1486), and not a new ADR: a visitor who reads a public space meets a closed door at the first members-only page, so the rule for the door belongs next to the rule for the pages. It adds a second way in beside the invitation ([ADR 0019](0019-community-invitations-by-email.md)), and replaces nothing in it.

1. A shared community (never a personal one) has a setting "Who can join", the column `join_policy` (the backed enum `KnowiiCommunityJoinPolicy`): `invitation` (the default, the behavior before this change) or `open`. With `open`, any signed-in person with a verified email address joins by themselves, as a member. Nothing in the platform is specific to one community: any shared community has the setting.
2. The owner and the admins change it (`CommunityPolicy::manageJoinPolicy`, like the moderator rights), on the settings page of the community, with the sentence "Anyone with an account can join this community and read its members-only spaces." and with `PATCH /communities/{community}/join-policy`. The value is read on `GET /communities/{community}` (`join_policy`). A personal community answers 400 (validation).
3. `POST /communities/{community}/membership` is the join (`JoinCommunity`): the setting is `open`, the address is verified, the person is not a member, and no manager removed the person (point 5). It adds a member with the role member, sends `CommunityMembersChanged` (the same event as an accepted invitation), and returns the community as a member sees it (201). Each refusal is a 422 with a clear message. The route needs `communities:write` and is limited per person (`throttle:joins`). The action locks the community row, so a double click adds the member once.
4. "Join" shows for a signed-in outsider on the public community page (the `data-slot="join"` place) and in the header of a public space, when the community is `open`. A visitor sees "Sign up to join" and "Sign in" in the same places. The sign-up or the sign-in comes back to the page that the visitor read (the intended address, see point 15 above; the verification of the address keeps it too), and the person presses "Join": nothing joins by itself after a sign-up. The public shape of the community (`CommunityResource` for a non-member) gets one field, `join_policy`.
5. A person whom a manager removed cannot join again by themselves, even when the community is open (decision of the owner, 2026-11-15). The table `community_join_blocks` holds the smallest thing: community, person, date (a unique pair, deleted with the community and with the account). `RemoveCommunityMember` writes the row, in the same transaction as the removal. A member who leaves by themselves (`LeaveCommunity`) writes none. An accepted invitation deletes the row, and so does "May join again" in the member management (`DELETE /communities/{community}/join-blocks/{member}`, the managers of the members: owner, admins, and moderators with `manage_members`; `GET /communities/{community}/join-blocks` lists the people). The person sees a neutral message: "You cannot join this community by yourself. Ask its managers for an invitation." The row is new personal data: it is in the member export (`memberships.json`, `join_blocks`), in the community export, and it goes with the account (cascade). The privacy policy gets one sentence for it after the owner approves the words.
6. Joining gives no access group. The entitlement rules of a later issue run when a person becomes a member: `CommunityMembersChanged` is the hook, fired by the join, by an accepted invitation and by a removal. Nothing is built for it here.
7. An open community without a public space has no page for an outsider (the community home answers 403, as before) and no "Join" button on the web. The join still works through the API for a person who knows the community. There is no community directory.

## Indexing

A change inside the same decision (#1487), which replaces point 18 above: a community decides if search engines index its public spaces. The robots rules of the instance (#104) stay the first gate.

1. A shared community (never a personal one) has a setting "Search engines may index the public spaces", the boolean column `indexing_enabled`, off by default. The owner and the admins change it (`CommunityPolicy::manageIndexing`, like who can join), on the settings page, with a sentence that says what it does and that it is off by default, and with `PATCH /communities/{community}/indexing` (body `indexing_enabled`, a strict boolean; a personal community answers 400). Members read the value on `GET /communities/{community}` (`indexing_enabled`). It is not part of the public shape: no page for a visitor needs it.
2. `robots.txt` no longer blocks `/community/` as a whole. It keeps blocking what is never public: the pages about, settings, members and messages of a community (`/community/*/about`, `/community/*/settings`, `/community/*/member/`, `/community/*/messages`), the invitation links, the API, the sign-in pages and the other addresses of `config/robots.php`. The decision for a page is in its response.
3. Each response of a page of a community carries the decision in the `X-Robots-Tag` header and in the meta tag, from one place: `Robots::forRequest` (used by `AddRobotsHeader` and by `app.blade.php`), which calls `Robots::directive`. The answer is `index, follow, max-image-preview:large` only when all of these hold: the reader is a visitor, the page is the public community page or a page of a public space that a visitor reads (the space page of a discussion, pages, course or resources space, a post page, a page or lesson page, a resource page), the community has the setting on, the instance allows indexing (`ROBOTS_ALLOW_INDEXING`, true in production by default), and the response is a success. Everything else is `noindex, nofollow`: a signed-in reader, a members or groups space, a chat space, a link space, the pages about, settings, members and messages, the API, and the marker of a deleted post (#1344), a section heading of a page space and any address that does not exist.
4. The AI crawlers that `config/robots.php` lists stay refused everywhere in `robots.txt`, whatever a community decides. `robots.txt` is a request: the header and the meta tag are the decision, and the policies are the lock.
5. Turning the setting off takes effect at the next request: the decision is made for each request, with no cache. A crawler that read the page before keeps what it has until it comes back: the community should know that "off" does not remove what a search engine already holds.
6. The sitemap, the page titles and the descriptions of the public pages are not part of this change (#1400).

## Consequences

1. A public space is public in the full sense: its authors are shown by name and picture to the whole internet. The policy and the notice say so before a member posts.
2. A new route that a visitor may call has to be added to the public group of `routes/api.php`, with its `ability:` middleware, and to the list in `ApiTokenAbilitiesTest`. A new prop on one of the four pages needs a check in `PublicSpacesTest` for the visitor, and one on the public community page a check in `PublicCommunityPageTest`.
3. The first public read costs the same as the member read. The rate limit protects the server from an anonymous crawler.
4. A visitor who then goes to the sign-in page by hand may come back to the last public page that they read. This is the price of the "Sign in" links that need no parameter.

## Alternatives considered

1. A separate set of public controllers and resources: duplicates the pages and drifts from them. The policies already decide, so the same pages serve the visitor with cut props.
2. A `return` parameter on the sign-in link: needs an allowlist against open redirects, and leaves the other sign-in paths (a link by email, a two-factor challenge) without it. The intended address of the session works for all of them.
3. Hiding the member data in the client: refused by the rule "cut on the server".
4. Opening the chat space for reading: it holds running talk, and its members did not agree to be read by the internet.
5. For the join (#1486): joining at the first click of a visitor, without the second click (a sign-up is not a decision to join, and it would join people to communities that they only wanted to read). A block that holds a reason or the name of the manager (more personal data than the rule needs). A block that the leaving also writes (a member who leaves keeps the freedom to come back to an open community).
