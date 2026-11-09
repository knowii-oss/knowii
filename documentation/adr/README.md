# Architecture decision records

An architecture decision record (ADR) is a short document that records one decision that shapes the code: the context, the decision, and its consequences. ADRs keep the code from drifting: a new contributor (or agent) reads why things are the way they are before changing them.

## When to write an ADR

Write an ADR in the same pull request when a change:

1. Adds a model or a concept to the domain, or changes how concepts relate (for example space groups and spaces).
2. Adds or replaces a dependency that shapes the code (framework, UI kit, editor, storage, search engine).
3. Adds a pattern that others must follow (a new kind of channel, a new way to render content, a new storage layout).
4. Changes or breaks a [rule](../conventions/rules.md).
5. Has a security, privacy or data-loss impact (authentication, CSP, uploads, backups).

Do not write an ADR for a bug fix, a refactor that keeps the structure, or a feature that only follows the existing patterns.

## How

1. Copy [`template.md`](template.md) to `NNNN-<kebab-case-title>.md`, with the next free number.
2. Status `Accepted` when the pull request is merged. A decision that is replaced stays in place with the status `Superseded by NNNN`. Never delete or renumber an ADR.
3. Keep it short: one page. Link the issue and the pull request.
4. Add it to the index below.

## Index

| ADR                                            | Decision                                                                | Status   |
| ---------------------------------------------- | ----------------------------------------------------------------------- | -------- |
| [0001](0001-record-architecture-decisions.md)  | Record architecture decisions                                           | Accepted |
| [0002](0002-laravel-inertia-react-monolith.md) | Laravel, Inertia and React in one Nx monorepo                           | Accepted |
| [0003](0003-own-ui-components.md)              | Own UI components on Tailwind CSS and Headless UI                       | Accepted |
| [0004](0004-multi-community-scoping.md)        | Every piece of data is scoped to a community                            | Accepted |
| [0005](0005-cuid2-public-identifiers.md)       | cuid2 public identifiers, slugs for page URLs                           | Accepted |
| [0006](0006-actions-behind-contracts.md)       | Changes go through actions behind contracts, the API is complete        | Accepted |
| [0007](0007-strict-content-security-policy.md) | Strict Content Security Policy                                          | Accepted |
| [0008](0008-postgresql-only.md)                | PostgreSQL only                                                         | Accepted |
| [0009](0009-real-time-through-reverb.md)       | Real time through Reverb                                                | Accepted |
| [0010](0010-own-accounts-optional-oidc.md)     | Own accounts, the Knowii account as an optional OpenID Connect provider | Accepted |
| [0011](0011-rest-api-design-guide.md)          | The REST API follows the NBB REST API Design Guide                      | Accepted |
