# 0047. The entitlements of the sign-in provider feed the access groups, in real time

- Status: Accepted
- Date: 2026-11-16
- Issue: #1386
- Pull request: #1506

## Context

[ADR 0010](0010-own-accounts-optional-oidc.md) plans a link between the accounts of the platform and an OpenID Connect provider. [ADR 0025](0025-access-groups.md) gives the spaces with the visibility "groups" to the members of named access groups, and [ADR 0045](0045-openid-connect-sign-in.md) connects the account of a member to an account of the provider (`user_identities`). What a person owns at the provider (a plan, a course) is not in the ID token, because it changes. Today a manager adds the members to the groups by hand, or another system does it with the API.

The owner decided (2026-11-15) that nothing in the platform is specific to one community or to one provider, and that access must follow what the person owns in real time: a person who buys gets the spaces at once, a person whose plan ends loses them soon after. The provider offers two things for this (its own repository): a read of one subject with the client credentials grant, and signed change calls ("something changed for this subject"), never trusted for their content.

This decision extends ADR 0010 (the optional link with a provider), ADR 0025 (access groups) and ADR 0045 (the sign-in and the connected accounts).

This code opens and closes paid spaces. A mistake gives paid content away, or locks paying members out. So every rule below says what the code refuses, and when it is unsure, the code gives less access.

## Decision

### Generic, optional, per instance

1. The feature belongs to the provider of the instance, not to a community. `OIDC_ENTITLEMENTS_URL` (the address of the read of one subject, `https`, with `{subject}` once in the path), `OIDC_ENTITLEMENTS_SCOPE` (default `entitlements:read:subjects`), `OIDC_ENTITLEMENTS_RESOURCE` (optional), `OIDC_ENTITLEMENT_COMMUNITIES` (the slugs of the communities that may use rules, empty means none) and `OIDC_ENTITLEMENTS_SIGN_IN_SCOPE` (default `entitlements:read`). The sign-in of ADR 0045 must be on too.
2. Without the address, the feature does not exist: the change address and the other routes answer 404, no job is queued, the profile has no card and the access group manager has no "Rules". When the feature is on, the sign-in also asks the scope of `OIDC_ENTITLEMENTS_SIGN_IN_SCOPE`: the provider links a client to a person only then. `offline_access` is still never asked, whatever the configuration says.
3. A rule works only in a community that the operator lists. Another community of a shared instance never learns what a person bought through a rule. Nothing in the code names a key or a community of any provider.

### Reading the truth

4. `App\Contracts\Auth\ReadsSubjectEntitlements`, bound to `EntitlementsClient`, reads the keys of one subject with a token of the client credentials grant (the scope, the optional resource). The requests go through the client of ADR 0045 (`OpenIdConnectClient`): its discovery, its keys, and its rules for requests (timeout, no redirect, size limit, public addresses only, TLS). The token is kept in the cache, encrypted, until a minute before it expires. When the provider refuses it (401) one new token is asked, once. It is never logged.
5. 200 gives `entitlements`, the list of active keys. A body that is not what the provider documents (not JSON, no list, another subject) is a temporary failure, never "no keys": a bad answer cannot take access away. A key that does not match the safe pattern is ignored (a rule could not name it). 404 means that the link at the provider is gone or the account there is deleted: it is its own answer. A network error, a timeout, a 5xx, a 429 and any other status are temporary failures. A 401 or 403 after a fresh token is a configuration problem.
6. Only the active keys are stored: `user_entitlements` (`user_id` with cascade, `issuer`, `key`, unique per user, issuer and key), replaced as a whole in one transaction, with `entitlements_checked_at` on the identity. No grant, source or date of the provider is stored, and there is no refresh token anywhere. A read of an identity of another issuer than the one of this instance never happens. Two reads of the same identity never run at the same time (a cache lock), so the answer that is written is the one of the last read.
7. A read runs in the queued job `ReadEntitlements` (the id of the identity only). Triggers: after the link at each sign-in through the provider (also when a member connects the account from the profile settings), when a person becomes a member of a community that is listed and has rules (`JoinCommunity`, `AcceptCommunityInvitation`), when the change call arrives, and once a night for every connected account (`entitlements:sync`, 02:30, a read a second at most, so that the night is not a burst against the limit of the provider).
8. A result: 200 replaces the keys and runs the sync. 404 deletes the keys and runs the sync, which removes the rule memberships. A temporary failure changes nothing and is tried again after 1 minute, 5 minutes, 30 minutes, 2 hours and 6 hours, then left to the nightly read. A refusal by the provider (401 or 403) changes nothing, is not tried again and is logged once an hour, without any token.

### The change call

9. `POST /webhooks/oidc/entitlements`: public, with no session, no cookie, no CSRF token and no token ability (the proof is the signature), limited to 120 calls a minute per IP address. It answers 404 without the feature. It accepts only `Content-Type: application/jwt`.
10. A call is accepted only when, in this order: the shape (three segments, at most 16 KB) and the algorithm are right (`EdDSA`, `RS256` or `ES256`: never `none`, never an HMAC, so a public key cannot be used as a secret); the header `typ` is `entitlements-event+jwt`; the key is found by `kid` among the keys of the issuer (read again once for an unknown key id) and the signature is valid; `iss` is the issuer; `aud` holds the client ID; `exp` is in the future, without skew; `iat` is present, at most 5 minutes old and not in the future beyond a minute; `event` is `entitlements.changed`; `sub` and `jti` are plain text.
11. A `jti` is kept for 24 hours in the cache (it forgets it on its own, and `retention:prune` removes expired cache entries from the database), only after every check above passed, so that a refused call never uses up the `jti` of a real one. A call that repeats a `jti` answers 202 and does nothing (the provider retries with the same `jti`: a repeat is not an error).
12. A valid call answers 202 with an empty body and queues the read of the subject when an identity with this issuer and subject exists. When none exists the answer is the same 202 and nothing else happens: nobody can learn who has an account here. Any invalid call answers 401 with an empty body and no detail. The call is never trusted for more than the subject: the keys come from the read of the truth.

