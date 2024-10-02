# TODO

- Add tests for CreateTextResource
- Add tests for TextResourceApiController
- create client-side types and enums for resources: Resource, ResourceType, ResourceLevel, CommunityResource, ResourceTextArticle, etc
- all dates should be stored using the ISO-8601 format
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
