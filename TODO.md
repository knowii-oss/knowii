# TODO

- Fix community deletion test (broken model changes)
- Ensure that autocompletion works for Window.Echo both in the project and in the lib (if not, fix or duplicate the d.ts files)
- Communities channel should also include update about private and personal communities IF the user is a member
- Add channel for specific resource collections
- Add channel for specific resources
  - Also emit on those
- ->dontBroadcastToCurrentUser();
- Issue: many event subscriptions made and cancelled
- Dashboard: listen to community updated and community deleted events
- Add reset button to createResourceDialog form
- If error while saving resource, CreateResourceDialog should stop the loading spinner
  - should be handled in knowii-api-client.ts
  - Fix knowii-api-client: should not try to parse directly, or at least should not EXPLODE if the response does not have the hoped structure
- CreateTextResource: reject urls pointing to files (audio, video, etc)
- TODO show both the community specific name/description and the resource generic one
- Implement ResourcePage page
- Test CreateResourceDialog within the resource collection screen, where the specific collection can directly be provided and ensure that the collection field is not displayed
- Add form validation to CreateResourceDialog
- Allow changing the level of a resource
- Allow changing the description of a resource
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
- Facts table for user profiles
  - Verified: boolean
- Request to join a community vs RBAC + add to features
  - What happens to created resources once a user does not have access to the community anymore?
- Community chat
- Community gallery
- Share content on social media
  - Publish right from Knowii
- Try to execute node from Laravel
  - https://laracasts.com/discuss/channels/laravel/how-to-run-nodejs-from-laravel-controller
  - https://www.npmjs.com/package/node-html-markdown?activeTab=code
  - https://github.com/mixmark-io/turndown
  - https://github.com/crosstype/node-html-markdown
- Add and configure the following plugins for ReactMarkdown: remark-gfm, remark-toc, remark-mdx, remark-frontmatter
- add "profile" to UserResource?

'availableRoles' => array_values(Jetstream::$roles),
'availablePermissions' => Jetstream::$permissions,
'defaultPermissions' => Jetstream::$defaultPermissions,

- Check out Locale package: https://github.com/macmotp/locale
- Switch to autocomplete for resource collection selection in CreateResourceDialog (might be many collections)
- Improve PHP Constants.php to use const string NAME = 'value';
  - Reference: https://php.watch/versions/8.3/typed-constants
