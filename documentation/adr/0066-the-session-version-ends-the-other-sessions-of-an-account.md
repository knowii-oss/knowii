# 0066. The session version ends the other sessions of an account

- Status: Accepted
- Date: 2026-11-24
- Issue: none
- Pull request: none
- Extends: [0059](0059-the-first-proof-of-an-address-takes-the-account-over.md) (point 4: the version of the sessions)

## Context

Each account has a version of its sessions ([ADR 0059](0059-the-first-proof-of-an-address-takes-the-account-over.md)): a session holds the version of its sign-in, and the middleware `EndOutdatedSessions` ends a session that holds another number, whatever the session driver. A takeover of an account adds 1 to the version.

A member who changes or resets the password, who presses "Log out other browser sessions", or who changes the second factor, expects that the other devices of the account are signed out. The application needs one rule for these changes, the same on every route where a session signs in (the pages, the routes of Fortify and Jetstream, the API of the session, the authorization of the live channels) and for every session driver.

## Decision

1. One action ends the other sessions of an account: `App\Contracts\Auth\EndsOtherSessions` (bound to `App\Actions\Auth\EndOtherSessions`). It adds 1 to `users.session_version`, in one write with the values that its caller gives. Every other session of the account then ends at its next request, on every route.
2. A change of the password (`App\Actions\Fortify\UpdateUserPassword`) and a reset of the password (`App\Actions\Fortify\ResetUserPassword`) call it with the new password: the new password and the new version are one write.
3. "Log out other browser sessions" (`App\Http\Controllers\OtherBrowserSessionsController`) calls it too, in one transaction with the steps of Jetstream: a new hash of the same password, and the removal of the rows of the other sessions of the `database` driver. So the button ends every other session with every session driver.
4. The second factor calls it when it is confirmed (`ConfirmTwoFactorAuthentication`), when it is turned off while it was on (`DisableTwoFactorAuthentication`), and when its recovery codes are made again while it is on (`GenerateNewRecoveryCodes`), in one transaction with the write of Fortify. These three also write the remember token of the account again: a "Remember me" cookie of before signs nobody in, so every other device signs in again, with the code when the second factor is on. A set-up that is cancelled and new codes before the confirmation change nothing for a sign-in: they end no session.
5. "Enable" (`EnableTwoFactorAuthentication`) never replaces a second factor that is on: it stores a secret only while the account has none, and its `force` value makes a new secret only before the confirmation. So "Enable" changes nothing for a sign-in and ends no session. A person who wants a new secret turns the second factor off and sets it up again, and both steps end the other sessions.
6. The change is written only when the account still has the version that the request read. The action locks the row of the account, compares its version with the version of the model, runs the other write of its caller, and counts the row that its own write changed, in one transaction. When the account changed elsewhere after the request read it, nothing is written and the change fails:
   1. A request with a session of the account holds a session of before that other change. The session ends at once, as it would at its next request, and the sign-in page says "Your account changed on another device. Sign in again." (a request that expects JSON gets a 401 with these words).
   2. A reset by mail has no session: its form says "Your account changed during this request. Nothing was saved. Please try again.", and the link of the mail still works.
7. The session of the request that made the change goes on: the action stores the new version in it, as a sign-in does (`App\Listeners\StoreSessionVersion`), and the password of now for the checks of the password of the session, on the pages and on the API. After a change of the password the session also gets a new session id, as after a sign-in: the id of before signs nobody in. A reset by mail has no session of the account: the person signs in with the new password.
8. The "Remember me" cookie of the present browser ends with the others after a change of the password (the cookie holds a value of the password of before) and after a change of the second factor (the remember token is written again): its session goes on, and its next sign-in asks for the password again. After "Log out other browser sessions" the present browser gets a new cookie, as Jetstream does it.
9. `EndOutdatedSessions` runs before the sign-in check of every route that has one: it is in the `web` group and in the `api` group. `tests/Feature/OtherSessionsEndTest.php` reads the routes from the router and fails for a route with a sign-in check and without the middleware before it. On the API, the check of the password of the session of Sanctum comes before it: a session whose password changed gets the same 401 from that check.
10. The confirmation of the password holds for 15 minutes (`auth.password_timeout`, `AUTH_PASSWORD_TIMEOUT`, 900 seconds): after it, the settings of the second factor ask for the password again. An empty value, zero and a value that is no number of seconds keep the 900 seconds.
11. The browser forgets the pages of a member when the session ends ([App\Support\BrowserHistory](../architecture/backend.md)). After the sign-out, the page data of a member cannot be read from the history of the browser, on https:
    1. A page asks the browser to encrypt the pages that it keeps in its history (`INERTIA_ENCRYPT_HISTORY`, on by default), only where a browser can do it: for a request over https, or to `localhost`, `127.0.0.1` or `[::1]`. On plain http for another host name the pages are not encrypted, so that the links keep working.
    2. The server tells the browser to forget the key on the page after a sign-out, the deletion of the account, "Log out other browser sessions", the end of a session and a takeover.
    3. The web application forgets the key by itself, in every tab: it keeps the cuid of the account of its last page (in memory and in the `sessionStorage` of the tab), and clears the history before it shows a page for another account or for no account (`apps/knowii/src/history-account.ts`). So a tab learns it at its first navigation, whatever ended the session: a sign-out in another tab, a session that expired, a change on another device.
    4. The limit that stays: a tab that makes no request keeps what it shows, and its history, until its next navigation. And without https (or a loopback name) nothing is encrypted. The application decides from the scheme that it sees: behind a reverse proxy it must see https (`TRUSTED_PROXIES`, and `X-Forwarded-Proto` of the proxy).

## Consequences

1. A new change that must sign the other devices of an account out calls `EndsOtherSessions`, in the write of the change.
2. Every update of a `users` row by a model checks the version that the model read (ADR 0059, point 14). So a request of another session that was under way during the change writes nothing after it, and a change of that request that calls `EndsOtherSessions` fails and ends its session (point 6).
3. A form that is sent two times at the same moment by one browser can end the session of that browser: the second request finds an account that changed. The person signs in again.
4. With the `database` session driver, the list "Browser sessions" of the profile shows the row of a session that ended until that browser makes its next request, or until the row expires.
5. The words of the screens do not change, except the two sentences of point 6: the other devices get the sign-in page at their next request.

## Alternatives considered

1. The check of the password of the session (`AuthenticateSession`) on every route: it is a middleware per route, it compares a value that each guard keeps under its own name, and it says nothing about the second factor. The version is one number, checked in one place.
2. Deleting the rows of the `sessions` table of the account: only the `database` driver has rows.
3. A validation error at a field of the form for a change that was not written, with the session left as it is: each form has other fields, three of the callers have none, and the session would end at the next request without a word.
4. A new "Remember me" cookie for the present browser after a change of the password or of the second factor: not done. The session goes on, and one more sign-in after it is a small cost.
