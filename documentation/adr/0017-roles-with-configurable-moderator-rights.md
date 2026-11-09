# 0017. Four fixed roles, with moderator rights configured per community

- Status: Accepted
- Date: 2026-11-09
- Issue: #1240

## Context

A community has four roles: the owner (implicit), admins, moderators and members. Each policy ability named the roles that pass, and nothing could change it. Community owners need moderators whose rights they decide: one community lets its moderators organize the spaces, another only wants them to remove spam. ADR 0015 also took the management of resource collections away from moderators.

The options were a custom-role system (any number of roles, each with any set of permissions) or fixed roles with a few switches.

## Decision

1. The four roles stay: owner, admin, moderator, member. No custom roles.
2. Each community stores four moderator rights (`communities.moderator_permissions`, `KnowiiModeratorPermission`). The owner and the admins switch them (`CommunityPolicy::manageModeratorPermissions`, `PATCH /communities/{community}/moderator-permissions`):
   1. `manage_spaces`: create, edit, move and delete space groups and spaces, of every type (no special case for resources spaces).
   2. `manage_members`: add members, change roles and remove members, within the role hierarchy (#1242).
   3. `pin_posts`: pin and unpin posts.
   4. `delete_content`: delete the posts and comments of other members (`SpacePolicy::moderate`).
3. Defaults (new and existing communities): `pin_posts` and `delete_content` on, `manage_spaces` and `manage_members` off. This keeps the behavior before this ADR.
4. The owner and the admins always have every moderator right. Only the owner updates and deletes the community. Members never have a moderator right.
5. Moderators act on the whole community. Moderators of a single space come with access groups (#1241).
6. Posting where only moderators post (`posting_policy: moderators`) depends on the role, not on a right.
7. `App\Support\CommunityRoles::allows($user, $community, $permission)` is the one place that applies points 2 to 4. Policies call it; they never test a role and a right themselves.
8. The member abilities (`addCommunityMember`, `updateCommunityMember`, `removeCommunityMember`) follow `manage_members`, so admins have them too. Who may act on whom (the role hierarchy) is checked by the member actions (#1242).

## Consequences

1. The settings page opens for owners, admins, and moderators with `manage_spaces` (they see the space editor only).
2. A new moderator right is a new `KnowiiModeratorPermission` case with its default, a label in `MODERATOR_PERMISSION_LABELS`, the policy that uses it, and a line in [roles and permissions](../architecture/roles-and-permissions.md).
3. `tests/Feature/PolicyTest.php` checks every right on and off, per role.

## Alternatives considered

1. Custom roles with free permission sets: a role editor, harder rules for members to understand, and more cases to test, for a need that four switches cover.
2. Per-space moderators now: they need the group model of access groups (#1241); building them twice is waste.
