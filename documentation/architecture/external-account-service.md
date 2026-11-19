# The external account service

The platform keeps its own accounts. An instance can also use an external account service: an OpenID Connect provider that signs people in, and that can say what each person has access to (a plan, a course). The code of the platform names no service and no host: this page is the contract that a provider implements, and the configuration of the platform points at it. A provider that implements this page works with the platform without a change of the code.

Two names are used:

1. "The external account service" is the whole contract: the sign-in, the new sign-in, the entitlements and the change calls.
2. "The sign-in provider" is the sign-in part only.

The decisions behind it: [ADR 0010](../adr/0010-own-accounts-optional-oidc.md) (own accounts, an optional provider), [ADR 0045](../adr/0045-openid-connect-sign-in.md) (the sign-in), [ADR 0047](../adr/0047-entitlement-rules-feed-access-groups.md) (the entitlements and the change calls) and [ADR 0052](../adr/0052-account-deletion-after-a-new-sign-in.md) (the new sign-in for a deletion). The steps for an operator are in [`SELF_HOSTING.md`](../../SELF_HOSTING.md). `tests/Unit/InstanceNeutralNamesTest.php` fails when the name or a host of one instance comes back in the code. The same guard covers the other values of one instance (#1653, [ADR 0055](../adr/0055-instance-values-come-from-configuration.md)): its operator, the founder, the company number, the street address and the hosts of the hosted instance come from configuration, never from the code.

## What the platform needs

Every part is optional. An instance without any part works with its own accounts only.

| Part                                   | The provider implements                                                                      | Needed for                                                                                  | Turned on by                                          |
| -------------------------------------- | -------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------- | ----------------------------------------------------- |
| [Sign-in](#the-sign-in)                | OpenID Connect: discovery, the authorization code flow with PKCE, signed ID tokens, the keys | A button `Continue with <label>`, and "Connected accounts" in the profile settings          | `OIDC_ISSUER`, `OIDC_CLIENT_ID`, `OIDC_CLIENT_SECRET` |
| [New sign-in](#the-new-sign-in)        | `max_age=0` honored, and `auth_time` in the ID token                                         | The deletion of an account without a password (an account that the sign-in created)         | The sign-in                                           |
| [Entitlements](#the-entitlements-read) | The client credentials grant, and a read of the keys of one subject                          | Access groups that follow what a person has at the provider                                 | The sign-in and `OIDC_ENTITLEMENTS_URL`               |
| [Change calls](#the-change-calls)      | A signed JWT sent to the platform when the keys of a subject change                          | Access that changes within seconds (without them, the next read is at most one night later) | The entitlements                                      |

Without the new sign-in, the sign-in works, and a member whose account has no password gets a refusal that asks them to write to the operator. Without the change calls, the keys are read at each sign-in, at each join of a listed community and once a night.

## Rules for every request of the platform

The server of the platform calls the provider for the discovery document, the keys, the token endpoint and the entitlements read. Each request:

1. Goes to an `https` address with a valid certificate. Only in the local environment, an `http` address on a loopback host (`localhost`, `127.0.0.1`, `::1`) is accepted, to test with a provider on the machine.
2. Goes to a host that resolves to public addresses only. The connection is pinned to the checked address.
3. Follows no redirect: a `3xx` answer is a failure.
4. Waits 5 seconds at most for the connection and 10 seconds at most for the answer.
5. Accepts a body of 256 KiB at most. A JSON document has a depth of 8 at most. A token (an ID token, an access token, a change call) has 16 KiB at most.

The discovery document and the keys are kept in the cache for one hour. A failed read is never cached.

## The sign-in

### Registration of the client

Register the platform at the provider as one confidential client:

1. The grant type `authorization_code`, with PKCE. With the entitlements, also `client_credentials` for the same client (see [the entitlements read](#the-entitlements-read)).
2. The redirect address `<APP_URL>/auth/oidc/callback`, exactly (for example `https://community.example.com/auth/oidc/callback`). It is the only redirect address: the sign-in, the connection of an account and the new sign-in use it.
3. A client authentication of `client_secret_basic` or `client_secret_post`. The platform prefers Basic. The identifier and the secret are form-encoded before the Basic encoding (RFC 6749, section 2.3.1).

### Discovery

The issuer (`OIDC_ISSUER`) is an `https` address without a query, a fragment or a user name, of 512 characters at most. The platform reads `<issuer>/.well-known/openid-configuration` (a trailing `/` of the issuer is removed first). The document must hold:

1. `issuer`: equal to `OIDC_ISSUER`, character for character.
2. `authorization_endpoint`, `token_endpoint` and `jwks_uri`: `https` addresses.

These members are optional. When one is present, it must allow what the platform uses, or the document is refused:

1. `response_types_supported`: holds `code`.
2. `code_challenge_methods_supported`: holds `S256`.
3. `id_token_signing_alg_values_supported`: holds `EdDSA`, `RS256` or `ES256`.
4. `token_endpoint_auth_methods_supported`: holds `client_secret_basic` or `client_secret_post`. Without this member, the platform uses `client_secret_basic`.

### The authorization request

The platform sends the browser to `authorization_endpoint` with these parameters:

| Parameter               | Value                                                                                                                                |
| ----------------------- | ------------------------------------------------------------------------------------------------------------------------------------ |
| `response_type`         | `code`                                                                                                                               |
| `client_id`             | `OIDC_CLIENT_ID`                                                                                                                     |
| `redirect_uri`          | `<APP_URL>/auth/oidc/callback`                                                                                                       |
| `scope`                 | `openid`, the scopes of `OIDC_SCOPES` (default `openid profile email`) and, with the entitlements, `OIDC_ENTITLEMENTS_SIGN_IN_SCOPE` |
| `state`                 | A random value, used once                                                                                                            |
| `nonce`                 | A random value, used once                                                                                                            |
| `code_challenge`        | The PKCE challenge                                                                                                                   |
| `code_challenge_method` | `S256`                                                                                                                               |
| `max_age`, `prompt`     | `0` and `login`, only for [the new sign-in](#the-new-sign-in)                                                                        |

The scope `offline_access` is never sent, whatever the configuration says. The platform asks for no refresh token and stores none.

The flow lives in the session of the browser for 10 minutes and is used once.

### The callback

The provider sends the browser back to the redirect address with `code` and `state`. The platform refuses the callback when:

1. The session holds no flow, the flow is older than 10 minutes, or `state` is not the one of the flow.
2. The query holds `error` (its text is never shown).
3. The query holds `iss` (RFC 9207) and it is not the issuer. `iss` is optional.
4. `code` is missing or longer than 2048 characters.

### The token exchange

The platform posts `grant_type=authorization_code`, `code`, `redirect_uri` and `code_verifier` to `token_endpoint`, with the client authentication. The answer must be `200` with a JSON object that holds `id_token`. The access token and any refresh token of the answer are ignored and never stored.

### The ID token

The platform accepts the ID token only when, in this order:

1. The header `alg` is `EdDSA`, `RS256` or `ES256`. `none`, every HMAC algorithm and anything else are refused before a key is read.
2. A key of `jwks_uri` fits (see [the keys](#the-keys)): the key of the header `kid`, or, without `kid`, the only key of the set that fits the algorithm.
3. The signature is valid.
4. `iss` is the issuer, character for character.
5. `aud` holds the client ID. With several audiences, or when `azp` is present, `azp` is the client ID.
6. `exp` is in the future, without any clock difference.
7. `iat` is present and at most 60 seconds in the future. When `nbf` is present, it is at most 60 seconds in the future.
8. `nonce` is the nonce of the flow.
9. `sub` is text of 1 to 255 characters without a control character. It must never change for an account: the platform links its account to the pair issuer and `sub`.

The platform reads these claims and nothing else:

| Claim            | Needed                                  | What the platform does with it                                                                                                                                                                      |
| ---------------- | --------------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `sub`            | Always                                  | Stored with the issuer (`user_identities`). After the first sign-in, only this pair finds the account                                                                                               |
| `email`          | For a first sign-in                     | Finds the platform account with the same address (without case), or becomes the address of a new account. Never stored otherwise, and never followed: a new address at the provider changes nothing |
| `email_verified` | For a first sign-in                     | Must be exactly the boolean `true` (the text `"true"` and the number `1` are not). Otherwise a person without a connected account cannot sign in                                                    |
| `name`           | No                                      | The name of a new account (100 characters at most). Without it, the part of the address before the `@`                                                                                              |
| `auth_time`      | For [the new sign-in](#the-new-sign-in) | The time of the sign-in at the provider, an integer (seconds since 1970). A text or a decimal number is no time                                                                                     |

What the platform does with the identity (link by address once, take over an unverified account, create an account, the second factor) is in [ADR 0045](../adr/0045-openid-connect-sign-in.md), decision 14.

### The keys

`jwks_uri` gives a JSON object with `keys`, a list of JSON Web Keys. The platform keeps only the public members (`kty`, `kid`, `use`, `alg`, `crv`, `x`, `y`, `n`, `e`) of the keys that it can use:

| Algorithm | Key                                        |
| --------- | ------------------------------------------ |
| `EdDSA`   | `kty` `OKP`, `crv` `Ed25519`, `x`          |
| `RS256`   | `kty` `RSA`, `n` of 2048 bits or more, `e` |
| `ES256`   | `kty` `EC`, `crv` `P-256`, `x`, `y`        |

A key with `use` other than `sig`, or with an `alg` that is not the algorithm of its type, is not used. A private part and a shared secret (`oct`) are dropped. A set without a usable key is a failure.

When a token names a `kid` that the cached keys do not hold, the platform reads `jwks_uri` again, once. For the change calls, it does so at most once a minute. A provider that rotates its keys publishes the new key before it signs with it.

## The new sign-in

An account that the sign-in created has no password. To delete it, the member proves the intent with a new sign-in at the provider ([ADR 0052](../adr/0052-account-deletion-after-a-new-sign-in.md)). `App\Support\AccountDeletion::proofOf()` decides which proof an account needs, and `App\Actions\Auth\ConfirmAccountDeletion` checks the new sign-in.

1. The member starts it in the deletion section of the profile settings (`POST /auth/oidc/confirm-deletion`).
2. The authorization request holds `max_age=0` and `prompt=login`: the provider must ask the person to sign in again, also when the person is signed in there. It goes to the same redirect address.
3. The callback and the ID token are checked as for a sign-in. The member who comes back must be the one who started the flow.
4. Then the ID token must hold `auth_time`, an integer, and:
   1. `auth_time` is at most `OIDC_FRESH_SIGN_IN_SECONDS` old (default 300, kept between 60 and 900) and at most 60 seconds in the future.
   2. `auth_time` is not before the start of the flow (60 seconds of clock difference allowed). A provider that ignores `max_age` sends the time of an older sign-in, and is refused.
   3. `sub` is the subject that is connected to the account.
5. A valid proof is stored on the account and holds for `OIDC_FRESH_SIGN_IN_SECONDS` after `auth_time`. The callback deletes nothing: the member deletes the account with `DELETE /user` or `DELETE /api/v1/user` within that time, with the two-factor code when it is on. The deletion uses up the proof.

`max_age` alone proves nothing: the browser can remove it from the request, and a provider can ignore it. Only `auth_time` in the signed ID token is the proof. A provider that does not send `auth_time` gets the refusal "The sign-in provider did not confirm a new sign-in", never a deletion. `tests/Feature/DeleteAccountWithNewSignInTest.php` covers each case.

## The entitlements read

An entitlement is a key that says what a person has at the provider, for example `example.paid` or `course.intro`. The platform never interprets a key: the managers of a listed community give an access group to the holders of a key (a rule).

### The token

The platform asks a token for itself, never for a person: it posts to `token_endpoint`, with the client authentication of the sign-in:

| Field        | Value                                                            |
| ------------ | ---------------------------------------------------------------- |
| `grant_type` | `client_credentials`                                             |
| `scope`      | `OIDC_ENTITLEMENTS_SCOPE` (default `entitlements:read:subjects`) |
| `resource`   | `OIDC_ENTITLEMENTS_RESOURCE`, only when it is set (RFC 8707)     |

The answer must be `200` with a JSON object:

1. `access_token`: text of 16 KiB at most.
2. `token_type`: `Bearer` (any case).
3. `expires_in`: an integer, in seconds. The platform keeps the token, encrypted, until a minute before it expires, and never longer than an hour. Without `expires_in`, or with 60 seconds or less, the token is used once.

### The request

`GET <OIDC_ENTITLEMENTS_URL>` with `{subject}` replaced by the `sub` of the ID token, percent-encoded (`rawurlencode`), and the headers `Authorization: Bearer <access token>` and `Accept: application/json`. The address template is an `https` address with `{subject}` once, in its path, and no query, for example `https://accounts.example.com/v1/subjects/{subject}/entitlements`.

When the sign-in has the entitlements on, it also asks the scope `OIDC_ENTITLEMENTS_SIGN_IN_SCOPE` (default `entitlements:read`). The provider can use it to know that the person agreed that this client reads their entitlements.

### The answer

A `200` answer is a JSON object:

```json
{
  "sub": "248289761001",
  "entitlements": ["example.paid", "course.intro"]
}
```

| Member         | Needed | What the platform does with it                                                                                                                                                                                               |
| -------------- | ------ | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `entitlements` | Yes    | The list of the active keys. They replace, as a whole, the keys that the platform stored for this account and issuer, and the rules are applied at once. A key matches a rule only when it is equal, character for character |
| `sub`          | No     | When it is present, it must be the subject of the request. Otherwise the answer is not used                                                                                                                                  |
| Anything else  | No     | Ignored and never stored (a grant, a source, a date)                                                                                                                                                                         |

A key is kept only when it has 1 to 100 characters among letters, digits, `.`, `-` and `_`. Another key is ignored, because no rule can name it. Duplicates are removed. The platform keeps 200 keys at most per account (in sorted order). An empty list is a valid answer: the person has nothing, and the rule memberships go.

### What each status means

| Answer                                                     | What the platform does                                                                                                                      |
| ---------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------- |
| `200` with the object above                                | Replaces the keys and applies the rules                                                                                                     |
| `200` with another body (not JSON, no list, another `sub`) | A temporary failure: nothing changes. A bad answer never takes access away                                                                  |
| `404`                                                      | The link at the provider is gone, or the account there is deleted: the platform deletes the stored keys and removes the rule memberships    |
| `401`                                                      | The platform asks one new token and reads once more. A second `401` is handled as `403`                                                     |
| `403`                                                      | A configuration problem: nothing changes, nothing is tried again, and one line is logged once an hour                                       |
| `429`, `5xx`, any other status, a timeout, a network error | A temporary failure: nothing changes, and the read is tried again after 1 minute, 5 minutes, 30 minutes, 2 hours and 6 hours, then at night |

The token endpoint: `400`, `401` and `403` are a configuration problem, `408`, `429` and `5xx` are temporary.

A `404` is a strong answer: send it only for a subject that this client cannot read any more. An address template that is wrong also gives `404`: try the address with one known subject before you set it.

### When the platform reads

1. After each sign-in through the provider, and after a member connects the account from the profile settings.
2. When a person becomes a member of a listed community that has rules.
3. When a change call arrives.
4. Once a night (02:30, `entitlements:sync`) for every connected account, one read a second at most.

## The change calls

When the keys of a subject change, the provider sends `POST <APP_URL>/webhooks/oidc/entitlements`. The call says only "something changed for this subject": the platform never trusts its content for more than the subject, and reads the keys again (see [the entitlements read](#the-entitlements-read)).

### The request

1. The header `Content-Type: application/jwt` (a parameter and any case are accepted).
2. The body: one compact JWS (three segments), of 16 KiB at most. Spaces around it are removed.
3. No cookie, no session, no CSRF token and no API token: the signature is the proof.

### The JWT

Header:

```json
{ "alg": "EdDSA", "typ": "entitlements-event+jwt", "kid": "key-2026-11" }
```

Claims:

```json
{
  "iss": "https://accounts.example.com",
  "aud": "the client ID of the platform",
  "sub": "248289761001",
  "jti": "5f0c2b7e-2a4e-4d47-9f53-0d9a1d0c8e11",
  "iat": 1795000000,
  "exp": 1795000300,
  "event": "entitlements.changed"
}
```

The platform accepts the call only when, in this order:

1. The body has three segments, and `alg` is `EdDSA`, `RS256` or `ES256` (never `none`, never an HMAC).
2. The header `typ` is exactly `entitlements-event+jwt`. An ID token is never a change call.
3. A key of `jwks_uri` fits, by `kid` (see [the keys](#the-keys)), and the signature is valid.
4. `iss` is the issuer, character for character.
5. `aud` holds the client ID.
6. `exp` is in the future, without any clock difference.
7. `iat` is at most 5 minutes old, and at most 60 seconds in the future.
8. `event` is exactly `entitlements.changed`.
9. `sub` and `jti` are texts of 1 to 255 characters without a control character. `sub` is the same value as the `sub` of the ID token for this client.

### The answers

| Answer            | When                                                                                                         | What the provider does                                   |
| ----------------- | ------------------------------------------------------------------------------------------------------------ | -------------------------------------------------------- |
| `202`, empty body | A valid call. The platform queues a read when an account is connected with this subject                      | Nothing more                                             |
| `202`, empty body | A valid call for a subject that nobody here knows: the same answer, so that nobody learns who has an account | Nothing more                                             |
| `202`, empty body | A `jti` that the platform handled in the last 24 hours: nothing happens again                                | Nothing more: a retry keeps the same `jti`               |
| `401`, empty body | Any check above failed, or the content type is not `application/jwt`. The body never says why                | Check the call. The log of the platform holds the reason |
| `404`             | The entitlements are off on this instance                                                                    | Stop sending                                             |
| `429`             | More than 120 calls a minute from one IP address                                                             | Try again later, with the same `jti`                     |

A `jti` is stored only after every other check passed, so a refused call never uses up the `jti` of a real one. A call that is lost changes nothing for a long time: the next sign-in, join or nightly read finds the change.

## The errors of the sign-in

A failure of the sign-in sends the person back to the sign-in page, or to the profile settings when a member connects an account or makes the new sign-in, with one plain sentence. The sentence never repeats what the provider or the token said. The log holds a short reason code and the issuer, never a token, a code, a subject or an address.

| What happens                                                                                  | What the person sees                                                                                         |
| --------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------ |
| The provider cannot be reached, or the discovery document, the keys or the token are refused  | "We could not sign you in with this account. Please try again, or use another way to sign in."               |
| The flow expired, was not started here, or `state` is wrong                                   | "This sign-in expired" or "This sign-in could not be checked", and "Please try again"                        |
| The person signed in or out on the platform during the flow                                   | "You signed in or out while this was going on. Please try again."                                            |
| `email_verified` is not `true` for a person without a connected account                       | "The provider has not verified your email address. Verify it there, then try again."                         |
| No usable `email` for a person without a connected account                                    | "The provider did not send an email address that we can use. Add one to your account there, then try again." |
| The address matches several accounts, or the account is connected to another provider account | A sentence that asks the person to contact the administrators, or to sign in the way they did before         |
| The provider account is connected to another member (when a member connects it)               | "This account is already connected to another member of this site."                                          |
| The new sign-in: no `auth_time`, an old `auth_time`, or another `sub`                         | A sentence that says why, and "Your account was not changed."                                                |

## Links to the service

The platform has no link to a page of the provider today. A future link, such as "Manage my account" or "Get access", will be a configuration value: an address and a label. When it is not set, the link does not show. No address of a provider is ever written in the code.

## Configuration of the platform

| Value                             | Default                      | Part         | What it is                                                                                                          |
| --------------------------------- | ---------------------------- | ------------ | ------------------------------------------------------------------------------------------------------------------- |
| `APP_URL`                         |                              | All          | The base of the redirect address (`/auth/oidc/callback`) and of the change address (`/webhooks/oidc/entitlements`)  |
| `OIDC_ISSUER`                     |                              | Sign-in      | The issuer, exactly as the provider writes it in `iss`                                                              |
| `OIDC_CLIENT_ID`                  |                              | Sign-in      | The client ID, and the `aud` of the ID tokens and of the change calls                                               |
| `OIDC_CLIENT_SECRET`              |                              | Sign-in      | The secret of the client                                                                                            |
| `OIDC_SCOPES`                     | `openid profile email`       | Sign-in      | The scopes of the sign-in (`openid` is always added, `offline_access` is always removed)                            |
| `OIDC_LABEL`                      | The host name of the issuer  | Sign-in      | The name on the button (`Continue with <label>`), 40 characters at most                                             |
| `OIDC_FRESH_SIGN_IN_SECONDS`      | `300`                        | New sign-in  | How old `auth_time` may be, and how long the proof holds (kept between 60 and 900)                                  |
| `OIDC_ENTITLEMENTS_URL`           |                              | Entitlements | The address template of the read, with `{subject}` once in its path. Turns on the entitlements and the change calls |
| `OIDC_ENTITLEMENTS_SCOPE`         | `entitlements:read:subjects` | Entitlements | The scope of the client credentials grant                                                                           |
| `OIDC_ENTITLEMENTS_RESOURCE`      |                              | Entitlements | The `resource` of the token request, when the provider needs one                                                    |
| `OIDC_ENTITLEMENTS_SIGN_IN_SCOPE` | `entitlements:read`          | Entitlements | The scope that the sign-in also asks when the entitlements are on                                                   |
| `OIDC_ENTITLEMENT_COMMUNITIES`    |                              | Entitlements | The slugs of the communities that may use rules, separated by commas. Empty means none                              |

The code reads them in `config/services.php` (`oidc`), through `App\Support\OpenIdConnect` and `App\Support\OidcEntitlements`.
