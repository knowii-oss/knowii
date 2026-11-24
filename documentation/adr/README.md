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

| ADR                                                                                            | Decision                                                                         | Status   |
| ---------------------------------------------------------------------------------------------- | -------------------------------------------------------------------------------- | -------- |
| [0001](0001-record-architecture-decisions.md)                                                  | Record architecture decisions                                                    | Accepted |
| [0002](0002-laravel-inertia-react-monolith.md)                                                 | Laravel, Inertia and React in one Nx monorepo                                    | Accepted |
| [0003](0003-own-ui-components.md)                                                              | Own UI components on Tailwind CSS and Headless UI                                | Accepted |
| [0004](0004-multi-community-scoping.md)                                                        | Every piece of data is scoped to a community                                     | Accepted |
| [0005](0005-cuid2-public-identifiers.md)                                                       | cuid2 public identifiers, slugs for page URLs                                    | Accepted |
| [0006](0006-actions-behind-contracts.md)                                                       | Changes go through actions behind contracts, the API is complete                 | Accepted |
| [0007](0007-strict-content-security-policy.md)                                                 | Strict Content Security Policy                                                   | Accepted |
| [0008](0008-postgresql-only.md)                                                                | PostgreSQL only                                                                  | Accepted |
| [0009](0009-real-time-through-reverb.md)                                                       | Real time through Reverb                                                         | Accepted |
| [0010](0010-own-accounts-optional-oidc.md)                                                     | Own accounts, the Knowii account as an optional OpenID Connect provider          | Accepted |
| [0011](0011-rest-api-design-guide.md)                                                          | The REST API follows the NBB REST API Design Guide                               | Accepted |
| [0012](0012-space-groups-and-typed-spaces.md)                                                  | Space groups and typed spaces structure a community                              | Accepted |
| [0013](0013-markdown-rendered-on-the-server.md)                                                | Member content is Markdown, rendered to safe HTML on the server                  | Accepted |
| [0014](0014-explicit-foreign-keys-instead-of-polymorphic-relations.md)                         | Explicit foreign keys instead of polymorphic relations                           | Accepted |
| [0015](0015-resources-space-type.md)                                                           | Resource collections become a `resources` space type                             | Accepted |
| [0016](0016-resource-images-stored-by-knowii.md)                                               | Resource images are stored by Knowii, never hotlinked                            | Accepted |
| [0017](0017-roles-with-configurable-moderator-rights.md)                                       | Four fixed roles, with moderator rights configured per community                 | Accepted |
| [0018](0018-content-capture.md)                                                                | Queued server capture of links, full captures from a browser extension           | Accepted |
| [0019](0019-community-invitations-by-email.md)                                                 | Community invitations by email, for one address, with a signed link              | Accepted |
| [0020](0020-members-choose-visible-profile-fields.md)                                          | Members choose which profile fields the other members see                        | Accepted |
| [0021](0021-avatars-stored-by-knowii-on-the-user.md)                                           | Avatars are square images stored by Knowii, on the user                          | Accepted |
| [0022](0022-api-token-abilities.md)                                                            | API token abilities, read and write per area, checked on every route             | Accepted |
| [0023](0023-sign-in-links.md)                                                                  | Sign-in links by email, used once, confirmed with a button                       | Accepted |
| [0024](0024-account-deletion-removes-personal-data.md)                                         | Deleting an account removes the personal data, the content stays                 | Accepted |
| [0025](0025-access-groups.md)                                                                  | Access groups open spaces to some members, by hand or by an API sync             | Accepted |
| [0026](0026-link-spaces.md)                                                                    | Navigation links are a space type                                                | Accepted |
| [0027](0027-presence-channels.md)                                                              | Presence through Reverb presence channels, hidden members never join             | Accepted |
| [0028](0028-direct-messages.md)                                                                | Direct messages are chat messages in a conversation                              | Accepted |
| [0029](0029-course-spaces.md)                                                                  | Lessons are pages, sections are rows of the same list                            | Accepted |
| [0030](0030-retention-periods-enforced-by-scheduled-tasks.md)                                  | Retention periods are configuration, and scheduled tasks apply them              | Accepted |
| [0031](0031-videos-in-member-content.md)                                                       | A video link alone on its line becomes a click-to-load player                    | Accepted |
| [0032](0032-reactions-with-any-emoji.md)                                                       | Reactions use any emoji of an allow-list, chosen in our own picker               | Accepted |
| [0033](0033-unique-names-for-shared-communities.md)                                            | The name of a shared community is unique                                         | Accepted |
| [0034](0034-course-progress-per-member.md)                                                     | Course progress is one row per lesson done, private to the member                | Accepted |
| [0035](0035-ownership-transfer.md)                                                             | The owner transfers a community to an admin and becomes an admin                 | Accepted |
| [0036](0036-reserved-community-names.md)                                                       | Some community names are reserved for the administrators of the instance         | Accepted |
| [0037](0037-course-materials.md)                                                               | Course materials are private files and links, added by the managers              | Accepted |
| [0038](0038-lesson-quizzes.md)                                                                 | A lesson has at most one quiz, graded by the server, one result a member         | Accepted |
| [0039](0039-images-that-follow-each-other-form-a-gallery.md)                                   | Images that follow each other form a gallery, with a viewer                      | Accepted |
| [0040](0040-stored-sizes-of-uploaded-images.md)                                                | Uploaded images have stored smaller sizes, and the HTML uses srcset              | Accepted |
| [0041](0041-access-groups-open-space-groups.md)                                                | Access groups can open a whole space group, new spaces included                  | Accepted |
| [0042](0042-notifications.md)                                                                  | Notifications: one table, one action, four levels                                | Accepted |
| [0043](0043-delete-content-with-the-account.md)                                                | A member can delete their posts, comments and messages with the account          | Accepted |
| [0044](0044-public-spaces-for-visitors.md)                                                     | Public spaces can be read by visitors, without an account                        | Accepted |
| [0045](0045-openid-connect-sign-in.md)                                                         | Sign in with an OpenID Connect provider, linked by subject                       | Accepted |
| [0046](0046-production-image-and-compose.md)                                                   | One production image, one Compose project with five services                     | Accepted |
| [0047](0047-entitlement-rules-feed-access-groups.md)                                           | The entitlements of the sign-in provider feed the access groups                  | Accepted |
| [0048](0048-where-i-am.md)                                                                     | Where I am: time zone, last visit, visits of spaces, "Catch up" data             | Accepted |
| [0049](0049-component-toolkit-storybook-stories-as-tests.md)                                   | Component toolkit: Storybook, stories as tests                                   | Accepted |
| [0050](0050-a-delete-keeps-the-replies-of-other-people.md)                                     | A delete by the author keeps the replies of other people                         | Accepted |
| [0051](0051-security-scan-of-the-php-code-with-psalm-taint-analysis.md)                        | Scan the PHP code for security problems with the taint analysis of Psalm         | Accepted |
| [0052](0052-account-deletion-after-a-new-sign-in.md)                                           | An account without a password deletes itself after a new sign-in                 | Accepted |
| [0053](0053-events-a-space-type-with-its-own-table.md)                                         | Events: a space type with its own table                                          | Accepted |
| [0054](0054-email-delivery-the-mail-queue-the-budget-bounces-and-the-one-click-stop.md)        | Email delivery: the mail queue, the budget, bounces and the one-click stop       | Accepted |
| [0055](0055-instance-values-come-from-configuration.md)                                        | The values of one instance come from configuration                               | Accepted |
| [0056](0056-the-member-directory-every-member-reads-the-member-list.md)                        | The member directory: every member reads the member list of the community        | Accepted |
| [0057](0057-the-landing-after-the-address-check-travels-in-the-link.md)                        | The landing after the address check travels in the link                          | Accepted |
| [0058](0058-a-change-of-the-email-address-applies-after-its-confirmation.md)                   | A change of the email address applies after its confirmation                     | Accepted |
| [0059](0059-the-first-proof-of-an-address-takes-the-account-over.md)                           | The first proof of an address takes the account over                             | Accepted |
| [0060](0060-member-tags-labels-on-members.md)                                                  | Member tags: labels on members, for the members or for the managers only         | Accepted |
| [0061](0061-invitations-carry-access-groups-and-tags-and-come-from-a-file.md)                  | Invitations carry access groups and tags, and many come from a file              | Accepted |
| [0062](0062-invite-links-a-shared-link-to-join-a-community-with-set-access-groups-and-tags.md) | Invite links: a shared link to join a community, with set access groups and tags | Accepted |
| [0063](0063-a-member-chooses-to-be-in-the-member-list-of-their-communities.md)                 | A member chooses to be in the member list of their communities                   | Accepted |
| [0064](0064-the-session-and-the-statistics-keep-no-address-that-holds-a-secret.md)             | The session and the statistics keep no address that holds a secret               | Accepted |
| [0065](0065-the-sign-up-makes-the-account-when-the-link-of-its-mail-is-opened.md)              | The sign-up makes the account when the link of its mail is opened                | Accepted |
| [0066](0066-the-session-version-ends-the-other-sessions-of-an-account.md)                      | The session version ends the other sessions of an account                        | Accepted |
| [0067](0067-the-tries-of-the-current-password-count-for-what-signed-the-request-in.md)         | The tries of the current password count for what signed the request in           | Accepted |
| [0068](0068-the-cookies-belong-to-one-host.md)                                                 | The cookies belong to one host                                                   | Accepted |
| [0069](0069-names-are-one-line-of-plain-text-and-mails-print-plain-text.md)                    | Names are one line of plain text, and mails print plain text                     | Accepted |
