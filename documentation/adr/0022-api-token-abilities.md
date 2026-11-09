# 0022. API token abilities, read and write per area, checked on every route

- Status: Accepted
- Date: 2026-11-09
- Issue: #689

## Context

Jetstream lets members create personal API tokens with "permissions" (Sanctum abilities). The list (`community:create`, `resource:read`...) was never checked: every token could do everything that its owner can. Members need tokens for scripts and for the browser extension of [ADR 0018](0018-content-capture.md), which must only add resources.

## Decision

1. The abilities are read and write per area, plus one narrow ability: `user:read`, `user:write`, `communities:read`, `communities:write`, `posts:read`, `posts:write`, `resources:read`, `resources:write`, `resources:create` (add resources only).
2. Every authenticated route of `routes/api.php` names the abilities that it accepts with the `ability:` middleware (`RequireTokenAbility`, any of the list). `ApiTokenAbilitiesTest` fails when a route has none.
3. A request with a personal access token passes only with one of the abilities (403 otherwise). The web session of the member is not limited (Sanctum's transient token).
4. A new token gets the four read abilities by default; the member adds more.
5. The token of the API login (`includeToken`, 12 hours) has every ability: the password and the second factor stand behind it (#1256).
6. Members manage their tokens on the "API tokens" page (user menu): create (the token shows once), change the abilities, delete.

## Consequences

1. A new API route needs an `ability:` middleware, and a new area needs its abilities here, in `JetstreamServiceProvider`, in `API_TOKEN_PERMISSIONS` and in the API README.
2. Personal tokens expire after 30 days, 90 days (the default) or 1 year, or never, as the member chooses when creating them (#1267, Sébastien, 2026-11-09). Sanctum refuses an expired token. The login token of `POST /api/v1/login` lasts 12 hours.
3. Abilities limit what a token can do, never more than its owner: the policies still apply.

## Alternatives considered

1. One ability per route: precise, but a list of 50 switches that no member would read.
2. Read and write only: the browser extension would get the right to change everything.
3. OAuth clients (Passport): needed for third-party apps, not for the own tokens of a member.
