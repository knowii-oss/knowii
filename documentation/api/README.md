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
| `communities:write` | Create, change and delete communities, spaces, members, invitations   |
| `posts:read`        | Read posts, comments and reactions, preview Markdown                  |
| `posts:write`       | Write posts and comments, pin, react, upload images                   |
| `chat:read`         | Read the messages and threads of chat spaces, mark them as read       |
| `chat:write`        | Write, edit and delete messages, react to them                        |
| `messages:read`     | Read your direct messages, mark them as read (not in the default set) |
| `messages:write`    | Start conversations, write, edit and delete direct messages, react    |
| `pages:read`        | Read the pages of page spaces                                         |
| `pages:write`       | Create, change, reorder and delete pages (owner, admins, moderators)  |
| `resources:read`    | Read resources, their comments and reactions                          |
| `resources:write`   | Add resources, comment and react on them                              |
| `resources:create`  | Add resources, nothing else (the browser extension, ADR 0018)         |
| `groups:read`       | Read access groups and their members                                  |
| `groups:write`      | Manage access groups and their members (a sync from another system)   |

## Keep it current

Each pull request that adds or changes an endpoint adds or updates the requests here, in the same pull request:

1. File name: `<Area> - <Operation>.bru`, and `<Area> - <Operation> - <Case>.bru` for an error case. Example: `Spaces - Create.bru`, `Spaces - Create - Invalid type.bru`.
2. One request for the success case, and one for each main failure (validation, permission) when it shows something the success case does not.
3. Bodies use the field names of the API and realistic values. The fields are snake_case. A few older request fields are camelCase (`turnstileToken`, `includeToken`), see [ADR 0011](../adr/0011-rest-api-design-guide.md).
4. Never commit a secret: credentials come from `.env` through `{{process.env.*}}`.

`tests/Feature/BrunoCollectionTest.php` fails when an `/api/v1` route has no request here, or when a request targets a route that does not exist.
