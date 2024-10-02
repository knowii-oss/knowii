# TODO

- Need to add logs to Sail docker console
- Bruno: add invalid tests for create text resource
- Write tests for CreateText resource
- result of successfully creating a resource through the api is a community resource object
  - it should contain the link to the resource table entry
  - it should contain the link to the specific resource table entry
- EITHER we keep it that way, or
- when retrieving a resource, the code should load the information from the community_resources table, then from the resources_table, then from the type specific table (e.g., resource_text_articles table)
  - it should return a single object with all the information
    - the type should have
      - generic: cuid, created_at, updated_at
      - community resource specific: community cuid, resource collection cuid
      - resource generic: ... fields (from the resources table)
      - resource specific: ... fields (e.g., from the resource_text_articles table)
    - thus, polymorphic response type
- create client-side types and enums for resources: Resource, ResourceType, ResourceLevel, CommunityResource, ResourceTextArticle, etc
- create the action for creating CommunityResources
- create an API controller for creating CommunityResources
  - should create the underlying concepts automatically
- Upon registration, before creating a user profile, make sure that there is none with the same email. If there is one, then attach it again
  - Improvement for later: create a temporary user profile, and when the user has verified their email, actually delete the new profile, and reattach the old one
- Idea: create a dummy "Unknown" user account, and link user profiles of deleted users to it, for cases where the user explicitly requests the user profile to be removed as well (no recovery possible in that case)
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
