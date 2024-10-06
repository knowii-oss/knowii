# TODO

Goal 1: Be able to run Puppeteer in Sail
Goal 2: Be able to do the same in production

- Add tests for CreateTextResource
- Improve URL analysis code in CreateTextResource

```
PDOException(code: 23502): SQLSTATE[23502]: Not null violation: 7 ERROR:  null value in column \"slug\" of relation \"resources\" violates not-null constraint
laravel.test-1  |   DETAIL:  Failing row contains (24, cc40cgs0w0woc8wsw08csokc, , null, , null, null, null, null, https://www.youtube.com, null, textArticle, beginner, f, 0, 0, 2024-10-04 12:40:01, 2024-10-04 12:40:01, 0, f, 2024-10-04 12:40:01, 2024-10-04 12:40:01)
```

-
- In inertia controllers, find how to return a single property, but applying an API resource class to each returned element
  - (e.g., the array of collections)
  - Maybe using a map function?
  - Once we have a solution, apply it to all controllers
  - Also, rename properties to be JSON friendly (e.g., createdAt vs created_at)
- Implement ResourcePage page
- Add support for voting on resources
- Add support for starring resources
- Closing the createresourcedialog should reset it
- Closing the createresourcecollection dialog should reset it
- Test CreateResourceDialog within the resource collection screen, where the specific collection can directly be provided and ensure that the collection field is not displayed
- Add form validation to CreateResourceDialog
- Add tests for TextResourceApiController
- Add test, deleting a user does not remove the user profile
- Allow changing the level of a resource
- Allow changing the description of a resource
- HasCommunities: roles, permissions, etc
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
  - Add members
- Create ContentBox taking e.g., upcoming events, members, new resources, etc.
  - Should have inputs to pass a canAdd boolean, a onAdd function, and children
- Add and configure the following plugins for ReactMarkdown: remark-gfm, remark-toc, remark-mdx, remark-frontmatter
- add "profile" to UserResource?

'availableRoles' => array_values(Jetstream::$roles),
'availablePermissions' => Jetstream::$permissions,
'defaultPermissions' => Jetstream::$defaultPermissions,