### Rules and memberships

13. `access_group_rules`: `access_group_id` (cascade), `entitlement_key`, unique pair. The key is free text that the platform never interprets, checked against a safe pattern (letters, digits, dot, dash, underscore, 100 characters at most, 20 keys per group). Owner and admins (`CommunityPolicy::manageAccessGroups`) set them in the access group manager and with `GET` and `PUT /communities/{community}/access-groups/{group}/rules` (abilities `groups:read` and `groups:write`). `PUT` replaces the whole list. Moderators with the right to manage members read the groups and the sources of their members, never the keys.
14. `access_group_members` gets two flags: `manual` (added by hand or by the API, the value of every existing row) and `by_rule`. One row per member and group, kept while one of the two is true (a check constraint, and every writer deletes the row when both are false). A hand add sets `manual`. A hand removal clears `manual` only: the member stays when a rule gives the group, and the screen says so. A rule never touches `manual`. `App\Support\AccessGroupMemberships` is the only writer and locks the row first, so a hand change and a sync at the same time cannot lose a flag. The reading rule of [ADR 0041](0041-access-groups-open-space-groups.md) and the member counts do not change.
15. The sync for one member (`SyncEntitlementRules`, behind `SyncsEntitlementRules`): in each listed community where the person is a member (the member list, not the owner), the wanted groups are the groups with a rule whose key the member holds at the issuer of this instance, compared exactly. It sets and clears `by_rule`, deletes empty rows, and sends `AccessGroupsChanged` (the prune of the notifications of [ADR 0042](0042-notifications.md) follows) and `SpaceTreeChanged` once per community that changed. A new, changed or removed rule runs the sync for the members of that community in the queued job `SyncCommunityEntitlementRules`, after the commit, with the keys that are stored (it never calls the provider).
16. Unlinking the identity (ADR 0045) deletes the stored keys of that account and, when it is the account of the provider of this instance, every rule membership of the member, at once. Deleting the account deletes them by the cascades. A member who leaves or is removed leaves the groups as before.
17. Removing a community from `OIDC_ENTITLEMENT_COMMUNITIES`, or turning the feature off, clears the `by_rule` flags at the next nightly run (`entitlements:sync` runs `clearUnlisted`), and with the feature off it deletes the stored keys too. The rules stay stored, and do nothing. A membership that was added by hand stays in every case.

### API, screens, personal data

18. `GET /user/entitlements`: the keys of the member and the date of the last check, own data only, with the ability `entitlements:read` (not in the default set of a new token). The member list of an access group returns the source of each membership (`sources`: `manual`, `by_rule`, or both), never a key.
19. The access group manager shows the keys of a group ("Members also come from: ...") with an editor, and the source of each member. The profile settings show "What your account gives access to": the keys and the date of the last check.
20. Personal data: the keys and the date, shown to the member, in the member export (with where each membership comes from), deleted with the account and with the link. The community export lists the keys of the rules (a setting of the community). The privacy policy (section 2, item 3) needs one more sentence: the owner has not approved the words yet, so this pull request does not change the policy.
21. The log holds cuids, the issuer, the reason of a refusal and counts. Never a token, a subject, a key list or an address.

## Consequences

1. A person who buys at the provider gets the spaces within seconds (the change call, then a queued read). If the call is lost, the nightly read is at most a day late. If the provider is down, nobody loses access until it answers: a failure never removes a rule membership.
2. The worker must run (`SELF_HOSTING.md`): the reads and the sync of a rule change are queued jobs. Without a worker the sign-in still works and the rules wait.
3. The row of a membership has two sources. Code that lists "who is in this group" still reads the row; code that adds or removes a member must go through `AccessGroupMemberships`.
4. A key that the provider renames stops giving the group: the manager changes the rule. The platform cannot tell.
5. Turning the feature off removes the access that rules gave at the next nightly run, not at once. An operator who turns it off by mistake restores everything with the next read (a sign-in, a join, a change call or the nightly run).
6. The `jti` store is the cache. A cache that is emptied forgets the `jti`s: a replay inside the five minutes of validity would then queue one more read, which reads the truth and changes nothing that is not already true.

## Alternatives considered

1. A refresh token per member, read once a day (the first design): a secret per member in the database, access that stays up to a day after a plan ends, and it stops working when the member removes the consent. The client credentials read and the change calls remove all three.
2. Trusting the content of the change call: refused. A forged or replayed call could then give access. The call only says "read again".
3. A rule that names a product: refused. The platform stays generic: the manager chooses the key.
4. A join rule tied to the sign-in with the provider (everyone who signs in joins the community): dropped by the owner. A person joins by the general ways (an invitation, an invite link, the "people can join by themselves" setting), and the sync runs when they do.
5. One row per source (two rows per member and group): it would change the counts and the reading rule. Two flags on one row keep both.
6. A table for the `jti`s with a prune step: the cache gives the same atomic "add if absent" with a time to live and needs no table.
