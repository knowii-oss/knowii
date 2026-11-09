# 0023. Sign-in links by email, used once, confirmed with a button

- Status: Accepted
- Date: 2026-11-09
- Issue: #703

## Context

Members asked to sign in without their password (#703). Packages exist (passwordless login, magic link libraries), but the platform already has the pieces: Fortify, the cache, queued mail. A sign-in link is a password sent by email, so it must be short-lived, single use, and must not weaken two-factor authentication.

## Decision

1. The login page offers "Email me a sign-in link". The answer is always the same ("If an account has this address, we sent it a link"), so the form never tells who has an account.
2. The link holds a random token of 64 characters. The cache keeps its SHA-256 hash with the user for 15 minutes. `Cache::pull()` reads and deletes it, so it works once.
3. Opening the link shows a page ("Sign in as ...?") with a button. Only the POST of the button signs in: mail scanners open links, and a link that signed in on GET would be used up before the member clicks it.
4. For an account with two-factor authentication, the link replaces the password only: the member goes to the code challenge of Fortify.
5. Using a link marks the email address as verified: the member reads this inbox.
6. Asking for links and using them is rate limited (5 per minute per address and IP). The email is queued.
7. No new dependency.

## Consequences

1. The cache must be shared by the web servers (Redis or the database in production), else a link made on one server is unknown to another.
2. A member who can read the inbox of an account can sign in to it (like the password reset).

## Alternatives considered

1. A package (grosv/laravel-passwordless-login, cesargb/laravel-magiclink): a new dependency for a few lines, and most sign in on GET.
2. Signed URLs (`URL::temporarySignedRoute`): they cannot be used only once without a store anyway.
3. One-time codes typed on the login page: one more step for the member; can come later for mobile apps.
