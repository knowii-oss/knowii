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
| `user:write`        | Change the avatar                                                     |
| `communities:read`  | Read communities, space groups, spaces, members and invitations       |
| `communities:write` | Create, change and delete communities, spaces, members, invitations; leave a community, transfer its ownership |
| `posts:read`        | Read posts, comments and reactions, preview Markdown                  |
| `posts:write`       | Write posts and comments, pin, react, upload images                   |
| `chat:read`         | Read the messages and threads of chat spaces, mark them as read       |
| `chat:write`        | Write, edit and delete messages, react to them                        |
| `messages:read`     | Read your direct messages, mark them as read (not in the default set) |
| `messages:write`    | Start conversations, write, edit and delete direct messages, react    |
| `notifications:read`  | Read your notifications and their count (not in the default set)       |
| `notifications:write` | Mark your notifications as seen or read, undo read (not in the default set) |
| `pages:read`        | Read the pages of page spaces, mark lessons as done, read the progress, read and download course materials, read and answer lesson quizzes |
| `pages:write`       | Create, change, reorder and delete pages and course materials, write and delete lesson quizzes (owner, admins, moderators) |
| `resources:read`    | Read resources, their comments and reactions                          |
| `resources:write`   | Add resources, comment and react on them                              |
| `resources:create`  | Add resources, nothing else (the browser extension, ADR 0018)         |
| `groups:read`       | Read access groups and their members                                  |
| `groups:write`      | Manage access groups and their members (a sync from another system)   |

## Public spaces

A public space (`visibility` public) can be read by anyone on the internet, without an account ([ADR 0044](../adr/0044-public-spaces-for-visitors.md)). The `GET` routes below answer a request with no session and no token, each with the gate of its space, and are limited per IP address (60 requests a minute; a signed-in request is not limited). A chat space is never readable without an account, also when it is public. In any other space they answer 401 without an account, and 403 to a signed-in person who may not read it.

| Route                                                                  | What it returns                              |
| ---------------------------------------------------------------------- | -------------------------------------------- |
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

Without an account, these answers hold the name, the username and the avatar of the authors, and the counts of the reactions. They never hold: a member list, the team, the online members, profile fields, the member count, who reacted, the names of access groups, the moderator rights, the direct message setting, the captured article of a resource, the progress in a course, or the results of a quiz. Everything else needs an account: who reacted, chat messages, online members, member search, quizzes, the download of a course material (the list is public, the download is not), the navigation of the community, and every write.

A request with a token still needs the ability of the route (`posts:read`, `pages:read`, `resources:read`, `communities:read`). A token that is not accepted (unknown, revoked or expired) gets a 401: it is never treated as no token.

## Keep it current

Each pull request that adds or changes an endpoint adds or updates the requests here, in the same pull request:

1. File name: `<Area> - <Operation>.bru`, and `<Area> - <Operation> - <Case>.bru` for an error case. Example: `Spaces - Create.bru`, `Spaces - Create - Invalid type.bru`.
2. One request for the success case, and one for each main failure (validation, permission) when it shows something the success case does not.
3. Bodies use the field names of the API and realistic values. The fields are snake_case. A few older request fields are camelCase (`turnstileToken`, `includeToken`), see [ADR 0011](../adr/0011-rest-api-design-guide.md).
4. Never commit a secret: credentials come from `.env` through `{{process.env.*}}`.

`tests/Feature/BrunoCollectionTest.php` fails when an `/api/v1` route has no request here, or when a request targets a route that does not exist.
