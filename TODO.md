# TODO

- CommunityController.php
- community.schema.ts
- CommunityPolicy
- Create a database migration 
- $table->foreignId('user_id');
- When a new community is created, an entry should be added to the CommunityUser table, with the current user having the owner role
- Implement RBAC model

  - Users can have a role which depends on the platform, not on the content
    - Admin: boolean flag on the user account
  - Users have generic permissions (e.g., canLogin, canRegister, etc)
  - Users can have roles and permissions in communities
  - Community roles: Owner, Admin, Moderator, Member
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

- Recent resource card should include level: Beginner, Intermediate, Advanced, Expert
- Page titles should be breadcrumbs
- When entering a resource collection, should show Community Name - Resource collection name as title and the community name should have a link (breadcrumb)
- Create ContentBox taking e.g., upcoming events, members, new resources, etc.
  - Should have inputs to pass a canAdd boolean, a onAdd function, and children
- Move KnowiiApiResponse, KnowiiApiResponseCategory, and KnowiiApiResponseType to a dedicated package -> App\Enums
- Add and configure the following plugins for ReactMarkdown: remark-gfm, remark-toc, remark-mdx, remark-frontmatter

'availableRoles' => array_values(Jetstream::$roles),
'availablePermissions' => Jetstream::$permissions,
'defaultPermissions' => Jetstream::$defaultPermissions,
