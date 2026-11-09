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

| Ability                                 | Policy                                                    | Owner  | Admin  | Moderator        | Member | Outsider | Visitor |
| --------------------------------------- | --------------------------------------------------------- | ------ | ------ | ---------------- | ------ | -------- | ------- |
| See the community                       | `CommunityPolicy::view`                                   | yes    | yes    | yes              | yes    | no       | no      |
| Update the community                    | `CommunityPolicy::update`                                 | yes    | no     | no               | no     | no       | no      |
| Delete the community                    | `CommunityPolicy::delete`                                 | yes    | no     | no               | no     | no       | no      |
| Community settings                      | `CommunityPolicy::manage`                                 | yes    | yes    | no               | no     | no       | no      |
| Switch the moderator rights             | `CommunityPolicy::manageModeratorPermissions`             | yes    | yes    | no               | no     | no       | no      |
| Manage space groups and spaces          | `CommunityPolicy::manageSpaces`                           | yes    | yes    | `manage_spaces`  | no     | no       | no      |
| Add, change and remove members          | `CommunityPolicy::add/update/removeCommunityMember`       | yes    | yes    | `manage_members` | no     | no       | no      |
| Upload images                           | `CommunityPolicy::uploadImage`                            | yes    | yes    | yes              | yes    | no       | no      |
| Read a members-only space               | `SpacePolicy::view`                                       | yes    | yes    | yes              | yes    | no       | no      |
| Read a public space                     | `SpacePolicy::view`                                       | yes    | yes    | yes              | yes    | yes      | yes     |
| Post where members post                 | `SpacePolicy::contribute`                                 | yes    | yes    | yes              | yes    | no       | no      |
| Post where only moderators post         | `SpacePolicy::contribute`                                 | yes    | yes    | yes (role)       | no     | no       | no      |
| Delete the posts and comments of others | `SpacePolicy::moderate`                                   | yes    | yes    | `delete_content` | no     | no       | no      |
| Pin a post                              | `PostPolicy::pin`                                         | yes    | yes    | `pin_posts`      | no     | no       | no      |
| Edit one's own post or comment          | `PostPolicy::update`, `CommentPolicy::update`             | author | author | author           | author | no       | no      |
| Comment and react                       | `PostPolicy::comment`, `CommunityResourcePolicy::comment` | yes    | yes    | yes              | yes    | no       | no      |

## Per space type

The rules above are the same for every space type (discussion, chat, pages, course, resources). What changes per type is the content that `contribute` adds: posts, chat messages, pages, lessons or resources.

## Later

1. Access groups (#1241): spaces visible to some groups, and moderators of one space.
2. The role hierarchy of member management (#1242): nobody acts on the owner; only the owner manages admins; nobody acts on a member with their own role or a higher one.
