# 0045. Sign in with an OpenID Connect provider: a generic client, linked by subject

- Status: Accepted, amended by [0052](0052-account-deletion-after-a-new-sign-in.md) (an account without a password confirms the deletion of the account with a new sign-in at the provider: decision 18)
- Date: 2026-11-15
- Issue: #1385
- Pull request: #1495

## Context

[ADR 0010](0010-own-accounts-optional-oidc.md) keeps the accounts of the platform (Jetstream, Fortify) and plans "sign in with the Knowii account" as a generic OpenID Connect client, enabled by the configuration of the instance. The Knowii account is an OpenID Connect provider already: authorization code flow with PKCE (`S256`), ID tokens signed with `EdDSA` (Ed25519), keys at its `jwks_uri` that rotate every 90 days, clients registered by hand. The decision of the owner (2026-11-15) settles the open points: an unverified platform account with the same address is taken over by the first sign-in of its owner, the scope `offline_access` is never asked and no refresh token is stored, and the API shows and removes the links.

This is sign-in code: a mistake gives an account to the wrong person. So every rule below says what the code refuses, and tests cover each refusal.

## Decision

### Generic, optional, per instance

1. The client knows no provider and no community. `OIDC_ISSUER`, `OIDC_CLIENT_ID`, `OIDC_CLIENT_SECRET` turn the feature on (all three, and an issuer that is `https`, or a loopback address in the local environment). `OIDC_SCOPES` (default `openid profile email`) and `OIDC_LABEL` (the text of the button) are optional. Without the label, the host name of the issuer is the label. `openid` is always asked and `offline_access` never: no refresh token is asked or stored.
2. Without the configuration there is no button, the two routes answer 404 and the instance works as before. The shared page prop `oidc` is null, the profile page has no card, and the answer of `GET /api/v1/ping` holds `data.oidc` null.

### The flow

3. Authorization code flow with PKCE `S256`, `state` and `nonce`. The three secrets and the date of the start live in the session, are read and deleted at the first callback, and expire after ten minutes. A callback without a flow, with a missing or wrong `state`, with an `error` of the provider, or with an `iss` that is not the issuer (RFC 9207) is refused. The text of an error of the provider is never shown.
4. Routes (web): `GET /auth/oidc/redirect` (a guest signs in, a signed-in member connects an account) and `GET /auth/oidc/callback`, both under the limiter `oidc` (10 a minute per IP address). The redirect address to register at the provider is `<APP_URL>/auth/oidc/callback`.
5. A failure sends the member back to the sign-in page (or to the profile settings for a member who connects) with one plain sentence. The log holds the reason code and the issuer, never a token, a code, a subject or an address.
6. `/login?provider=1` goes to the provider at once. The password, the sign-in link and the registration stay available. The "intended" address of the session ([ADR 0044](0044-public-spaces-for-visitors.md)) is kept: after the sign-in the member goes back to the page that they were reading.

### The client and the ID token

7. The client is `App\Actions\Auth\OpenIdConnectClient`, behind the contract `App\Contracts\Auth\ReadsOpenIdConnectIdentities`, so that tests (and other implementations) can fake the provider. The JWT library is `firebase/php-jwt` 7.2.1 (exact version): maintained, small, supports `EdDSA` through `ext-sodium`, and it is the library that the rest of the PHP ecosystem uses. It only checks a signature and the dates. The rules below are ours.
8. The discovery document is read from `<issuer>/.well-known/openid-configuration` and refused when its `issuer` is not the configured one, when it lists code challenge methods without `S256`, response types without `code`, signature algorithms without one of ours, or client authentication methods without `client_secret_basic` or `client_secret_post`. Basic is preferred. Its endpoints must be `https` (or loopback in the local environment).
9. Every request goes through the safe rules of the application: a timeout of 10 seconds, no redirect followed, a size limit, TLS verification on, and for a host that is not loopback `SafeUrl` checks that it resolves to public addresses only and pins the connection to the checked address. A discovery document cannot point the server at an internal service.
10. The discovery document and the keys are cached for one hour. When the key id of a token is unknown and the keys came from the cache, they are read again, once. A failed read is never cached. Only the public parts of the keys that the client can use are kept: RSA of 2048 bits or more, P-256 and Ed25519. A key for encryption, a private part and a shared secret (`oct`) are dropped.
11. The ID token is accepted only when, in this order: its algorithm is `EdDSA`, `RS256` or `ES256` (never `none`, never an HMAC, whatever the keys hold, so a public key cannot be used as an HMAC secret); the key is found by `kid` (without `kid`, only when exactly one key fits) and its type fits the algorithm; the signature is valid; `iss` equals the configured issuer character for character; `aud` holds the client ID (and with several audiences `azp` is the client ID); `exp` is in the future, without any skew; `iat` is present and not more than 60 seconds in the future; `nonce` equals the nonce of the session (compared in constant time); `sub` is text of 1 to 255 characters without control characters.
12. Only the identity leaves the client: the subject, the address, whether `email_verified` is exactly the boolean `true` (the text "true" and the number 1 are not), and the name. The access token and any other token are ignored and never stored.

### Which account

