# 0067. The tries of the current password count for what signed the request in

- Status: Accepted
- Date: 2026-11-24
- Issue: none
- Pull request: none
- Amends: [0043](0043-delete-content-with-the-account.md) (point 6: the limiter of the deletion), [0052](0052-account-deletion-after-a-new-sign-in.md) (point 9: the code of the deletion)

## Context

Seven places ask a signed-in person for the current password: the confirmation of the password (`POST /user/confirm-password`), the change of the password (`PUT /user/password`), "Log out other browser sessions" (`DELETE /user/other-browser-sessions`), the deletion of the account (the page and `DELETE /api/v1/user`), a change of the email address in the profile form, and the transfer of the ownership of a community. Each one must limit the tries.

A count per account is one number for every session and every token of the account: the tries that one browser or one API token makes then close these forms in every other browser of the member, also the two forms that sign the other devices out. The count must belong to what made the tries.

A count starts again only with a new proof of the person: the password, a sign-in link, the provider, the second factor. A session that a sign-in made has such a proof behind it, so it has its own count. A session that the "Remember me" cookie made, and an API token, have none of their own: the cookie signs in without a password, and the API tokens page makes a token for a session without the password. So each of these two kinds has one count per account.

## Decision

1. A wrong current password counts for what signed the request in (`App\Support\RequestCredential`), in one of three counts, and never for the account as a whole:
   1. A session that a sign-in made (the form with the password, the API sign-in, a sign-in link, the provider, the code of the second factor) has its own count (its id, hashed in the key of the counter).
   2. The sessions that the "Remember me" cookie made share one count per account. A session knows how it began: `App\Listeners\StoreSessionVersion` writes `RequestCredential::RECALLED_SESSION_KEY` into the session when the cookie signs the request in (the guard says `viaRemember()`), and every other sign-in removes it.
   3. The API tokens of an account share one count per account.
2. One counter for the seven places together (`App\Support\PasswordTries`), for each of these counts: `Constants::PASSWORD_TRIES` (5) wrong passwords in `Constants::PASSWORD_TRIES_MINUTES` (15) minutes. The 15 minutes start at the first wrong password.
3. A try is counted first, then the password is checked: the counter goes up in one step of the cache before the hash is read, so tries that run at the same time cannot pass the limit together. A right password gives its own place back, never the place of an earlier wrong try. So only a wrong password counts, and a member who types the right one is never slowed down.
4. A session that makes the fifth wrong try of its count ends (`App\Support\EndedSession`): the account is signed out in that browser, the session is invalidated, the "Remember me" cookie of that browser is forgotten, and the browser gets the sign-in page with "Too many wrong passwords. Sign in again.". The form of a page gets the redirect. A request that expects JSON gets a 401 with these words, not a 429: the session is gone, so waiting changes nothing, and a new sign-in does. A session of the "Remember me" cookie whose shared count has no tries left ends at its next try in the same way, also with the right password: the person signs in again, and that session has its own count.
5. When a session ends for wrong passwords, the "Remember me" token of the account is written again: no "Remember me" cookie of before signs in after it, in any browser. The sessions of the account that are open go on.
6. The API tokens of an account that made five wrong tries together are refused on the places that ask for the password until the 15 minutes are over, each token of the account, also a new one: 429, the JSON error of [ADR 0011](0011-rest-api-design-guide.md), with `Retry-After`. No token is deleted, and the other routes of the tokens work. The count of the tokens never ends or slows down a session.
7. The limits of the two routes of the API that ask for the password count per credential: `account-deletions` (3 requests a minute) and `ownership-transfers` (5 requests a minute) are keyed by the session or by the token (`RequestCredential::keyOf()`), not by the account. The requests of one token never close the route for the session of the member.
8. The code of two-factor authentication of the deletion of the account has one count per account for the page and the API together (`App\Support\AccountDeletion::checkSecondFactor()`): 3 wrong codes a minute. A code is counted first and checked after, as a try of the password, so codes that are sent at the same time cannot pass the limit together; a right code gives its own place back, and a request without a code takes none. The code is asked only after the right password, or after the proof of a new sign-in. Since then, this one count per account holds every place that checks a code (the challenge of the sign-in, the API sign-in, the deletion, the transfer of a community; `App\Support\SecondFactorTries`): 5 wrong codes a minute and 10 an hour.
9. The counter of the tries is never read inside a database transaction that can be rolled back: with the `database` cache store the count would go with it. "Log out other browser sessions" checks the password before its transaction.

## Consequences

1. A member who types the password wrong five times in 15 minutes in one browser signs in again in that browser. The open sessions of the other browsers of the member go on; their "Remember me" cookies sign in no more, so each of these browsers asks for the password at its next sign-in.
2. A new place that asks for the current password calls `PasswordTries::check()` with the check itself, outside a transaction.
3. The count of a session that a sign-in made ends with the session: a new sign-in with the password, or another proof, starts at zero. A new session of the "Remember me" cookie and a new API token start no new count.
4. A member whose session came from the "Remember me" cookie shares the count with the other sessions of that kind of the account: when that count has no tries left, a place that asks for the password ends the session, and the member signs in again. No form of a member who signed in is closed by it.
5. The tokens of an account wait together: the wrong passwords of one script close the two places of the API that ask for the password for the other tokens of the account, for at most 15 minutes.
6. The screens need no "Too many tries" sentence for the password: the sign-in page says it, as a warning. The dialog that confirms the password sends the browser to the sign-in page when its request gets the 401. Each form shows a wrong password under its field, so a member sees each try.

## Alternatives considered

1. One count per account (the rule of before): any credential of the account closes the forms of every session of the member.
2. A count per credential, and a wider count per account on top of it: the count per account brings the same fault back, at a higher number.
3. A 429 for a session that used up its tries, with the session kept: the tries of that session start again after the wait, with no new sign-in.
4. One count for each session and for each token, whatever made it: a count would then start again without a new proof of the person.
5. No try at all for a session of the "Remember me" cookie (a place that asks for the password ends it): each member who is remembered signs in again before each change that asks for the password.
