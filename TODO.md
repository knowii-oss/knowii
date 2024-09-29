# TODO

- Add tests for user profile detach function
- Add tests for user deletion
- On registration, before creating a user profile, make sure that there is none with the same email. If there is one, then attach it again
  - Improvement for later: create a temporary user profile, and when the user has verified their email, actually delete the new profile, and reattach the old one
- Idea: create a dummy "Unknown" user account, and link user profiles of deleted users to it, for cases where the user explicitly requests the user profile to be removed as well (no recovery possible in that case)
- HasCommunities: roles, permissions, etc
- CommunityController.php
- community.schema.ts
- CommunityPolicy
- Community permissions
  - createPublicCommunity
  - createPrivateCommunity
  - createPersonalCommunity
  - viewPersonalCommunity
  - discoverPublicCommunities
  - discoverPrivateCommunities
  - viewPublicCommunity
  - viewPrivateCommunity
  - viewPersonalCommunity
  - joinPublicCommunity
  - joinPrivateCommunity
  - joinPersonalCommunity
  - inviteNewCommunityMember
  - manageCommunityMembers
  - manageCommunitySettings
  - deleteCommunity
  - ...
- CommunityController
  - Add resource collections
  - Add recent resources
  - Add members
- Create ContentBox taking e.g., upcoming events, members, new resources, etc.
  - Should have inputs to pass a canAdd boolean, a onAdd function, and children
- Move KnowiiApiResponse, KnowiiApiResponseCategory, and KnowiiApiResponseType to a dedicated package -> App\Enums
- Add and configure the following plugins for ReactMarkdown: remark-gfm, remark-toc, remark-mdx, remark-frontmatter
- add "profile" to UserResource?

'availableRoles' => array_values(Jetstream::$roles),
'availablePermissions' => Jetstream::$permissions,
'defaultPermissions' => Jetstream::$defaultPermissions,
