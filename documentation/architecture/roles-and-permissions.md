# Roles and permissions

Who can do what in a community ([ADR 0017](../adr/0017-roles-with-configurable-moderator-rights.md)). The policies in `app/Policies` hold the rules; `App\Support\CommunityRoles` applies the moderator rights. The UI only hides controls: the actions check every permission again.

## Roles

| Role      | Who                                                   |
| --------- | ----------------------------------------------------- |
| Owner     | The member who owns the community (one per community) |
| Admin     | Members that the owner made admins                    |
| Moderator | Members with the moderator role                       |
| Member    | Every other member of the community                   |
| Outsider  | A signed-in user who is not a member                  |
| Visitor   | Not signed in (public spaces, M2)                     |

## Moderator rights

The owner and the admins switch these rights in the community settings ("Moderator rights"). Owners and admins always have them.

| Right            | Default | What moderators can do with it                                    |
| ---------------- | ------- | ----------------------------------------------------------------- |
| `manage_spaces`  | off     | Create, edit, move and delete space groups and spaces (all types) |
| `manage_members` | off     | Add members, change roles below their own, remove members (#1242) |
| `pin_posts`      | on      | Pin and unpin posts                                               |
| `delete_content` | on      | Delete the posts and comments of other members                    |

## Abilities

"Moderator (right)" means: moderators, when the community switched that right on.

| Ability                                 | Policy                                                    | Owner  | Admin  | Moderator                           | Member     | Outsider | Visitor |
| --------------------------------------- | --------------------------------------------------------- | ------ | ------ | ----------------------------------- | ---------- | -------- | ------- |
| See the community                       | `CommunityPolicy::view`                                   | yes    | yes    | yes                                 | yes        | no       | no      |
| See the details page and the team       | `CommunityPolicy::view`                                   | yes    | yes    | yes                                 | yes        | no       | no      |
| Update the community                    | `CommunityPolicy::update`                                 | yes    | no     | no                                  | no         | no       | no      |
| Delete the community                    | `CommunityPolicy::delete`                                 | yes    | no     | no                                  | no         | no       | no      |
| Community settings                      | `CommunityPolicy::manage`                                 | yes    | yes    | no                                  | no         | no       | no      |
| Switch the moderator rights             | `CommunityPolicy::manageModeratorPermissions`             | yes    | yes    | no                                  | no         | no       | no      |
| Manage space groups and spaces          | `CommunityPolicy::manageSpaces`                           | yes    | yes    | `manage_spaces`                     | no         | no       | no      |
| List the members and their roles        | `CommunityPolicy::view`                                   | yes    | yes    | yes                                 | yes        | no       | no      |
| Invite, change and remove members       | `CommunityPolicy::add/update/removeCommunityMember`       | yes    | yes    | `manage_members`                    | no         | no       | no      |
| Upload images                           | `CommunityPolicy::uploadImage`                            | yes    | yes    | yes                                 | yes        | no       | no      |
| Read a members-only space               | `SpacePolicy::view`                                       | yes    | yes    | yes                                 | yes        | no       | no      |
| Read an "access groups only" space      | `SpacePolicy::view`                                       | yes    | yes    | in a group                          | in a group | no       | no      |
| Create, rename and delete access groups | `CommunityPolicy::manageAccessGroups`                     | yes    | yes    | no                                  | no         | no       | no      |
| Add and remove access group members     | `CommunityPolicy::manageAccessGroupMembers`               | yes    | yes    | `manage_members`                    | no         | no       | no      |
| See the access groups                   | `CommunityPolicy::viewAccessGroups`                       | yes    | yes    | `manage_members` or `manage_spaces` | no         | no       | no      |
| Read a public space                     | `SpacePolicy::view`                                       | yes    | yes    | yes                                 | yes        | yes      | yes     |
| Post where members post                 | `SpacePolicy::contribute` (needs `view`)                  | yes    | yes    | yes                                 | yes        | no       | no      |
| Post where only moderators post         | `SpacePolicy::contribute`                                 | yes    | yes    | yes (role)                          | no         | no       | no      |
| Delete the posts and comments of others | `SpacePolicy::moderate`                                   | yes    | yes    | `delete_content`                    | no         | no       | no      |
| Pin a post                              | `PostPolicy::pin`                                         | yes    | yes    | `pin_posts`                         | no         | no       | no      |
| Edit one's own post or comment          | `PostPolicy::update`, `CommentPolicy::update`             | author | author | author                              | author     | no       | no      |
| Comment and react                       | `PostPolicy::comment`, `CommunityResourcePolicy::comment` | yes    | yes    | yes                                 | yes        | no       | no      |

## Role hierarchy

Member management (#1242) adds a rule on top of the abilities above. `CommunityRoles::canActOn()` and `canGrant()` apply it in the member actions.

1. The ranks are owner 4, admin 3, moderator 2, member 1.
2. A user acts only on a member with a lower rank: nobody changes or removes the owner, and nobody changes their own role.
3. A user gives only a role with a lower rank than their own: only the owner makes admins, admins make moderators and members, moderators with `manage_members` invite and remove members only.
4. The same rule applies to invitations: the role of an invitation is lower than the role of the person who sends or cancels it.

| Who                             | Acts on                     | Gives the roles          |
| ------------------------------- | --------------------------- | ------------------------ |
| Owner                           | admins, moderators, members | admin, moderator, member |
| Admin                           | moderators, members         | moderator, member        |
| Moderator with `manage_members` | members                     | member                   |

Invitations ([ADR 0019](../adr/0019-community-invitations-by-email.md)): the email of an invitation has a signed link, valid for 7 days, that works only for an account with the invited email address.

## Per space type

The rules above are the same for every space type (discussion, chat, pages, course, resources). What changes per type is the content that `contribute` adds: posts, chat messages, pages, lessons or resources.

Access groups ([ADR 0025](../adr/0025-access-groups.md)): a space with the `groups` visibility opens to the members of its access groups. The owner and the admins see every space. A member who cannot read a space cannot post in it, whatever the posting policy. A member removed from the community leaves its groups.

## Later

1. Moderators of one space (a group that moderates a space).
2. Leaving a community, and transferring the ownership of a community.
3. Show a restricted space as locked to the members outside its groups (#869, #1241).
4. Add the buyers of a product to a group automatically (Knowii account entitlements, ADR 0010).
