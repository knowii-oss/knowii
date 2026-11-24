# 0065. The sign-up makes the account when the link of its mail is opened

- Status: Accepted
- Date: 2026-11-23
- Issue: none
- Pull request: none
- Amends: [0059](0059-the-first-proof-of-an-address-takes-the-account-over.md) (the sign-up form makes no account whose address was never verified), [0057](0057-the-landing-after-the-address-check-travels-in-the-link.md) (the landing of a sign-up travels with the pending sign-up)
- Uses: [0023](0023-sign-in-links.md) (a link opens a page with a button), [0054](0054-email-delivery-the-mail-queue-the-budget-bounces-and-the-one-click-stop.md) (level 0 mail), [0064](0064-the-session-and-the-statistics-keep-no-address-that-holds-a-secret.md) (addresses that hold a secret, queued mails that hold a link)

## Context

Before this decision, the sign-up form made the account at once, signed the person in, and sent the mail of the address check. An address that had an account got the error "The email has already been taken." The password reset form and the form of the sign-in link give the same answer for every address, and the sign-up form gave another one.

The owner decided on 2026-11-23 that the sign-up form gives one answer for every address ("Check your inbox"). With one answer, the request can neither make the account nor sign anyone in: a session for a new address and none for an address of an account is another answer. And the account cannot exist before the proof of its address: an account made by the queue with the password of the form would let the person who filled the form sign in, and a sign-in that works tells that the address was free. So the account is made when the person opens the link of the mail, and the password is chosen there.

## Decision

### The form

1. `POST /register` gives the same answer, the same status and the same words for every address that passes the checks of the form: a new address, the address of an account (verified or not, in any case), the pending address of another account ([ADR 0058](0058-a-change-of-the-email-address-applies-after-its-confirmation.md)) and the address of a deleted account. The words are "Check your inbox: we sent you an email with the next step. If nothing comes in a few minutes, check your spam folder and the address, then try again." (`App\Http\Responses\SignUpRequestResponse`). A form post and an Inertia visit get a redirect to `GET /register` with the flash `status`, which the page shows in the place of the form; a request that expects JSON gets 200 `{"message": ...}`. Nobody is signed in.
2. The form asks for the name and the address, with the bot check of Turnstile and the honeypot (`CreateNewUser::validateForm()`). The address gets the checks of its format and its length only. A field that fails gets its error, which never tells about an account; the honeypot keeps its error (`CreateNewUser::NOT_CREATED_MESSAGE`). The password and the box of the terms are on the page of the link.
3. `App\Http\Controllers\Inertia\RegisteredUserController` is bound in the place of the controller of Fortify. It lowers the address as Fortify does, runs the checks, queues one job and answers. It never reads `users`, fires no `Registered` and signs nobody in.

### The job

4. `App\Jobs\SendSignUpMail` holds the address, the name and the landing of the session (`Landing::fromSession()`, [ADR 0057](0057-the-landing-after-the-address-check-travels-in-the-link.md)), so it is encrypted in the queue. It runs on the queue `default`, with no limiter and no hold.
5. It sends at most one mail of the sign-up per address a minute, both kinds together, whatever the IP address: a cache key with an HMAC of the address in lower case, never the address. A second job in that minute sends nothing. An address gets 10 mails of the sign-up a day, both kinds together, then one more at most once every 3 hours (`App\Support\AccountMailCaps`, [ADR 0071](0071-the-two-factor-code-is-the-last-check-and-a-closed-second-step-sends-a-mail.md)): a mail over the cap is not sent, and the answer of the form does not change.
6. When an account has the address (one select, without regard to the case), it queues the mail "You already have an account on Knowii" (`App\Mail\SignUpAccountExists`) to the stored address of the account. An account whose address was never verified gets the same mail: its sign-in link or its reset is the first proof of [ADR 0059](0059-the-first-proof-of-an-address-takes-the-account-over.md).
7. Else it stores a pending sign-up and queues the mail "Finish your sign-up to Knowii" (`App\Mail\FinishSignUp`) with the link of its token.
8. It ends as done on any error, with one log line that holds the id of the job and the class of the error. No log line holds the address or the token.

### The pending sign-up

9. `App\Actions\Auth\PendingSignUps`: a random token of 64 characters; the cache keeps the entry under `sign-up:` and the SHA-256 of the token. The value holds the name, the address and the landing, encrypted with the key of the application, so a row of the database cache that waits for its removal holds no address in clear. It lives `auth.verification.expire` minutes (60), as the link of the address check, and works once (`Cache::pull`).

