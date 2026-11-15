# 0041. Access groups can open a whole space group

- Status: Accepted
- Date: 2026-11-15
- Issue: #1422
- Pull request: #1469

## Context

[ADR 0025](0025-access-groups.md) lets an access group open spaces one by one (`access_group_space`). The Knowii Community gives access by space group instead: a group of members gets every space of a space group, and a new space in that space group must be open to them at once. With one link per space, a manager has to remember to add the group to each new space. A space that nobody remembers stays closed, or a mistake leaves a paid space open to the wrong people.

Moving a space to another space group is also a way to change who can read it. This must be clear to the manager before the move.

## Decision

1. Table: `access_group_space_group` links an access group to a space group. Explicit foreign keys on both sides, deleted with either side (ADR 0014), and a unique pair, like `access_group_space`. The actions check that both belong to the same community. The database does not.
2. Reading rule: a space with the visibility `groups` is open to a member who is in an access group of the space itself, or in an access group of its space group. The rule is read when the space is read, never copied to the spaces. So a space added later to the space group is open at once, and a space moved out of it is closed at once. A space with another visibility is not changed. The owner and the admins still see every space.
3. One place: `Space::openingAccessGroupIds()` lists the access groups that open a space (its own and the ones of its space group). `User::isInAccessGroupOf()` uses it, and `SpacePolicy::view` uses that. Every read (navigation, pages, posts, comments, chat, resources, materials, API, broadcast channels, online members) already goes through `SpacePolicy::view` (ADR 0025), so none has its own copy of the rule. The navigation loads the access groups of the space groups and of the spaces with the tree, so it adds no query per space.
4. A `groups` space needs at least one group that opens it: a group given to the space, or a group given to its space group. A space with no group at all stays visible to the owner and the admins only. A save that would leave a `groups` space with no group at all is refused with a validation issue, a move to a space group without access groups included (when the space has no group of its own).
5. API, with the token abilities `groups:read` and `groups:write` (ADR 0022):
   - `GET`, `PUT /communities/{community}/access-groups/{group}/space-groups`
   - `GET`, `PUT /communities/{community}/space-groups/{spaceGroup}/access-groups`
   - `PUT` replaces the whole list (`space_groups` or `access_groups`: cuids). An empty list removes every link. A cuid of another community gives a validation issue (400). The two `PUT` routes change the same links, so a sync can use the side that suits it.
   - Who: the same people who can give a space its groups, the ones with `CommunityPolicy::manageSpaces` (the owner, the admins and the moderators with the `manage_spaces` right). Reading needs `CommunityPolicy::viewAccessGroups`. A moderator changes the links of a space group only when they can read every `groups` space of it (403 otherwise), in line with #1325: without this rule, a moderator gives an access group of their own a closed space group and reads it. Only the space groups that gain or lose a link are checked. The owner and the admins read every space, so the rule never stops them.
6. Moving a space (`PATCH` of its `space_group_cuid`) changes who reads it. The edit space form shows, before the save, the access groups that gain the space and the ones that lose it, and asks to confirm. The server does not need a new rule: the next read follows the new space group.
7. The settings of a space show which access groups open it and where each comes from: "this space" or "its space group".
8. Deleting an access group or a space group deletes its links. The community export lists the space groups of each access group.
9. Changes broadcast `community.access_groups.changed` and `community.space_tree.changed` (ADR 0025).

## Consequences

1. The mapping of a community with many space groups is simpler: a few access groups per space group, set once.
2. The reading rule has two sources. Anything that lists "who can read this space" must use `Space::openingAccessGroupIds()`, not only `access_group_space`.
3. A manager can open many spaces with one change. The warning on a move and the roles table keep the decision visible.
4. The member count on a group of the API is unchanged. The space count is only the spaces linked one by one: a group also has `space_groups_count`.

## Alternatives considered

1. Copy the group of the space group to every space when it is linked: a new space would need a second step, a moved space would keep the old groups, and a copy can drift.
2. A polymorphic link (`accessible_type`, `accessible_id`): refused by ADR 0014.
3. Space groups with their own visibility: a larger change than needed, and the visibility of a space would then depend on two settings.
