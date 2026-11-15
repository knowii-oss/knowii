# Roles and permissions

Who can do what in a community ([ADR 0017](../adr/0017-roles-with-configurable-moderator-rights.md)). The policies in `app/Policies` hold the rules; `App\Support\CommunityRoles` applies the moderator rights. The UI only hides controls: the actions check every permission again.

## Roles

| Role      | Who                                                                                                         |
| --------- | ----------------------------------------------------------------------------------------------------------- |
| Owner     | The member who owns the community (one per community)                                                       |
| Admin     | Members that the owner made admins                                                                          |
| Moderator | Members with the moderator role                                                                             |
| Member    | Every other member of the community                                                                         |
| Outsider  | A signed-in user who is not a member                                                                        |
| Visitor   | Not signed in: reads the public spaces, except chat ([ADR 0044](../adr/0044-public-spaces-for-visitors.md)) |

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

| Ability                                   | Policy                                                    | Owner  | Admin  | Moderator                                    | Member     | Outsider          | Visitor                       |
| ----------------------------------------- | --------------------------------------------------------- | ------ | ------ | -------------------------------------------- | ---------- | ----------------- | ----------------------------- |
| See the community                         | `CommunityPolicy::view`                                   | yes    | yes    | yes                                          | yes        | no                | no                            |
| See the details page and the team         | `CommunityPolicy::view`                                   | yes    | yes    | yes                                          | yes        | no                | no                            |
| Update the community                      | `CommunityPolicy::update`                                 | yes    | no     | no                                           | no         | no                | no                            |
| Delete the community                      | `CommunityPolicy::delete`                                 | yes    | no     | no                                           | no         | no                | no                            |
| Community settings                        | `CommunityPolicy::manage`                                 | yes    | yes    | no                                           | no         | no                | no                            |
| Switch the moderator rights               | `CommunityPolicy::manageModeratorPermissions`             | yes    | yes    | no                                           | no         | no                | no                            |
| Manage space groups and spaces            | `CommunityPolicy::manageSpaces`                           | yes    | yes    | `manage_spaces`                              | no         | no                | no                            |
| List the members and their roles          | `CommunityPolicy::view`                                   | yes    | yes    | yes                                          | yes        | no                | no                            |
| Invite, change and remove members         | `CommunityPolicy::add/update/removeCommunityMember`       | yes    | yes    | `manage_members`                             | no         | no                | no                            |
| Transfer the ownership to an admin        | `CommunityPolicy::transferOwnership` (#1339)              | yes    | no     | no                                           | no         | no                | no                            |
| Leave the community                       | `CommunityPolicy::leave` (#1338)                          | no     | yes    | yes                                          | yes        | no                | no                            |
| Upload images                             | `CommunityPolicy::uploadImage`                            | yes    | yes    | yes                                          | yes        | no                | no                            |
| Read a members-only space                 | `SpacePolicy::view`                                       | yes    | yes    | yes                                          | yes        | no                | no                            |
| Read an "access groups only" space        | `SpacePolicy::view`                                       | yes    | yes    | in a group                                   | in a group | no                | no                            |
| Give an access group a whole space group  | `CommunityPolicy::manageSpaces` (#1422)                   | yes    | yes    | `manage_spaces`                              | no         | no                | no                            |
| Create, rename and delete access groups   | `CommunityPolicy::manageAccessGroups`                     | yes    | yes    | no                                           | no         | no                | no                            |
| Add and remove access group members       | `CommunityPolicy::manageAccessGroupMembers`               | yes    | yes    | `manage_members`                             | no         | no                | no                            |
| See the access groups                     | `CommunityPolicy::viewAccessGroups`                       | yes    | yes    | `manage_members` or `manage_spaces`          | no         | no                | no                            |
| Read a public space                       | `SpacePolicy::view`                                       | yes    | yes    | yes                                          | yes        | yes               | yes (not a chat space, #1387) |
| Post where members post                   | `SpacePolicy::contribute` (needs `view`)                  | yes    | yes    | yes                                          | yes        | no                | no                            |
| Post where only moderators post           | `SpacePolicy::contribute`                                 | yes    | yes    | yes (role)                                   | no         | no                | no                            |
| Delete the posts and comments of others   | `SpacePolicy::moderate` (needs `view`, #1325)             | yes    | yes    | `delete_content`                             | no         | no                | no                            |
| Pin a post                                | `PostPolicy::pin`                                         | yes    | yes    | `pin_posts`                                  | no         | no                | no                            |
| Mark lessons as done, read one's progress | `SpacePolicy::trackProgress` (needs `view`, #1304)        | yes    | yes    | yes                                          | yes        | no                | no                            |
| Create, edit, reorder and delete pages    | `SpacePolicy::managePages` (needs `view`)                 | yes    | yes    | yes (role)                                   | no         | no                | no                            |
| Read the list of course materials         | `SpacePolicy::view` (#1336, #1387)                        | yes    | yes    | yes                                          | yes        | in a public space | in a public space             |
| Download a course material                | `SpacePolicy::view` (#1336), signed in                    | yes    | yes    | yes                                          | yes        | in a public space | no                            |
| Add, change and delete course materials   | `SpacePolicy::managePages` (#1336)                        | yes    | yes    | yes (role)                                   | no         | no                | no                            |
| Read the questions of a lesson quiz       | `SpacePolicy::view` (#1335), signed in                    | yes    | yes    | yes                                          | yes        | in a public space | no                            |
| Answer a quiz, read one's own result      | `SpacePolicy::trackProgress` (#1335)                      | yes    | yes    | yes                                          | yes        | no                | no                            |
| Write and delete a lesson quiz            | `SpacePolicy::managePages` (#1335)                        | yes    | yes    | yes (role)                                   | no         | no                | no                            |
| Write in a chat space                     | `SpacePolicy::contribute` (posting policy)                | yes    | yes    | yes                                          | members    | no                | no                            |
| Edit or delete one's own chat message     | `ChatMessagePolicy::update`, `::delete`                   | author | author | author                                       | author     | no                | no                            |
| Delete the chat messages of others        | `ChatMessagePolicy::delete` (`SpacePolicy::moderate`)     | yes    | yes    | `delete_content`                             | no         | no                | no                            |
| Change a resource, capture it again       | `CommunityResourcePolicy::update`, `::capture` (members)  | yes    | yes    | curator or `manage_spaces`                   | curator    | no                | no                            |
| Delete a resource                         | `CommunityResourcePolicy::delete` (#324)                  | yes    | yes    | curator, `manage_spaces` or `delete_content` | curator    | no                | no                            |
| Edit one's own post or comment            | `PostPolicy::update`, `CommentPolicy::update`             | author | author | author                                       | author     | no                | no                            |
| Comment and react                         | `PostPolicy::comment`, `CommunityResourcePolicy::comment` | yes    | yes    | yes                                          | yes        | no                | no                            |
| Get a notification of a reply or mention  | `Notify` (#1474)                                          | yes    | yes    | yes                                          | yes        | no                | no                            |
| Read one's own notifications              | own rows only (#1474)                                     | yes    | yes    | yes                                          | yes        | no                | no                            |

Three rules hold for every ability (authorization audit, 2026-11-10):

1. The API needs a verified email address, like the web app (#1323).
2. A rule on authored content applies to members only: a curator or an author who left the community loses the rights on what they wrote (#1324).
3. Nobody moderates, changes or deletes a space that they cannot read: a moderator outside the groups of a groups space has no right on it (#1325, ADR 0025).

Visitors (#1387, [ADR 0044](../adr/0044-public-spaces-for-visitors.md)): a visitor reads the pages of a public space of type discussion, pages, course, resources and link, and nothing else. A chat space is never readable by a visitor, also when it is public (`SpacePolicy::view`). On a post, a comment, a page and a resource a visitor gets the name, the username and the avatar of the author, and never a member list, the team, the online members, the profile fields, the member count, who reacted (counts only), the names of access groups, the moderator rights, the direct message setting, the captured article of a resource, the progress in a course or the quiz. Any page of a `members` or `groups` space, and every other community page, sends a visitor to the sign-in page and back to the same address. The API reads that a visitor may call are listed in the ADR and in `documentation/api/README.md`.

Decided on purpose after the audit (Sébastien, 2026-11-10):

1. A public space is readable by everyone, also in a private community: the visibility of the space decides, not the one of the community.
2. Moderators with `manage_members` may add themselves to an access group, paid groups included (ADR 0025).
3. Group members can be added by email, so these moderators can check which member uses an address.

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

Notifications ([ADR 0042](../adr/0042-notifications.md), #1474): a member is told about a reply to their post, a reply to their comment or a mention only while they can view the item, with the same rule as the reading of the space (`SpacePolicy::view`). Nobody is told about their own action, and a member who blocked the author is not told. A member reads only their own notifications: the notification of another member gives 404. When access changes (an access group, a role, the visibility of a space), the notifications of the members who can no longer view the item are deleted. The list and the count check it again at every read.

Quizzes ([ADR 0038](../adr/0038-lesson-quizzes.md), #1335): the correct choices and the explanations of a quiz reach the managers of the course only. A member gets them in the review of their own attempt, never before. Nobody reads the result of another member: managers get no scores. A signed-in outsider reads the questions of a public course and keeps no result.

Leaving (#1338): a member leaves by themselves (`DELETE /communities/{community}/membership`), with the same effects as a removal: they leave the access groups, lose their course progress and their quiz results, and lose the rights on what they wrote, which stays. The owner cannot leave: a community always has an owner, so they transfer the ownership first or delete the community.

Ownership ([ADR 0035](../adr/0035-ownership-transfer.md), #1339): the owner gives the community to one of its admins (`POST /communities/{community}/ownership`, with the password of the owner). In one transaction the admin becomes the owner (rank 4) and the former owner becomes an admin (rank 3). Only an admin can receive the community, and a personal community never changes its owner. The new owner gets an email.

Invitations ([ADR 0019](../adr/0019-community-invitations-by-email.md)): the email of an invitation has a signed link, valid for 7 days, that works only for an account with the invited email address. Its footer says who invited the person, who runs the instance (`LEGAL_OPERATOR`), where the address comes from, when it is deleted, and where the privacy policy is (GDPR art. 14, #1345). The inviter is not stored on the invitation.

## Per space type

The rules above are the same for every space type (discussion, chat, pages, course, resources, link). What changes per type is the content that `contribute` adds: posts, chat messages, pages, lessons or resources. A link space ([ADR 0026](../adr/0026-link-spaces.md)) holds no content: nobody contributes in it, and its visibility only decides who sees the link.

Access groups ([ADR 0025](../adr/0025-access-groups.md)): a space with the `groups` visibility opens to the members of its access groups. The owner and the admins see every space. A member who cannot read a space cannot post in it, whatever the posting policy. A member removed from the community leaves its groups.

Access groups on space groups ([ADR 0041](../adr/0041-access-groups-open-space-groups.md)): a space with the `groups` visibility also opens to the members of the access groups of its space group, the spaces added later included. `Space::openingAccessGroupIds()` is the one place that knows this rule, and `SpacePolicy::view` uses it. The people who can give a space its groups (`manageSpaces`) can also give a whole space group to an access group, with the API or the access group settings. A moderator does it only for a space group whose `groups` spaces they can all read (#1325): a closed space group stays out of their reach. Moving a space to another space group changes who can read it: the edit form warns first.

## Later

1. Moderators of one space (a group that moderates a space).
2. Show a restricted space as locked to the members outside its groups (#869, #1241).
3. Add the buyers of a product to a group automatically (Knowii account entitlements, ADR 0010).
