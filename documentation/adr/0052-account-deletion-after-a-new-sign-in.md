# 0052. An account without a password deletes itself after a new sign-in at the provider

- Status: Accepted
- Date: 2026-11-18
- Issue: #1521
- Pull request: #1662
- Amends: [0043](0043-delete-content-with-the-account.md) ("The API route", point 5: an account without a password could not be deleted through the API) and [0045](0045-openid-connect-sign-in.md) (decision 18: an account without a password could not confirm a deletion)

## Context

An account can exist without a usable password: it signs in only through the OpenID Connect provider of the instance ([ADR 0045](0045-openid-connect-sign-in.md)). The deletion of an account asked for the password, on the web and in the API ([ADR 0043](0043-delete-content-with-the-account.md)), so such a member could not delete the account alone. Every member must be able to use the right to erasure without the help of an operator.

The owner decided (2026-11-17): the proof of the intent for such an account is a new sign-in at the provider, a short time ago, checked on the server. Two-factor authentication is never skipped. On 2026-11-18 the owner chose that the API follows the same rule: a token client uses the proof that a browser made a short time before.

A new nonce and a new `iat` prove only a new round trip, not a new sign-in: a person who is still signed in at the provider passes with no action. `max_age=0` asks the provider for a new sign-in, but a person can remove it from the request in their own browser, and a provider can ignore it. Only the claim `auth_time` of the signed ID token says when the person signed in.

## Decision

### Who

1. An account with a usable password: nothing changes (the password, and on the API the two-factor code when it is on).
2. An account without a password that has a connected identity of the provider of the instance, on an instance where the provider is configured: the proof below.
3. An account without a password and without a way to make the proof (no connected identity, or no provider any more): a refusal with a text that tells the person to write to the operator of the site. Never a deletion without a proof. `App\Support\AccountDeletion::proofOf()` decides the case, and the enum `KnowiiAccountDeletionProof` names it (`password`, `sign_in`, `unavailable`).

### The proof

4. The member starts it from the deletion section of the profile settings: `POST /auth/oidc/confirm-deletion` (CSRF token, signed-in session, verified address, limiter `oidc`). The flow in the session has its own `state`, nonce and PKCE verifier, used once, the purpose `deletion` and the id of the account.
5. The authorize request holds `max_age=0` and `prompt=login`, and uses the one callback address that the provider knows (`/auth/oidc/callback`). The callback tells the sign-in, the connection of an account and the proof apart by the purpose that the server stored with the flow, never by a parameter of the browser.
6. The callback checks the request and the ID token as for a sign-in (state, issuer, audience, signature, expiry, nonce, the same signed-in account that started the flow), then `App\Actions\Auth\ConfirmAccountDeletion` checks, in this order: the account still has no password and is still connected to the provider; `auth_time` is present and an integer (else: "The sign-in provider did not confirm a new sign-in"); `auth_time` is at most `services.oidc.fresh_sign_in_seconds` old (`OIDC_FRESH_SIGN_IN_SECONDS`, default 300, kept between 60 and 900) and not more than 60 seconds in the future; `auth_time` is not before the start of the flow (60 seconds of clock difference allowed: a provider that ignores `max_age` sends the time of an older sign-in, such as the one that opened the session of the platform); `sub` is exactly the subject of the identity that is connected to this account.
7. A valid proof is the time of the sign-in on the account: `users.deletion_signed_in_at`. It holds for `fresh_sign_in_seconds` after `auth_time` (not after the callback). The callback is a GET: it records the proof and never deletes anything. The member comes back to the deletion section, where the dialog opens again and says until when the proof holds.
8. A refused proof leaves the account and an earlier proof as they were, and says why in one plain sentence (no detail of the token). Each refusal is logged with its reason, the issuer and the cuid of the account, never a token, a code or a subject.

### The deletion

9. The web route `DELETE /user` and the API route `DELETE /api/v1/user` stay the only ways to delete. For case 2 they take the recorded proof in place of the password, and the two-factor code when two-factor authentication is on (on the web too, with 3 tries a minute; the API keeps its limiter `account-deletions`; since [ADR 0067](0067-the-tries-of-the-current-password-count-for-what-signed-the-request-in.md) the page and the API count the wrong codes together, per account, now with the sign-in: 5 a minute and 10 an hour). `DeleteUser::deleteAfterSignIn()` locks the row of the account, checks the proof again (present, of this account, not expired), clears it and deletes the account in one transaction: a second request cannot use it. `delete_content` works as before.
10. API: with a valid proof the request succeeds with the same token ability (`account:delete`). Without one, the answer is a business error (422) whose message says that a new sign-in is needed and gives the address of the deletion section (also in `metadata.sign_in_url`). The API has no route that makes or reads a proof, and no new token ability.

### The screen

11. Case 1: the password, as before. Case 2: no password field, a sentence that explains the step, and "Confirm with <label>" (the label of the sign-in button); after a valid proof: "You confirmed with <label>. You can delete your account until <time>." and the final button, with the code field when two-factor authentication is on. The box "Also delete my posts, comments and messages" keeps its value through the round trip (the server keeps it with the flow and gives it back). Case 3: no button, the refusal text.

## Consequences

1. Changed by this record: ADR 0043, "The API route", point 5 (an account without a password could not be deleted through the API), and ADR 0045, decision 18 (an account without a password could not confirm a deletion).
2. A provider must support `max_age` and send `auth_time` for the deletion of an account without a password. A provider that does not send `auth_time` gets the clear refusal, never a deletion. `SELF_HOSTING.md` says it.
3. `users.deletion_signed_in_at` is one value per account: replaced by a new proof, cleared by the deletion that uses it, deleted with the account. Nothing accumulates, so `retention:prune` has no step for it. It is hidden from every serialization and not in the export of the member: it is not useful after a few minutes.
4. The proof is bound to the account, not to a session: a token of the account can use it in the minutes after the browser made it. That is the choice of the owner, and the reason for the short window and the single use.
5. The person needs the provider in a browser to delete the account. When the provider is gone for good, the operator deletes the account (case 3).

## Alternatives considered

1. A confirmation link sent by mail: it depends on the delivery of a mail and on the suppression list of the platform, and it proves the mailbox, not the person who signs in. The owner chose the new sign-in.
2. Delete the account in the callback: the callback is a GET that the provider sends the browser to. A GET must not delete anything, the person could not check the box or give the two-factor code there, and a lost answer would leave the person without a result.
3. `max_age=0` alone, without `auth_time`: the browser can remove the parameter and a provider can ignore it. Only `auth_time` in the signed token proves the time of the sign-in.
4. Keep the proof only in the session: the API could not use it, and the owner decided that the API follows the same rule. The column on the account is read by a web session and by a token request, and the deletion can use it up in its own transaction.
5. The API refuses and only the web deletes such an account: not chosen by the owner (the API stays complete).
