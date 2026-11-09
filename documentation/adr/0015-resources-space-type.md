# 0015. Resource collections become a `resources` space type

- Status: Accepted
- Date: 2026-11-09
- Issue: #1126
- Amends: [0012](0012-space-groups-and-typed-spaces.md) (decision 2: "Resource collections stay a separate concept for now")

## Context

Resource collections (curated lists of web articles) are older than spaces. They have their own model, actions, controller, events, WebSocket channel, permissions (moderators create, rename and delete them), page URLs and API. Since [ADR 0012](0012-space-groups-and-typed-spaces.md), a community is organized in space groups and typed spaces, with one navigation, one permission model (visibility, posting policy) and one settings screen.

Two parallel structures give two navigations, two permission models and two settings screens. #1126 asked for a settings screen for collections, and #876 to #879 for a collection API: each one would duplicate what spaces already have.

There is no production instance, so no backward compatibility is needed (decision of 2026-11-09).

## Decision

1. A new space type, `resources`, holds the community resources (curated links; text articles today). The type never changes, like the other types.
2. `community_resources.collection_id` becomes `space_id` (foreign key to `spaces`, cascade on delete). `community_id` stays (denormalized, [ADR 0004](0004-multi-community-scoping.md)). A resource is unique in its space: `unique(space_id, resource_id)`. The actions refuse a space of another type.
3. The `community_resource_collections` table, its model, actions, contracts, controllers, events, WebSocket channel, policy abilities, API token permissions and front-end components are removed.
4. The migration moves the data: for each community with collections, it adds a "Resources" space group at the end of the navigation, and one `resources` space per collection (same name, description and slug; a suffix when a space already holds the slug), visible to members, posting policy `moderators`. The resources move to their new space. `down()` rebuilds the collections from the `resources` spaces, so the change is reversible without a backup.
5. Permissions follow the space model: `SpacePolicy::contribute` (the posting policy) decides who adds a resource, `CommunityPolicy::manageSpaces` (owner and admins) who creates, edits, moves and deletes a `resources` space. The `createResourceCollection`, `updateResourceCollection`, `deleteResourceCollection`, `createResource`, `updateResource` and `deleteResource` abilities of `CommunityPolicy` are removed.
6. The API lives under the space ([ADR 0011](0011-rest-api-design-guide.md)): `POST /communities/{community}/spaces/{space}/resources` creates a resource from a URL (a text article today), `GET .../resources` lists them (#877), `GET .../resources/{resource}` returns one (#876). The space endpoints replace the collection list and detail endpoints (#879, #878).
7. Pages: `/community/{c}/space/{s}` shows the resources of a `resources` space, `/community/{c}/space/{s}/resource/{slug}` one resource. The settings screen of a collection is the space settings (the space tree editor of the community settings page).
8. Real time: the resource events (`community.resource.created`, `updated`, `deleted`) go to the channel of the space only (`community.{c}.space.{s}`), which checks `SpacePolicy::view`. They no longer go to the public `communities` channel: a members-only space must not leak through it.

## Consequences

1. One navigation, one permission model and one settings screen for all the content of a community.
2. Moderators no longer create, rename or delete collections: owners and admins manage spaces. Moderators still add resources to the spaces that only moderators post in. Since [ADR 0017](0017-roles-with-configurable-moderator-rights.md), the community can give its moderators the "manage spaces" right.
3. Resource names (#925) follow the space rules: the slug is unique in the community and does not change on rename; two spaces can have the same name.
4. The community home page keeps a "Recent resources" list, limited to the `resources` spaces the member can see.
5. A future resource type (videos, books) is a new kind of resource in a `resources` space, not a new space type.

## Alternatives considered

1. Keep collections and add their own settings screen and API (#1126, #876 to #879 as planned): two navigations and two permission models to keep in sync.
2. Store resources as pages of a `pages` space: a resource is a link with metadata (level, cover image, extracted article), not a Markdown page.
