# TODO

- Pass the page URL for Markdown conversion so that links are correct
- FetchUrl
  - Add referrer to gotoOptions (?)
  - rejectResourceTypes
  - Array of strings: "cspviolationreport" "document" "eventsource" "fetch" "font" "image" "manifest" "media" "other" "ping" "prefetch" "preflight" "script" "signedexchange" "stylesheet" "texttrack" "websocket" "xhr"
  - Delay page loading. First only use the provided data, and trigger batch job doing the rest
- Reject urls pointing to files
- Reject YouTube links for now

"og:site_name" => "Sébastien Dubois"

"article:publisher" => "https://www.facebook.com/dSebastienBE/"
"article:author" => "https://www.facebook.com/trankill"
article:author - profile array - Writers of the article.

- Add tests for ProcessHtml
- Add Browserless in production
  - Try to use /active (Returns a simple "204" HTTP code, with no response, indicating that the service itself is up and running. Useful for liveliness probes or other external checks): https://docs.browserless.io/open-api#:~:text=/active,other%20external%20checks.
- Add tests for CreateTextResource
- Improve URL analysis code in CreateTextResource

```
PDOException(code: 23502): SQLSTATE[23502]: Not null violation: 7 ERROR:  null value in column \"slug\" of relation \"resources\" violates not-null constraint
laravel.test-1  |   DETAIL:  Failing row contains (24, cc40cgs0w0woc8wsw08csokc, , null, , null, null, null, null, https://www.youtube.com, null, textArticle, beginner, f, 0, 0, 2024-10-04 12:40:01, 2024-10-04 12:40:01, 0, f, 2024-10-04 12:40:01, 2024-10-04 12:40:01)
```

- If error while saving resource, CreateResourceDialog should stop the loading spinner
  - should be handled in knowii-api-client.ts
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
- Improve HTML to Markdown conversion: https://github.com/thephpleague/html-to-markdown
- Purify the loaded HTML when fetching resources: https://github.com/mewebstudio/Purifier
- Add community details link to the community homepage
  - Include base details for anyone to see if community is public or user is member
  - What to include?
  - More info and control if admin
  - More info and control if owner
  - RBAC
    - Member: can see
    - Moderator: can change content
    - Admin: can change community settings and manage members
    - Owner: Can change community type, manage billing, etc
    - Update the RBAC model
- Add community name availability check
- Show total resources count on resource card
- Select and show resource type icon on each resource card and on the resource details page
- Cascade delete of community invitations and resource collections and resources upon account deletion
- UI
  - Use scrollpanel for the community description: React ScrollPanel Component (primereact.org)
  - Add tw-merge
  - Configure prime react to use twMerge
    - import { twMerge } from 'tailwind-merge';
      ptOptions: { mergeSections: true, mergeProps: true, classNameMergeFunction: twMerge }
  - Stop using classnames: tailwind-merge - npm (npmjs.com)
    - use either twMerge (when a merge with styles conflict resolution is needed)
    - or twJoin (when no conflict resolution is needed)
  - Document this (contributing)
  - Add to .cursorrules
- Comments table: https://github.com/Lakshan-Madushanka/laravel-comments
- Facts table for user profiles
  - Verified: boolean
- Voice recording resource type
- Implement deletion of personal communities
- Request to join a community vs RBAC + add to features
  - What happens to created resources once a user does not have access to the community anymore?
- Persons list resource type
  - Search and add user profiles
  - Edit to add/remove people from the lists
- Community chat
- Community gallery
- Share content on social media
  - Publish right from Knowii
- Try to execute node from Laravel
  - https://laracasts.com/discuss/channels/laravel/how-to-run-nodejs-from-laravel-controller
  - https://www.npmjs.com/package/node-html-markdown?activeTab=code
  - https://github.com/mixmark-io/turndown
  - https://github.com/crosstype/node-html-markdown
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

- Check out Locale package: https://github.com/macmotp/locale
