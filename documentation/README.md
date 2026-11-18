# Knowii technical documentation

This folder is the technical reference of the Knowii Platform. It describes how the code is built and why. Read it before you change the structure of the code: a new model, a new space type, a new channel, a new dependency, a new pattern.

## Contents

| Document                                                             | What it covers                                                                              |
| -------------------------------------------------------------------- | ------------------------------------------------------------------------------------------- |
| [Architecture overview](architecture/overview.md)                    | The system, its runtime processes, the repository layout and the environments               |
| [High-level design](architecture/high-level-design.md)               | The domain model, multi-community scoping, request flow, authorization, real time, security |
| [Back-end design](architecture/backend.md)                           | Actions and contracts, API controllers, errors, events, WebSocket channels                  |
| [Roles and permissions](architecture/roles-and-permissions.md)       | Who can do what in a community: roles, moderator rights, abilities per policy               |
| [External account service](architecture/external-account-service.md) | The contract of a sign-in provider: sign-in, new sign-in, entitlements, change calls        |
| [Front-end design](architecture/frontend.md)                         | Pages, UI components, themes, the API client, `useSocket`, the CSP rules for the front-end  |
| [Naming conventions](conventions/naming.md)                          | Names of classes, files, routes, tables, events, channels, branches and commits             |
| [Rules](conventions/rules.md)                                        | The rules that every change must follow, and the definition of done                         |
| [Architecture decision records](adr/README.md)                       | The decisions that shape the code, one file per decision                                    |
| [Designs](design/README.md)                                          | What a feature does for the people who use it, as the owner approved it, one folder each    |
| [Reviews](reviews/README.md)                                         | The reports of the reviews of the whole application, one folder per review                  |
| [Community export runbook](runbooks/community-export.md)             | The steps for the operator to export the data of a community on the request of its owner    |
| [Member data export runbook](runbooks/member-data-export.md)         | The steps for the operator to answer a member who asks for a copy of their data             |
| [Vulnerability report runbook](runbooks/vulnerability-report.md)     | The steps for a maintainer from a security report to the published fix and its advisory     |
| [API requests (Bruno)](api/README.md)                                | The Bruno collection that documents and exercises the REST API                              |

Other documents at the root of the repository:

1. [`AGENTS.md`](../AGENTS.md): the short version of the conventions, for contributors and AI agents.
2. [`CONTRIBUTING.md`](../CONTRIBUTING.md): the development environment, the workflow, the release process.
3. [`SELF_HOSTING.md`](../SELF_HOSTING.md): how to run your own instance.

The OpenAPI documentation of the REST API is generated from the code by Scramble: `/docs/api` (interactive) and `/docs/api.json` (OpenAPI document).

## Keep it current

The documentation changes in the same pull request as the code:

1. A structural decision (see [when to write an ADR](adr/README.md#when-to-write-an-adr)) adds an ADR.
2. A change to the domain model, a flow, or a runtime process updates the architecture documents.
3. A new or changed API endpoint updates the Bruno collection and the Pest tests, and the generated OpenAPI document must still describe it correctly. Two tests fail when this is forgotten: `OpenApiDocumentTest` (every route documented, with its success response and its request body) and `BrunoCollectionTest` (every route has a Bruno request).
4. A new convention updates [naming](conventions/naming.md) or [rules](conventions/rules.md), and `AGENTS.md` when agents must know it.
5. A new feature that needs a design adds it to [designs](design/README.md) before the build, and a change of the behaviour updates the design.

A pull request that leaves the documentation wrong is not done.
