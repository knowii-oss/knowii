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

| ADR                                                                    | Decision                                                                | Status   |
| ---------------------------------------------------------------------- | ----------------------------------------------------------------------- | -------- |
| [0001](0001-record-architecture-decisions.md)                          | Record architecture decisions                                           | Accepted |
| [0002](0002-laravel-inertia-react-monolith.md)                         | Laravel, Inertia and React in one Nx monorepo                           | Accepted |
| [0003](0003-own-ui-components.md)                                      | Own UI components on Tailwind CSS and Headless UI                       | Accepted |
| [0004](0004-multi-community-scoping.md)                                | Every piece of data is scoped to a community                            | Accepted |
| [0005](0005-cuid2-public-identifiers.md)                               | cuid2 public identifiers, slugs for page URLs                           | Accepted |
| [0006](0006-actions-behind-contracts.md)                               | Changes go through actions behind contracts, the API is complete        | Accepted |
| [0007](0007-strict-content-security-policy.md)                         | Strict Content Security Policy                                          | Accepted |
| [0008](0008-postgresql-only.md)                                        | PostgreSQL only                                                         | Accepted |
| [0009](0009-real-time-through-reverb.md)                               | Real time through Reverb                                                | Accepted |
| [0010](0010-own-accounts-optional-oidc.md)                             | Own accounts, the Knowii account as an optional OpenID Connect provider | Accepted |
| [0011](0011-rest-api-design-guide.md)                                  | The REST API follows the NBB REST API Design Guide                      | Accepted |
| [0012](0012-space-groups-and-typed-spaces.md)                          | Space groups and typed spaces structure a community                     | Accepted |
| [0013](0013-markdown-rendered-on-the-server.md)                        | Member content is Markdown, rendered to safe HTML on the server         | Accepted |
| [0014](0014-explicit-foreign-keys-instead-of-polymorphic-relations.md) | Explicit foreign keys instead of polymorphic relations                  | Accepted |
| [0015](0015-resources-space-type.md)                                   | Resource collections become a `resources` space type                    | Accepted |
| [0016](0016-resource-images-stored-by-knowii.md)                       | Resource images are stored by Knowii, never hotlinked                   | Accepted |
| [0017](0017-roles-with-configurable-moderator-rights.md)               | Four fixed roles, with moderator rights configured per community        | Accepted |
| [0018](0018-content-capture.md)                                        | Queued server capture of links, full captures from a browser extension  | Accepted |
| [0019](0019-community-invitations-by-email.md)                         | Community invitations by email, for one address, with a signed link     | Accepted |
| [0020](0020-members-choose-visible-profile-fields.md)                  | Members choose which profile fields the other members see               | Accepted |
| [0021](0021-avatars-stored-by-knowii-on-the-user.md)                   | Avatars are square images stored by Knowii, on the user                 | Accepted |
| [0022](0022-api-token-abilities.md)                                    | API token abilities, read and write per area, checked on every route    | Accepted |
| [0023](0023-sign-in-links.md)                                          | Sign-in links by email, used once, confirmed with a button              | Accepted |
| [0024](0024-account-deletion-removes-personal-data.md)                 | Deleting an account removes the personal data, the content stays        | Accepted |
| [0025](0025-access-groups.md)                                          | Access groups open spaces to some members, by hand or by an API sync    | Accepted |
| [0026](0026-link-spaces.md)                                            | Navigation links are a space type                                       | Accepted |
| [0027](0027-presence-channels.md)                                      | Presence through Reverb presence channels, hidden members never join    | Accepted |
