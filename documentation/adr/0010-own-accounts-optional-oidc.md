# 0010. Own accounts, the Knowii account as an optional OpenID Connect provider

- Status: Accepted
- Date: 2026-11-09

## Context

The Knowii account holds the business side (customers, purchases, entitlements). The platform hosts many communities and can be self-hosted, so it cannot depend on the Knowii account.

## Decision

1. The platform keeps its own accounts (Jetstream, Fortify): email and password, verification, 2FA.
2. "Sign in with the Knowii account" is a generic OpenID Connect client, enabled by instance configuration (MVP M2). The first sign-in links to the platform account with the same verified email; after that, the link uses the stable OpenID Connect subject.
3. Entitlements from the Knowii account (`knowii.master`, `course.<id>`...) feed access groups of the Knowii Community only.

## Consequences

1. Self-hosted instances run without the Knowii account.
2. Email changes and account deletions on either side must not break the link (subject, not email).

## Alternatives considered

1. The Knowii account as the only way in: ties every community to DeveloPassion's business system.