13. The table `user_identities` holds `user_id` (deleted with the account), `issuer`, `subject`, `linked_at`, `last_used_at` and a `cuid` for the routes. Unique indexes on `(issuer, subject)` (a provider account belongs to one member) and `(user_id, issuer)` (a member has one account per provider). No address, no name and no token of the provider is stored.
14. `App\Actions\Auth\SignInWithOpenIdConnect` decides, in this order:
    1. A signed-in member who started the flow from the profile settings connects the account. It is refused when the subject is connected to another member, or when the member already has an account at this provider. This comes first: otherwise the next case would sign the member in as someone else.
    2. An identity with this issuer and subject exists: sign in its account, whatever the provider says about the address.
    3. `email_verified` is not `true`, or there is no usable address: refused, with a message that says to verify the address at the provider.
    4. A platform account has this address (compared without case) and verified it: connect it and sign in.
    5. A platform account has this address and never verified it: connect it, mark the address verified, remove its password, close its sessions, delete its API tokens and its password reset tokens, and remove a two-factor secret. The provider proved the address and whoever created that account never did (decision of the owner).
    6. Otherwise create the account through the normal creation path (`CreateNewUser::createAccount`): the name from `name` (else the part of the address before the `@`), a free username, a verified address, no password, a profile and a personal community.
15. Never a guess: an address that matches two accounts, or an account that already has another account at this provider, is refused. Two sign-ins at once end in the unique indexes.
16. The address of the platform account never follows the provider. A new address at the provider changes nothing here.
17. Two-factor authentication stays. An account that has it is asked for its code after the callback, with the same challenge as after a password (`login.id` in the session). The provider never replaces it. The second factor is the only thing that case 5 removes: it belonged to the person that the provider proved wrong.
18. An account without a password has `users.password` null (the column accepts null): nothing matches it, on the web or on the API login. The member sets a password with "Forgot your password?". Until then the actions that ask for the password (delete the account, transfer a community) cannot be confirmed. Changed by [ADR 0052](0052-account-deletion-after-a-new-sign-in.md): such an account deletes itself after a new sign-in at the provider (`max_age=0`, `prompt=login`, and a checked `auth_time`).

### The settings and the API

19. Profile settings: a "Connected accounts" card with the state, the date, "Connect" and "Disconnect". "Connect" is a `POST /auth/oidc/connect` with the CSRF token of the member, after the password was confirmed a moment ago when the account has one. The link of the sign-in (`GET /auth/oidc/redirect`) never starts a flow for a signed-in member: another site could open that link in the browser of a member, and the provider account of an attacker would then be connected to the account of the member. Disconnect is always allowed: the account keeps its address, so a sign-in link or a password reset works. A sentence says when the account has no password. Sessions and API tokens do not change.
20. API: `GET /api/v1/user/identities` (the issuer, a label, the date of the link and of the last use, never a subject) and `DELETE /api/v1/user/identities/{identityCuid}`. The abilities are `identities:read` and `identities:write`, not in the default set of a new token (they link the account to another service). The identity of another member gives 404.
21. The deletion of the platform account deletes its identities (cascade). A later sign-in with the same provider account creates a new, empty account ([ADR 0024](0024-account-deletion-removes-personal-data.md)).

### Personal data

22. New data: the issuer and the subject, the date of the link and of its last use. Kept while the link exists, shown to the member only, in the member export (issuer, subject and dates: the subject of the own account is the member's data) and in the privacy policy (section 2, item 1). No log line holds them.

## Consequences

1. A self-hosted instance chooses its provider with three variables. Nothing in the code names one. A second provider on one instance is a later step: the table already allows it (`issuer` is part of both indexes), the configuration and the pages do not.
2. The first sign-in links by the address, once. After that only the subject counts: the provider cannot move a link to another account by changing an address.
3. A provider that does not send `email_verified: true` cannot create or link accounts. It can still sign in an account that is connected already, and connect an account from the profile settings.
4. An account made by the provider has no password. It signs in with the provider, or with a sign-in link, and sets a password by the reset flow when it needs one.
5. Signing out of the platform does not sign out of the provider, and the reverse (no back channel in this version).
6. The other ways to sign in stay. A setting that refuses them, with a fallback for the owner, is a later step.
7. Entitlements of the provider ([ADR 0010](0010-own-accounts-optional-oidc.md), #1386) are read server to server later: this record stores no token for it.
8. A new dependency: `firebase/php-jwt`. A key of the provider that rotates is followed without a deployment.

## Alternatives considered

1. Laravel Socialite with a generic OAuth2 driver: no discovery, no ID token validation, and no `EdDSA` without extra code. The checks of the ID token are the point of this feature.
2. `league/oauth2-client` or a full OpenID Connect certified client: more code and more surface than a client that does one flow, and the same ID token checks to write on top.
3. Linking by address on every sign-in (no stored subject): a provider account that changes its address, or an address that is reused, would give an account to another person.
4. Refusing the unverified account of case 5 and asking the person to verify the platform address first: the owner decided on the takeover, which is safe because the provider proved the address.
5. Storing the address and the name of the provider: personal data that the platform does not need, and a second source of truth for an address that is the member's own.
6. A refresh token to read the entitlements later: the entitlements are read server to server, so no token is stored.