### The two mails

10. Both are account mails of level 0 ([ADR 0054](0054-email-delivery-the-mail-queue-the-budget-bounces-and-the-one-click-stop.md), point 1): plain mailables on `default`, never held, with a row of the delivery log and the kind `asked_for`. Both are encrypted in the queue (the link holds the token; every mail holds the address of its recipient) and have the middleware `App\Jobs\Middleware\HideMailErrorText`.
11. The mail of an account holds no token and no signed link: a button to the sign-in page and a link to the reset page.

### The page of the link

12. `GET /register/link/{token}` shows the page "Finish your sign-up" with the address, the name of the form, the password, its confirmation, the box of the terms and the zone of the browser (`App\Http\Controllers\Inertia\SignUpLinkController::show()`). It does not use the link up: mail scanners open links. A link that does not work shows "This link expired or was already used. Sign up again." with a link to the sign-up page. A session of an account that opens the link (GET or POST) goes to the dashboard with the line "You are signed in with another account. Sign out, then open the link again.", and the link stays valid. The path is in `SecretAddresses::PATHS` ([ADR 0064](0064-the-session-and-the-statistics-keep-no-address-that-holds-a-secret.md), point 1).
13. `POST /register/link/{token}` (the limiter `sign-up-link`, 5 a minute per IP address) works in this order: it checks the fields of the page (`CreateNewUser::validateFinish()`; an error keeps the link), uses the entry up, and makes the account with `CreateNewUser::createFromLink()` and the address of the entry, never an address of the request, verified from its first write: the link proved the address. `CreateNewUser::create()`, the method of the contract of Fortify that its own controller calls with the form, throws a `LogicException`: it never makes an account.
14. A unique violation on the address (another sign-up, a provider sign-in or a second link was first) makes no account and sends the person to the sign-in page with "This email address already has an account. Sign in, or reset your password." Any other unique value gets `CreateNewUser::NOT_CREATED_MESSAGE` on the page, and the link is used up. The name of the index tells which value was taken; without a name, the address is checked again (`CreateNewUser::isAddressTaken()`).
15. Then `Registered` (the framework sends no address check to a verified account), the sign-in, a new session (the listener of [ADR 0059](0059-the-first-proof-of-an-address-takes-the-account-over.md) stores the version of the sessions), and the answer of the address check: `Landing::remember()` with the landing of the entry, then the intended address or the dashboard with `?verified=1`. The link of an invitation or of an invite link that the session holds is kept.

### The limits

16. The limiter `sign-up` on `POST /register` has the numbers of the password reset form: 5 a minute per address and IP address, and `Constants::SIGN_UPS_PER_IP_PER_HOUR` (20) an hour per IP address. The 429 is the same for every address. Turnstile stays the main bot check.

### What does not change

17. The sign-up with a provider (the provider proves the address), the accept of an invitation, the takeover of [ADR 0059](0059-the-first-proof-of-an-address-takes-the-account-over.md) (for every account of before, and any later way that makes an account whose address was never verified), the page and the mail of the address check (for such an account), and the `guest` middleware. There is no sign-up in the API: a JSON request to `/register` gets point 1.

## Consequences

1. Every new account of the sign-up form has a verified address from its first write: the address check is no longer sent at the sign-up.
2. The person types the name and the address on the form, then the password on the page of the link, in the same browser or in another one. The landing and an invitation kept in the session work as before.
3. A sign-up that nobody finishes leaves no account: its link ends after 60 minutes, and `cache:prune-expired` deletes the expired entries of the database cache every hour, so the entry goes at most an hour after (another cache store ends it itself).
4. A stranger who signs up first with an address cannot use or see the link: it goes to the mailbox. The owner of the mailbox chooses the name (the page shows the name of the form in a field that they can change) and the password; only the landing of the stranger's session stays, which is a page of a community that the person may read, and joins nothing. The owner's own request in the same minute sends nothing (point 5): they ask again after a minute, or use the link of the first mail.
5. An address that has an account gets a mail that says so, with a link to sign in and to reset the password.
6. Tests: `tests/Feature/SignUpSameAnswerTest.php`, and every test that signs up runs the whole flow (`signUpThroughLink()` in `tests/Pest.php`).

## Alternatives considered

1. The account made at once and the address check, as before: the request would answer differently for an address of an account (no session, no account), or the job would make an account that the password of the form opens, and a sign-in that works tells that the address was free.
2. The password on the form, and typed again on the page of the link: the form would carry the password through the queue, and the person types it twice.
