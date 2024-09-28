# TODO

- 2024_09_26_172059_add_community_resources_table
- Add resourceLevel TS type based on KnowiiCommunityResourceLevel
- Add resource level to resource cards
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
- Page titles should be breadcrumbs
- When entering a resource collection, should show Community Name - Resource collection name as title and the community name should have a link (breadcrumb)
- Create ContentBox taking e.g., upcoming events, members, new resources, etc.
  - Should have inputs to pass a canAdd boolean, a onAdd function, and children
- Move KnowiiApiResponse, KnowiiApiResponseCategory, and KnowiiApiResponseType to a dedicated package -> App\Enums
- Add and configure the following plugins for ReactMarkdown: remark-gfm, remark-toc, remark-mdx, remark-frontmatter

'availableRoles' => array_values(Jetstream::$roles),
'availablePermissions' => Jetstream::$permissions,
'defaultPermissions' => Jetstream::$defaultPermissions,
