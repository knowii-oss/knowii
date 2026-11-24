# 0068. The cookies belong to one host

- Status: Accepted
- Date: 2026-11-24
- Issue: none
- Pull request: none
- Uses: [0055](0055-instance-values-come-from-configuration.md) (the start check of production), [0044](0044-public-spaces-for-visitors.md) (the intended address of a visitor), [0022](0022-api-token-abilities.md) (the tokens of the API)

## Context

An instance has one host name (`APP_URL`), and sometimes a second one (`TRUSTED_HOSTS`). Other services often have a host name under the same domain: a community site, an account service, a blog. The application sets three cookies: the session cookie, `XSRF-TOKEN` and the cookie of "Remember me". A cookie with a domain belongs to that domain and to every host under it. A cookie without a domain belongs to the host that set it.

The framework keeps the address to open after a sign-in in the session (the intended address). For a GET it is the address of the request. For another method it is the `Referer` header of the request.

The pages of the application have the same origin as the API. A client on another origin signs in with a token (ADR 0022).

## Decision

1. The session cookie belongs to the one host of the instance (`__Host-` prefix, no domain). The two example files and `SELF_HOSTING.md` leave `SESSION_DOMAIN` empty. Production names the cookie `__Host-knowii_session`, with `SESSION_SECURE_COOKIE=true` and `SESSION_PATH=/`: a browser accepts a cookie with this prefix only from the host of the page, over https, without a domain and with the path `/`. Development keeps `knowii_session`: plain http cannot use the prefix.
2. `XSRF-TOKEN` and the cookie of "Remember me" take the domain of the session configuration, so they have no domain either. `XSRF-TOKEN` keeps its name, because the HTTP clients of the front end read it by that name. The cookie of "Remember me" keeps the name that the framework gives it (`remember_web_<hash>`): the session guard builds the name in its code (`SessionGuard::getRecallerName()`) and reads no configuration for it. A `__Host-` name would need an own guard class in the place of the one of the framework.
3. In production, the instance answers only with such a session cookie. `App\Http\Middleware\RefuseUnconfiguredInstance`, the place of the start check of ADR 0055, answers `503` with a text that names the two values while the session domain has a value or the name of the session cookie does not start with `__Host-`. Every address answers so, the health address too, so a deployment that waits for a healthy application fails. The console commands still run.
4. One switch for another layout: `SESSION_ALLOW_DOMAIN_COOKIE=true` (`session.allow_domain_cookie`) turns the check of point 3 off. Only the value true does. `SELF_HOSTING.md` says what the operator then accepts.
5. The API allows requests with cookies from its own origin only: `supports_credentials` is `false` in `config/cors.php`. The API still answers every origin (`Access-Control-Allow-Origin: *`), for a client that sends a token in the `Authorization` header.
6. After a sign-in, the application goes only to an address of its own host names. The middleware `App\Http\Middleware\LandOnlyOnOwnHostNames` keeps the intended address of the session only when it is an address of the application: a path that starts with one `/`, or an http or https address whose host is one of the own host names (`AnswerOnlyForOwnHostNames::names()`) or the host of the request, in any letter case and with any port. It removes any other value: another host, another scheme, a text with a user name before the host, a backslash, a space or a control character, and a value that is not a text.
7. The middleware checks the session before the request goes on and after the answer. It is in the `web` group and in the priority list right after `StartSession` (`bootstrap/app.php`), so it runs before the sign-in check, the check of the email address and the confirmation of the password, which write the value, and before every answer that reads it: the answers of Fortify (the form, the second factor, the confirmation of the password), the sign-in link, the sign-up link, the callback of the provider and the address check. A test checks the order on every route of the `web` group.

## Consequences

1. An instance that updates sets `SESSION_COOKIE=__Host-knowii_session` and an empty `SESSION_DOMAIN` first: without them production answers `503`. The new name ends every open session once.
2. With a second host name, each name has its own cookies. A member who signs in on one name is not signed in on the other.
3. A script of another origin cannot call the API with the cookies of a member. It uses a token.
4. A reader of the intended address needs no check of its own. A new way to sign in on a route of the `web` group is covered.
5. The intended address is always one of: the address of a GET request, an address that the code builds with `route()`, or a `Referer` of an own host name.

## Alternatives considered

1. Keep `SESSION_DOMAIN` as the host of the instance: the cookie then also belongs to every host under that name, and the `__Host-` prefix is not possible with a domain.
2. A warning in the log, or a check in `app:health`, in place of the `503`: an instance would run for a long time with the other values. The start check of ADR 0055 already stops a deployment for the values that an instance needs.
3. A list of allowed origins for the API with cookies: no client needs it today. The pages have the same origin as the API.
4. An own `Redirector` whose `intended()` ignores an address of another host: it covers only the readers that call `intended()`, and not a reader of the session. The middleware removes the value from the session, for every reader.
5. An own session guard, to give the cookie of "Remember me" the `__Host-` prefix: it would copy the way the framework builds its guard. The cookie already has no domain.
