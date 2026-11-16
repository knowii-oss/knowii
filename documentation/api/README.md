# API requests (Bruno)

This folder is a [Bruno](https://www.usebruno.com/) collection. It holds an example request for every endpoint of the REST API (`/api/v1`), including the main error cases. It documents the API by example and lets you exercise it by hand against a running instance.

The reference description of the API is the OpenAPI document that Scramble generates from the code: `/docs/api` (interactive) and `/docs/api.json`.

## Use it

1. Open this folder as a collection in Bruno.
2. Copy `.env.example` to `.env` in this folder and set the email and password of a test user. The `.env` file is not committed (see the Bruno [dotenv secrets](https://docs.usebruno.com/secrets-management/dotenv-file) documentation).
3. Select the `Local` environment (`http://localhost:4200`).
4. Run `Get CSRF cookie`, then `Auth - Login`. Bruno keeps the session cookie for the next requests. For token authentication, run `Auth - Login (include token)` and send the token as `Authorization: Bearer <token>`.

Requests that target an existing item (a community, a space) contain a cuid from the seed data. Replace it with a cuid from your database.

## Tokens and abilities

A personal API token (created on the "API tokens" page of the user menu) has abilities ([ADR 0022](../adr/0022-api-token-abilities.md)). Each route of `routes/api.php` names the abilities that it accepts (`ability:` middleware); a token without one of them gets a 403. The web session and the token of `Auth - Login (include token)` have every ability. A personal token expires after the lifetime chosen when it was created (30 days, 90 days by default, 1 year, or never); an expired token gets a 401. The login token lasts 12 hours.

| Ability             | What it allows                                                        |
| ------------------- | --------------------------------------------------------------------- |
| `user:read`         | Read the current user, their blocks and their frequently used emoji   |
| `user:write`        | Change the avatar, the online status and the time zone                |
| `communities:read`  | Read communities, space groups, spaces, members and invitations       |
| `communities:write` | Create, change and delete communities, spaces, members, invitations; join an open community, leave a community, let a removed member join again, change who can join, let search engines index the public spaces, transfer its ownership |
| `posts:read`        | Read posts, comments and reactions, preview Markdown                  |
| `posts:write`       | Write posts and comments, pin, react, upload images                   |
| `chat:read`         | Read the messages and threads of chat spaces, mark them as read       |
| `chat:write`        | Write, edit and delete messages, react to them                        |
| `messages:read`     | Read your direct messages, mark them as read (not in the default set) |
| `messages:write`    | Start conversations, write, edit and delete direct messages, react    |
| `notifications:read`  | Read your notifications and their count, the state of a thread that you follow, your level for a space, the "Catch up" data (not in the default set) |
| `notifications:write` | Mark your notifications as seen or read, undo read, "I am caught up", follow, stop following and mute a thread, choose your level for a space, visit a space, the pop-up switch (not in the default set) |
| `pages:read`        | Read the pages of page spaces, mark lessons as done, read the progress, read and download course materials, read and answer lesson quizzes |
| `pages:write`       | Create, change, reorder and delete pages and course materials, write and delete lesson quizzes (owner, admins, moderators) |
| `resources:read`    | Read resources, their comments and reactions                          |
| `resources:write`   | Add resources, comment and react on them                              |
| `resources:create`  | Add resources, nothing else (the browser extension, ADR 0018)         |
| `groups:read`       | Read access groups and their members                                  |
| `groups:write`      | Manage access groups and their members (a sync from another system)   |
| `identities:read`   | Read the provider accounts that you connected to sign in: the issuer, a label and the dates, never the identifier at the provider (not in the default set) |
| `identities:write`  | Disconnect a provider account (not in the default set)                |
| `entitlements:read` | Read the entitlement keys that the sign-in provider reported for you and the date of the last check (not in the default set) |

## Entitlements of the sign-in provider

When the instance reads the entitlements of its sign-in provider ([ADR 0047](../adr/0047-entitlement-rules-feed-access-groups.md), #1386, `OIDC_ENTITLEMENTS_URL`), an owner or an admin of a community that the operator lists can give an access group by entitlement keys. Without the feature, and for a community that is not listed, these routes answer 404.

| Method and path | What it does |
|---|---|
| `GET /communities/{community}/access-groups/{group}/rules` | The keys that give the group (`entitlement_keys`, sorted). Owner and admins. Ability `groups:read` |
| `PUT /communities/{community}/access-groups/{group}/rules` | Replace the keys: body `{"entitlement_keys": ["example.paid"]}`. A key holds letters, digits, dots, dashes and underscores, 100 characters at most, 20 keys at most, no duplicate. An empty list removes every rule. Owner and admins. Ability `groups:write` |
| `GET /user/entitlements` | Your own keys (`entitlements`) and the date of the last check (`checked_at`). Ability `entitlements:read`, not in the default set |

A rule that is added, changed or removed runs the sync for the members of the community in the background. The list of access groups carries `entitlement_keys` for those who manage the groups, and `GET /communities/{community}/access-groups/{group}/members` returns `sources` for each member: `manual` (added by hand or by the API), `by_rule` (an entitlement rule gives the group), or both. `DELETE .../members/{member}` clears `manual` only: a member whom a rule gives the group stays in it (the answer is 204 either way, and the member then has `sources` `["by_rule"]`).

The change call of the provider, `POST /webhooks/oidc/entitlements`, is not part of the API: it is public, takes no token and no session, accepts only `Content-Type: application/jwt` and is proven by the signature of the provider. It answers 202 (also for a repeated call and for a person that this instance does not know), 401 with an empty body for any invalid call, 404 without the feature and 429 over the limit.

## Public spaces

A public space (`visibility` public) can be read by anyone on the internet, without an account ([ADR 0044](../adr/0044-public-spaces-for-visitors.md)). The `GET` routes below answer a request with no session and no token, each with the gate of its space, and are limited per IP address (60 requests a minute; a signed-in request is not limited). A chat space is never readable without an account, also when it is public. In any other space they answer 401 without an account, and 403 to a signed-in person who may not read it.

| Route                                                                  | What it returns                              |
| ---------------------------------------------------------------------- | -------------------------------------------- |
| `GET /v1/communities/{community}`                                      | The community, in its public shape (see below) |
| `GET /v1/communities/{community}/space-groups`                         | The navigation: the public spaces, without chat |
| `GET /v1/communities/{community}/newest-posts`                         | The 10 newest posts of the public discussion spaces |
| `GET /v1/communities/{community}/spaces/{space}`                       | The space                                    |
| `GET /v1/communities/{community}/spaces/{space}/posts`                 | The posts of a discussion space              |
| `GET /v1/communities/{community}/spaces/{space}/posts/{post}`          | One post                                     |
| `GET /v1/communities/{community}/spaces/{space}/posts/{post}/comments` | The comments of a post                       |
| `GET /v1/communities/{community}/spaces/{space}/pages`                 | The titles of the pages or lessons           |
| `GET /v1/communities/{community}/spaces/{space}/pages/{page}`          | One page or lesson                           |
| `GET /v1/communities/{community}/spaces/{space}/materials`             | The list of the materials of a course        |
| `GET /v1/communities/{community}/spaces/{space}/resources`             | The resources of a resources space           |
| `GET /v1/communities/{community}/spaces/{space}/resources/{resource}`  | One resource                                 |
| `GET /v1/communities/{community}/spaces/{space}/resources/{resource}/comments` | The comments of a resource           |

The first three routes are the public page of a community (#1485). They answer a visitor and a signed-in person who is not a member when the community has at least one public space that is not a chat space. Otherwise a visitor gets 404 (the same answer as for a community that does not exist) and a signed-in person who is not a member gets 403. The community has the public shape only: `cuid`, `name`, `slug`, `description`, `visibility`, `join_policy` (`invitation` or `open`, #1486) and `is_member` (false). The navigation holds the public spaces without any chat space. `newest-posts` has, for each post: `cuid`, `title`, `slug`, `excerpt`, `author` (`cuid`, `name`, `username`, `avatar_url`, or null for a deleted account), `space` (`cuid`, `slug`, `name`, `emoji`) and `created_at`. It never lists a deleted post, a post of a space that is not public, or one of a chat space. A member gets their full community and their own navigation, and the same newest posts.

Without an account, these answers hold the name, the username and the avatar of the authors, and the counts of the reactions. They never hold: a member list, the team, the online members, profile fields, the member count, who reacted, the names of access groups, the moderator rights, the direct message setting, the captured article of a resource, the progress in a course, or the results of a quiz. Everything else needs an account: who reacted, chat messages, online members, member search, quizzes, the download of a course material (the list is public, the download is not), the navigation of the community beyond its public spaces, and every write.

A request with a token still needs the ability of the route (`posts:read`, `pages:read`, `resources:read`, `communities:read`). A token that is not accepted (unknown, revoked or expired) gets a 401: it is never treated as no token.

## Who can join

A shared community is by invitation (`join_policy` `invitation`, the default) or open (`open`) ([ADR 0044](../adr/0044-public-spaces-for-visitors.md), #1486). Read `join_policy` on `GET /v1/communities/{community}`, for a member and for someone who is not one.

| Route | What it does |
| ----- | ------------ |
| `PATCH /v1/communities/{community}/join-policy` | Change who can join (owner and admins). Body: `join_policy`. Never for a personal community (400) |
| `POST /v1/communities/{community}/membership` | Join an open community by yourself, as a member. 201 with the community as a member sees it. 422 with a message when the community is by invitation, you are a member, or a manager removed you ("You cannot join this community by yourself. Ask its managers for an invitation."). Needs a verified address (403 without). Limited to 10 a minute per person |
| `GET /v1/communities/{community}/join-blocks` | The people whom a manager removed (owner, admins, moderators with `manage_members`) |
| `DELETE /v1/communities/{community}/join-blocks/{member}` | "May join again": the person can join by themselves again (same managers). 204, or 404 without a block |

All four need an account and the ability `communities:write` (`communities:read` for the list).

## Indexing

A shared community lets search engines index its public spaces, or not ([ADR 0044](../adr/0044-public-spaces-for-visitors.md), "Indexing", #1487). It is off by default. Members read `indexing_enabled` on `GET /v1/communities/{community}`; it is not part of the public shape.

| Route | What it does |
| ----- | ------------ |
| `PATCH /v1/communities/{community}/indexing` | Let search engines index the public spaces, or not (owner and admins). Body: `indexing_enabled` (a boolean). Never for a personal community (400). Needs an account and the ability `communities:write` |

The setting only matters on an instance that allows indexing (`ROBOTS_ALLOW_INDEXING`). The decision for each page is in its `X-Robots-Tag` header and its meta tag. The API itself is never indexed.

## Notifications, follows and levels

The notifications of the current member, in every community ([ADR 0042](../adr/0042-notifications.md), #1474, #1475). Only a member of the community who can view a space has follows and levels there: a visitor and a signed-in person who is not a member get 403. A notification of another member gives 404. None of these abilities is in the default set of a token.

| Route | What it does |
| ----- | ------------ |
| `GET /v1/notifications` | The list. `level=1` (default) is "Needs you", `level=2` is "Following" (the last 14 days, no `older`), `state` (open, read, all), `community`, `older`. Each notification has `reason` (`wrote_post`, `wrote_comment`, `mentioned`, `follows_thread`, `space_everything`, `started_course`), `follow_reason` (`wrote`, `replied`, `manual`) and `items_count`. Needs `notifications:read` |
| `GET /v1/notifications/count` | The number of the bell (`data.count`). It never counts "Following". `data.messages` is the number of conversations with new messages, in every community, for the title of the browser tab |
| `POST /v1/notifications/seen`, `PUT /v1/notifications/{notification}/read`, `POST /v1/notifications/{notification}/unread` | Seen, read, undo read. Need `notifications:write` |
| `POST /v1/notifications/caught-up` | "I am caught up": every open "Following" notification is read, in every community or in the one that the optional `community` (cuid) names. It never touches "Needs you". With a session, it also forgets the date of the "Catch up" card. Answers `data.marked` |
| `GET`, `PUT`, `DELETE /v1/communities/{community}/spaces/{space}/posts/{post}/follow` | The state of the thread for the member (`state`: `following`, `muted`, `stopped` or null; `reason`: `wrote`, `replied`, `manual`), "Follow", "Stop following" (the state becomes `stopped`: writing again does not follow the thread again) |
| `PUT /v1/communities/{community}/spaces/{space}/posts/{post}/mute` | "Mute this thread": nothing reaches the member from the thread, a mention included, and their open notifications about it are deleted |
| `GET`, `PUT`, `DELETE /v1/communities/{community}/spaces/{space}/messages/{message}/follow`, `PUT .../messages/{message}/mute` | The same for a chat thread (a first-level message; a reply has no thread: 422) |
| `GET /v1/communities/{community}/notification-levels` | The levels of the member for every space of the community that they can view, in one call, for a settings screen |
| `GET`, `PUT`, `DELETE /v1/communities/{community}/spaces/{space}/notification-level` | The level for one space (`level`: `all` "Everything", `mine` "Only what is for me", `none` "Muted"; `chosen`; `default`), choose it (`level`), go back to the default. Muted gives nothing from the space, a mention included |
| `GET /v1/communities/{community}/catch-up?since=YYYY-MM-DD` | The data of the "Catch up" card ([ADR 0048](../adr/0048-where-i-am.md), #1476): at most 5 lines (up to 2 `needs_you`, up to 2 `announcement`, 1 `thread`), each with `kind`, `title`, `excerpt`, `space`, `author`, `date` and `target`, and `has_more`. `since` is required, not in the future, at most 366 days back. Members only. Needs `notifications:read` |
| `PUT /v1/communities/{community}/spaces/{space}/visit` | The member visited the space: its dot in the navigation goes. Members who can view the space only (403 otherwise). Needs `notifications:write` |
| `PUT /v1/user/time-zone` | Set the time zone of the account (`time_zone`, an IANA name; an unknown name is a validation error). Only the member sets and reads it. Needs `user:write` |
| `PUT /v1/user/notification-settings` | The switch "Show pop-ups" (`popups`, boolean). Needs `notifications:write` |

The posts and the first-level chat messages carry `follow_state` (`following`, `muted`, `stopped`, or null). The space carries `default_notification_level` (`all`, `mine` or null) for the people who manage the spaces only; they set it with `PATCH /v1/communities/{community}/spaces/{space}` (`all` for a discussion space only, never a chat space; `none` is not allowed). `POST .../posts` takes `notify_now` (a boolean) to skip the 5 minutes before the members at "Everything" are told, for the people who can moderate the space (403 for the others).

## Keep it current

Each pull request that adds or changes an endpoint adds or updates the requests here, in the same pull request:

1. File name: `<Area> - <Operation>.bru`, and `<Area> - <Operation> - <Case>.bru` for an error case. Example: `Spaces - Create.bru`, `Spaces - Create - Invalid type.bru`.
2. One request for the success case, and one for each main failure (validation, permission) when it shows something the success case does not.
3. Bodies use the field names of the API and realistic values. The fields are snake_case. A few older request fields are camelCase (`turnstileToken`, `includeToken`), see [ADR 0011](../adr/0011-rest-api-design-guide.md).
4. Never commit a secret: credentials come from `.env` through `{{process.env.*}}`.

`tests/Feature/BrunoCollectionTest.php` fails when an `/api/v1` route has no request here, or when a request targets a route that does not exist.
