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

16. A "Public" label next to the name of the space, and "Post publicly" and "Reply publicly" on the buttons of the composers, for members, in a space that anyone can read. The space form tells the manager: "Anyone on the internet can read this space, without an account, with the names and pictures of the authors." and, for a chat space: "A chat space stays for signed-in people." The form of the notice is one small component (`Components/Spaces/PublicSpaceNotice.tsx`) because the owner still confirms it. The routes that write content describe the same thing in the OpenAPI document, and `documentation/api/README.md` lists the public routes.
17. The privacy policy has the approved sentence in the section about who receives the data.

### Search engines

18. Indexing does not change here. `robots.txt` still blocks `/community/`, and the pages of the communities stay `noindex`, also for a visitor of a public space, also on an instance that allows indexing.

### What comes next

19. #1485: the public page of the community, for a visitor. #1486: the "Who can join" setting and the join flow. #1487: the indexing setting of a community, and the robots rules that follow it.

## Consequences

1. A public space is public in the full sense: its authors are shown by name and picture to the whole internet. The policy and the notice say so before a member posts.
2. A new route that a visitor may call has to be added to the public group of `routes/api.php`, with its `ability:` middleware, and to the list in `ApiTokenAbilitiesTest`. A new prop on one of the four pages needs a check in `PublicSpacesTest` for the visitor.
3. The first public read costs the same as the member read. The rate limit protects the server from an anonymous crawler.
4. A visitor who then goes to the sign-in page by hand may come back to the last public page that they read. This is the price of the "Sign in" links that need no parameter.

## Alternatives considered

1. A separate set of public controllers and resources: duplicates the pages and drifts from them. The policies already decide, so the same pages serve the visitor with cut props.
2. A `return` parameter on the sign-in link: needs an allowlist against open redirects, and leaves the other sign-in paths (a link by email, a two-factor challenge) without it. The intended address of the session works for all of them.
3. Hiding the member data in the client: refused by the rule "cut on the server".
4. Opening the chat space for reading: it holds running talk, and its members did not agree to be read by the internet.
