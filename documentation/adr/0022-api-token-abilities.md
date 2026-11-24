# 0022. API token abilities, read and write per area, checked on every route

- Status: Accepted
- Date: 2026-11-09
- Issue: #689

## Context

Jetstream lets members create personal API tokens with "permissions" (Sanctum abilities). The list (`community:create`, `resource:read`...) was never checked: every token could do everything that its owner can. Members need tokens for scripts and for the browser extension of [ADR 0018](0018-content-capture.md), which must only add resources.

## Decision

1. The abilities are read and write per area, plus one narrow ability: `user:read`, `user:write`, `communities:read`, `communities:write`, `posts:read`, `posts:write`, `resources:read`, `resources:write`, `resources:create` (add resources only).
2. Every authenticated route of `routes/api.php` names the abilities that it accepts with the `ability:` middleware (`RequireTokenAbility`, any of the list). `ApiTokenAbilitiesTest` fails when a route has none.
3. A request with a personal access token passes only with one of the abilities (403 otherwise). The web session of the member is not limited (Sanctum's transient token). The API checks the abilities of a token and the email address before it reads the item of the route: `RequireTokenAbility` and `EnsureEmailIsVerified` (`verified`) come before `SubstituteBindings` in the priority list of `bootstrap/app.php`, so a refused request gets the same answer whatever item the URL names. `RequireTokenAbility` also comes before `ThrottleRequests`: a token without the ability uses up no try of the limit of the account.
4. A new token gets the four read abilities by default; the member adds more. An ability that deletes for good is its own choice and is never in the default set: `account:delete` ([ADR 0043](0043-delete-content-with-the-account.md)) and `communities:delete` (2026-11-24). Deleting a community through the API needs `communities:delete` and the name of the community in the body; `communities:write` creates and changes communities and does not delete one.
5. The token of the API login (`includeToken`, 12 hours) has every ability: the password and the second factor stand behind it (#1256).
6. Members manage their tokens on the "API tokens" page (user menu): create (the token shows once), change the abilities, delete.
7. The pages of the application accept the session only: an API token works on the API, with its abilities. Outside `routes/api.php` no route names an ability, so a request whose account comes from a token is refused there with a 403 (the JSON error of [ADR 0011](0011-rest-api-design-guide.md) for a request that expects JSON), whatever the abilities of the token: the pages, the routes of Jetstream (the "API tokens" page too), the invite routes, the routes of a change of the email address and the authorization of the live channels. A page that visitors may read refuses it too: it does not answer as for a visitor. The routes of Fortify (the sign-in forms, the password, the second factor) use the session guard, which never reads a token: a request with only a token is a visitor there, and gets the sign-in page, or a 401 for JSON. The middleware `AcceptOnlyTheSessionOnPages` does it, in the `web` group, right after the sign-in check of the route in the priority list of `bootstrap/app.php`. A request with a session is not changed. `PagesTakeTheSessionOnlyTest` reads every route outside the API that has a sign-in check from the router, and fails when a request with only a token gets another answer than the refusal.

## Consequences

1. A new API route needs an `ability:` middleware, and a new area needs its abilities here, in `JetstreamServiceProvider`, in `API_TOKEN_PERMISSIONS` and in the API README.
2. Personal tokens expire after 30 days, 90 days (the default) or 1 year, or never, as the member chooses when creating them (#1267, Sébastien, 2026-11-09). Sanctum refuses an expired token. The login token of `POST /api/v1/login` lasts 12 hours.
3. Abilities limit what a token can do, never more than its owner: the policies still apply.
4. A route outside `routes/api.php` needs no `ability:` middleware: a token never reaches it. A route for scripts and for other programs belongs in `routes/api.php`.

## Alternatives considered

1. One ability per route: precise, but a list of 50 switches that no member would read.
2. Read and write only: the browser extension would get the right to change everything.
3. OAuth clients (Passport): needed for third-party apps, not for the own tokens of a member.
