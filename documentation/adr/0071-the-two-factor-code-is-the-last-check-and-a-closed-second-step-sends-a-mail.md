# 0071. The two-factor code is the last check, and a closed second step sends a mail

- Status: Accepted
- Date: 2026-11-24
- Issue: none
- Pull request: none
- Uses: [0035](0035-ownership-transfer.md) (the transfer of a community), [0052](0052-account-deletion-after-a-new-sign-in.md) (the deletion of the account), [0054](0054-email-delivery-the-mail-queue-the-budget-bounces-and-the-one-click-stop.md) (the account mails), [0065](0065-the-sign-up-makes-the-account-when-the-link-of-its-mail-is-opened.md) (one answer for every address)

## Context

The code of two-factor authentication (a code of the authenticator app or a recovery code) is checked in four places: the challenge of the web sign-in, the API sign-in, the deletion of the account and the transfer of a community. The wrong codes of an account count together in `App\Support\SecondFactorTries`: 5 a minute and 10 an hour, whatever the IP address. At the limit of the hour, every code of the account waits until the hour is over.

A recovery code that passes is used up. A request can also be refused for a reason that is not the code. The member does not see the wrong codes that another person enters. The challenge of the web sign-in also had the limiter `two-factor` of Fortify (5 requests a minute per account). The account mails that a form asks for (the sign-in link, the sign-up mail, the reset mail, the mail of a change of the address) have a cap of 10 of each kind a day per address.

## Decision

1. The code is the last check of a request that asks for it. The transfer of a community checks the gate, the password, the new owner (a member, and an admin) and then the code. The deletion checks the password (or the proof of a new sign-in) and the option of the content before the code. A request that is refused for another reason never uses a recovery code and never counts a try.
2. The wrong code that reaches the limit of the hour queues one mail to the member (`App\Mail\SecondFactorLocked`): the first factor was right, then wrong codes were entered, the second step is closed until a given time (in the time zone of the member, else the one of the instance), and a member who did not send them changes the password (a link to the page of a password reset). One mail at most for each closed hour; a new hour that closes sends one again. It is an account mail of ADR 0054: queued, encrypted in the queue, never held, and not under the cap of the account mails that a form asks for. It holds no IP address and no place.
3. The tries of the account are the only limit of the challenge of the web sign-in: the limiter `two-factor` is gone (`fortify.limiters.two-factor` is null). When the account has no try left, the page shows "Too many tries" with the wait. A request without a code is an error of the form and counts nothing. A challenge that is older than 10 minutes ends on the sign-in page with "The sign-in took too long. Sign in again."
4. The cap of the account mails is soft: after the 10th mail of a kind in a day, one more mail of that kind goes to the address at most once every 3 hours (`Constants::ACCOUNT_MAILS_OVER_THE_CAP_GAP_HOURS`). A member waits at most 3 hours for a link, and an address gets at most about 18 mails of a kind a day. The answer of the form stays the same for every address (ADR 0065).

## Consequences

1. A new place that checks a code calls `SecondFactorTries` after every other check of the request.
2. A member learns by mail when the second step of the account closes, and can change the password at once.
3. The member of an address that got its 10 mails of a kind gets the next one within 3 hours.

## Alternatives considered

1. A lock that grows after each closed hour: it makes the member wait longer, and the mail already tells the member to change the password.
2. A hard cap of 10 account mails a day: a member could wait a whole day for a reset link.
3. Keep the limiter `two-factor` with another key: the tries of the account already limit the codes, and its answer was a bare error page.
