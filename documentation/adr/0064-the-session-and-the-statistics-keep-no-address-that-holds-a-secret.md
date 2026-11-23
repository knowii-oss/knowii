# 0064. The session and the statistics keep no address that holds a secret

- Status: Accepted
- Date: 2026-11-23
- Issue: none
- Pull request: none
- Extends: [0062](0062-invite-links-a-shared-link-to-join-a-community-with-set-access-groups-and-tags.md) (points 14 and 17: the session middleware and the statistics of the pages of an invite link)
- Uses: [0023](0023-sign-in-links.md) (sign-in links), [0045](0045-openid-connect-sign-in.md) (the callback of the provider), [0058](0058-a-change-of-the-email-address-applies-after-its-confirmation.md) (the link of a change of address)

## Context

Some addresses of the application hold a secret. The sign-in link (`/login/link/{token}`), the password reset (`/reset-password/{token}`) and the invite link (`/invite/{token}`, and its API read `/api/v1/invite-links/{token}`) hold a token. The link of the address check, of a change of address and of an invitation holds a signature. The callback of the OpenID Connect provider holds a one-time code in its query. An address with a character too many, a path too long, a bent path or capital letters reaches the fallback route, and holds the same secret.

The framework keeps the address of every full GET as the previous address of the session (`_previous.url`), and an Inertia visit can do the same (`inertia.store_previous_url`). The statistics (#1316) need the address of each page that a person visits, the first load and each Inertia visit after it. The application decides in one place which addresses hold a secret, so that the session, the statistics and a new route all follow the same rule. A list of route names in each place would miss the next signed route.

## Decision

### One rule

1. `App\Support\SecretAddresses::holdsASecret(Request)`. An address holds a secret when its route is signed, has a token parameter, or is named in the list:
   1. the matched route has the middleware `signed` (also `signed:relative`) or a class of `ValidateSignature`, as the router resolves it (`Router::gatherRouteMiddleware()`, also through a group);
   2. the matched route has a parameter of `SECRET_PARAMETERS` (`token`, `inviteLinkToken`, `hash`);
   3. the route name is in `SECRET_QUERY_ROUTES` (`oidc.callback`): its query holds the secret;
   4. the matched route is the fallback route (`$route->isFallback`): an address that no route matches answers 404, and the 404 of an address that no route matches sends no page view and never becomes the previous address. This covers a bent address (`/login//link/<token>`, `/reset-password;/<token>`, `/index.php/login/link/<token>`, a double slash before a signed path);
   5. the path, without regard to the case, matches `PATHS`: the static part of each route of 1 to 3 with `/*` (`invite/*`, `api/v1/invite-links/*`, `login/link/*`, `reset-password/*`, `email/verify/*`, `user/email-change/*`, `community-invitations/*`), and the path of each route of 3 (`auth/oidc/callback`). This covers the addresses that reach no route. `/invite`, the page of an invite link, holds no token and does not match.
2. A new signed route, or a new route with a parameter of `SECRET_PARAMETERS`, is covered without a change. The guard test (`tests/Feature/SecretAddressesTest.php`) lists the parameter names of every route, of every method: a new name fails until it goes in `SECRET_PARAMETERS` or in the public names of the test. It computes the routes that hold a secret from the router, without the rule, compares them with the rows of its dataset and with `SecretAddresses::routeHoldsASecret()`, and checks that `PATHS` covers the full path of each of them. No file of `app/` checks a signature by hand (`hasValidSignature()`, `hasValidRelativeSignature()`): such a route would escape the rule, so the test fails on it.
3. Three places read the rule: the session middleware, the statistics, and through them the page `Error` that production renders at the address of the request.

### The session

4. The session keeps no address that holds a link token or a signature as its previous address. `StartSessionWithoutTokenAddresses::storeCurrentUrl()` skips every request for which `holdsASecret()` is true (the paths of ADR 0062 moved into the rule), and so does `HandleInertiaRequests::shouldStoreCurrentUrl()` for an Inertia visit, also when `inertia.store_previous_url` is on. `AuthenticateIfPresent` keeps no such address as the intended address of a visitor, and the redirect of a refused visitor to the sign-in page (`bootstrap/app.php`) comes without it. A failed form keeps no `token` in its old input (`dontFlash` in `bootstrap/app.php`): the reset page reads its token from its props. So `back()` after a failed form of such a page reads the `Referer` header, as `UrlGenerator::previous()` does first.
5. The session is encrypted by default: `config/session.php` turns it off only for an explicit false value of `SESSION_ENCRYPT` (an empty value keeps it on), and both `.env` examples set `true`. The table `sessions` then holds no value of a session in clear: the intended address that a visitor keeps when a signed link sends them to the sign-in page (`redirect()->guest()`, which the sign-in needs to come back), the flash data, a future key. An operator can set `false`: point 4 still holds, and the intended address is then the one value that holds such an address.

### The statistics

6. The application loads no script of another host for the statistics. The script tag of `app.blade.php` is gone. `script-src` of the Content Security Policy no longer names the proxy; `connect-src` keeps its event address (`/api/v1/event`), the one address that the proxy needs.
7. The shared Inertia prop `statistics` (`App\Support\Analytics::pageProp()`) is `{endpoint, domain, url}` or null. It is null when the instance sets no statistics (`PLAUSIBLE_DOMAIN` and `PLAUSIBLE_PROXY_URL`), on a page of `Analytics::PAGES_WITHOUT_STATISTICS` (the pages of an invite link, ADR 0062 point 17), and at an address that holds a secret. The prop is read when the response is built, so the page `Error` at such an address gets null too. The statistics never see the address of a page that holds a link token or a signature.
8. A page view holds the origin and the path of the page, and of the query only the campaign keys: `utm_source`, `utm_medium`, `utm_campaign`, `utm_content`, `utm_term`, `ref` and `source` (`Analytics::CAMPAIGN_KEYS`), each with a text value. Never another key, never the fragment.
9. `apps/knowii/src/statistics.ts` (`startPageViews()`, called once in `main.tsx` before the application starts) sends one page view per visit of a page. It listens to the event `navigate` of Inertia (`router.on('navigate')`): Inertia fires it once for the first load of a document (`initial`), for a visit to another address (`visit`), and for back and forward (`history`), and not for a visit that replaces the address of the page: a partial reload, a reload of the same page, a poll and a live update send nothing. It posts `{name: 'pageview', url, domain, referrer}` to `endpoint` with the content type `text/plain` (no preflight request), as the Events API of Plausible documents, without credentials. `url` is the one of the prop, never the address of the browser. `referrer` is `document.referrer` on the first page view of a document only, and only when it is of another site; else null. Nothing is sent when the prop is null or missing, and nothing when `navigator.webdriver` is true. At an address that holds a secret the prop `newsletter` is off too, so the footer loads no bot check of another host there.
10. The `Referer` header to another host stays as it is: the application answers `Referrer-Policy: origin-when-cross-origin`, so another host gets the origin only, never the path or the query. `no-referrer` is not set on these pages: point 4 makes `back()` after a failed form read the `Referer` header, and the first-party requests of the API need it too.

### The queue

11. A queued mail that holds a link is encrypted in the queue. `App\Mail\MagicLink` (the token), `App\Notifications\ResetPasswordLink` (the token of the password reset) and `App\Mail\ConfirmNewEmailAddress` (the signed link) implement `ShouldBeEncrypted`, so the rows of `jobs` and `failed_jobs` hold neither the token nor the link. The other mails and notifications build their link when they are sent, or hold none.

## Consequences

1. A new route whose address holds a secret uses a parameter of `SECRET_PARAMETERS` or the signature middleware, or goes in `SECRET_QUERY_ROUTES`, and a pattern of its path goes in `PATHS`. The guard test fails until it does, and until the route has its rows in the dataset of the session and of the statistics.
2. A change of `SESSION_ENCRYPT` ends the open sessions: the old rows cannot be read in the other way.
3. The statistics count one page view per visit of a page, with the address that the server cleaned. The script extensions (outbound links, file downloads) are not counted.
4. `SELF_HOSTING.md` lists `SESSION_ENCRYPT`, and says that the proxy of the statistics needs only `/api/v1/event`.

## Alternatives considered

1. A list of route names in the session middleware and in the statistics: the next signed route, or the next route with a token, would miss one of them.
2. A marker middleware on each route that holds a secret: one more thing to forget on a new route.
3. A statistics script of another host, left out on the pages of the list only: the application would not choose the address and the referrer of the next page views, which the server cleans here (points 7 to 9).
4. The npm package of the Plausible tracker, with its own page views off and a change of each request: a new dependency for one POST of four values.
5. `Referrer-Policy: no-referrer` on the pages of the list: see point 10.
6. The intended address of the session kept in clear, with a list of its own: the encryption of the whole session covers it and every future key.
