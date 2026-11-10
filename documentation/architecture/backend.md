# Back-end design

How the Laravel back-end is organized. The names follow the [naming conventions](../conventions/naming.md); the [rules](../conventions/rules.md) say what every change must respect.

## Database

### Creating new tables

When creating new database tables, make sure to include a `cuid` string field right after the `id` field. This field is used, and exposed by the API. The `cuid` column is filled in automatically when creating new entities, assuming that you don't forget to add `use GeneratesCuid;` to the model.

You can learn more about cuid2 here: https://github.com/paralleldrive/cuid2?tab=readme-ov-file#why

### Model docblocks

Each model has a class docblock that lists its columns, its relations and its query methods. PHPStan (Larastan, level max) and the IDE read it, so it must agree with the database:

1. Run `composer generate-phpdoc` after a migration. It runs `ide-helper:models --write` and Pint. ide-helper adds the missing tags and keeps the tags that already exist, so a second run changes nothing.
2. Write a nullable column or relation as `?Type` (`?Carbon $edited_at`, `?User $author`), not `Type|null`. ide-helper writes new tags as `Type|null`: change them.
3. Mark `id`, `cuid`, relations, `*_count` attributes and accessors with `@property-read`. Only the other columns are `@property`.
4. Give a JSON column its real shape (`list<string> $keywords`), not `array`.
5. When a relation or a cast changes, change its tag by hand: ide-helper does not change a tag that exists.
6. A model with an API resource has a `@method` tag that types `toResource()`, so PHPStan knows the resource class (Larastan does not read `#[UseResource]`):

   ```php
   * @method TResource toResource<TResource of \Illuminate\Http\Resources\Json\JsonResource = \App\Http\Resources\PostResource>(class-string<TResource>|null $resourceClass = null)
   ```

### Resources and captured content

A link exists once in `resources` (one row per URL): the metadata of the page, its detected type, and its stored cover and icon ([ADR 0016](../adr/0016-resource-images-stored-by-knowii.md)). Each community that adds the link gets a row in `community_resources` with what the community decides and captured: its name, description, type, level, curator and capture status (`pending`, `capturing`, `captured`, `failed` with `capture_failure_reason`, or `skipped` when the curator kept only the link and Knowii never opened the page, #1294). The captured Markdown is in `resource_captures`, one row per community resource ([ADR 0018](../adr/0018-content-capture.md), #1244). Content captured in one community never shows in another one, even for the same URL.

- Only the single resource carries the capture (`->load('capture')`), and its Markdown only with `->withLargeFields()`. Lists never load it.
- Adding a link makes no HTTP request: `CreateCommunityResource` saves the community resource as `pending` and queues `CaptureCommunityResource` (#1245). The job (3 tries, backoff 10 s, 60 s, 300 s, unique per community resource) calls `CaptureResourcePage`: a plain HTTP fetch through `SafeUrl`, the metadata on the shared resource, the readable content as Markdown. A 404, a 403 or a refused address fail at once (`CaptureFailedException`, its message is shown to the curator); network and server errors try again. Each status change saves the community resource, which broadcasts `community.resource.updated` on the space channel. A capture from the browser extension is never replaced by a server capture.
- "Keep only the link" (`capture: false`, #1294) saves the community resource as `skipped`: no job, no request to the site, not even a DNS lookup, so pages behind a sign-in can be shared. "Capture the page" later goes through the capture endpoint, which checks the address with `SafeUrl` first.
- Resource types are a fixed list (`KnowiiResourceType`: article, video, audio, book, paper, tool, other, #1246). `ResourceTypeDetector` picks the first rule that matches: JSON-LD `@type`, `og:type`, the domain, the content type of the answer, then `article` when the page has readable content, else `other`. The detected type goes to `resources.type`; the community resource copies it unless its curator chose one (`type_chosen`, set by `PATCH .../resources/{resource}` or by a type in the add request). Only `article` resources keep captured content.
- The resource page shows the captured article to the members of the community only (`article` prop, rendered with `Markdown::render`, ADR 0013), also in a public space; the API leaves `capture` out for non-members (#1247). `ArticleImages` stores the images of the article under `community-resources/{cuid}/images/` (20 at most, re-encoded by `ImageProcessor`, downloaded through `SafeUrl`); an image that cannot be stored becomes its alternative text. A deleted capture deletes its images.
- The files of a community resource (the images of its article) are under `community-resources/{cuid}/` on the uploads disk (`CommunityResourceFiles`). They go when the community resource goes: the model deletes them after the commit, and `ContentFiles` deletes them when a space or a community goes (the database deletes the rows by cascade, without model events).

### Administration

If you want to look at the database during development, you can use the `npm run db:admin` command. It will start a container with PgAdmin.
Once started, you can go to `http://localhost:5050, and log in using:

- Username: root@knowii.net
- Password: password

### Cleanup

If you need to restart from scratch, you can reset the database to the default state and run all migrations using `composer db:clean` or `sail php artisan migrate:fresh`.

### Retention

The data that Knowii keeps only for a time is deleted by scheduled tasks ([ADR 0030](../adr/0030-retention-periods-enforced-by-scheduled-tasks.md)): `uploads:prune`, `retention:prune` (`App\Actions\Retention\PruneExpiredData`: invitations, API tokens, failed jobs, password reset tokens, database sessions and cache entries) and `mailing-lists:prune` (`App\Actions\EmailOctopus\PruneEmailOctopusContacts`). The periods come from `App\Support\Retention`, which reads `config/retention.php` and the Laravel keys. The privacy policy shows the same periods through `Retention::periods()`. Each command has `--dry-run`.

### Backup

Database backups rely on the laravel backup package: https://spatie.be/docs/laravel-backup/v8/introduction
To backup the database, run the following command: `php artisan backup:run`.
If you want to test it locally in Sail, then run 'sail php artisan backup:run'

By default, backups are stored under `storage/app/Knowii`

## Actions

Every change (create a community, delete a post, send a message) is an action: a class that does one task. Actions live in `app/Actions/<Domain>/` and implement a contract from `app/Contracts/<Domain>/`.

An action:

1. Is named after its task, verb first: `CreateCommunity`, `DeleteSpace`, `ReorderSpaces`.
2. Implements a contract named after the capability, in the third person: `CreatesCommunities`, `DeletesSpaces`, `ReordersSpaces`.
3. Is bound to its contract in `app/Providers/AppServiceProvider.php` (`app()->singleton(CreatesSpaces::class, CreateSpace::class)`), and resolved with `app(CreatesSpaces::class)`. Tests and other instances can swap the implementation.
4. Checks the permission first (`Gate::forUser($user)->authorize('<ability>', $model)`), then validates the input (`Validator::make(...)->validate()`, with the limits from `App\Constants`), then checks the business rules, then writes.
5. Wraps multi-row writes in a database transaction.
6. Returns the model. It never returns an HTTP response: the controller does that.

API controllers, Inertia controllers, console commands and jobs all use actions. No other place writes to the database.

### Authorization

Authorization is done using Laravel's built-in authorization system. Policies are used to define the authorization rules. Policies are located in the `app/Policies` directory, and must be named after the model they apply to, with the `Policy` suffix (e.g., `CommunityPolicy`). When names don't match a given model, they must be registered manually in the `AppServiceProvider` file.

Authorizations are checked in Action classes.

`tests/Feature/PolicyTest.php` holds one matrix per policy: for each ability, the roles that have it (owner, admin, moderator, member, outsider, guest). The test checks that exactly these roles have it. When you add or change an ability, add or change its row in the same pull request.

More details: https://laravel.com/docs/13.x/authorization

## Error handling

- Validation should be done using `$validator = Validator::make(...)` and `$validator->validate` calls. . This will throw a `ValidationException` if the validation fails. Such exceptions will be converted automatically to a JSON response by the global exception handlers
- Validation constraints should use constants defined in the `App\Constants` class. Those should be reflected on the front-end as well in the `constants.ts` file
- When there are business related problems, a `BusinessException` should be thrown. This exception should be caught in the global exception handler, and converted to a JSON response as well
- Where there are other problems (e.g., unavailable database), a `TechnicalException` should be thrown. This exception should be caught in the global exception handler, and converted to a JSON response as well

The global exception handlers are located in `bootstrap/app.php`. They must translate all exceptions to JSON when the client requests a JSON response.

## API

### Design

The API follows the [REST API Design Guide](https://github.com/NationalBankBelgium/REST-API-Design-Guide/wiki) of the National Bank of Belgium. [ADR 0011](../adr/0011-rest-api-design-guide.md) lists the rules we apply most (naming, methods, status codes, pagination, sorting, filtering) and our deviations (the response envelope below, snake_case JSON fields, cursor pagination for real-time feeds). Read both before you add an endpoint.

The response data structure looks like this:

```
{
  category: KnowiiApiResponseCategory // enum
  type: KnowiiApiResponseType // enum
  message: ""
  metadata: {} // optional
  data: {} // optional
  errors: { // optional
    "field_name": [ // example for validation errors
      ...
    ]
  }
}
```

Status codes follow the API design guide:

- 200 for successful requests
- 201 for successful creations
- 400 for bad requests / data validation issues
- 401 for authentication issues
- 403 for authorization issues
- 404 for not found issues
- 412 for precondition failed issues
- 422 for business issues (e.g., name already taken)
- 429 for rate limited calls
- 500 for internal server errors

### Architecture and data flow

- API Controllers are under `app/Http/Controllers/API`
- API Controllers are named `SomethingApiController`
- API Controllers are registered in the `routes/api.php` file. A route with nested models (`communities/{community}/spaces/{space}`) goes in a `Route::scopeBindings()` group: a child of another community then gives a 404, and the action never receives a child that does not belong to the community it authorized (#1213)
- API Controllers should have accurate PHP types and docblocks (the OpenAPI documentation is generated from them). Validation lives in the actions, so Scramble cannot infer the request body: declare each body field with `#[BodyParameter(...)]` on the controller method (see `SpaceApiController`)
- They should NOT validate the input (not their responsibility)
- They should delegate operations to a dedicated action classes (e.g., CreateCommunity)
- They return API resources from the model: `$post->toResource()->withLargeFields()` for one item, `$posts->toResourceCollection()` for a list. Each model declares its resource with `#[UseResource(PostResource::class)]` (rules, Architecture 6). Inside a resource, serialize a relation with `whenLoaded()` and a callback, so a relation that is not loaded leaves the field out: `'author' => $this->whenLoaded('author', fn (User $author) => $author->toResource(AuthorResource::class))`
- They should not catch exceptions returned by the action classes (that handle validation). Those are handled by the global exception handler (without leaking sensitive information)
- They should leverage the ApiResponses trait to return consistent responses

Data flow:

- Request > API Controller > Action class (authorization, input validation, business validation, ...) > Database
- Action class > API Controller > ApiResponses > Response
- OR Action class > Exception > Global Exception Handler > Response

### OpenAPI

The OpenAPI documentation of the API is generated automatically by [Scramble](https://scramble.dedoc.co/), from the routes, the controllers, the request validation rules, and the API resources. No annotations are needed.

In local development, it is available at:

- http://localhost:4200/docs/api (interactive documentation)
- http://localhost:4200/docs/api.json (OpenAPI document)

To export the OpenAPI document to a file, run `php artisan scramble:export`.

The interactive page uses Stoplight Elements. Scramble loads it from unpkg.com, which the strict CSP blocks ([ADR 0007](../adr/0007-strict-content-security-policy.md)), so `resources/views/vendor/scramble/docs.blade.php` loads a self-hosted copy from `public/vendor/` with the CSP nonce, and stops Font Awesome (bundled in Stoplight) from injecting its CSS. To update the copy, run `scripts/update-api-docs-assets.sh <stoplight version> <font awesome version>` and change the versions in the view. `ApiDocsPageTest` checks that the page loads no third-party origin and that every script and style element has the nonce.

Outside of the local environment, the documentation is only visible to users allowed by the `viewApiDocs` gate.

`tests/Feature/OpenApiDocumentTest.php` exports the document and checks it: every API route has an operation, every operation documents a success response, and every `POST`, `PUT` or `PATCH` documents its request body. Validation lives in the actions, so declare each body field with `#[BodyParameter]` on the controller method.

### Testing

Every endpoint has Pest feature tests (`tests/Feature/<Name>ApiControllerTest.php` for the HTTP layer, `tests/Feature/<Action>Test.php` for the action). The [Bruno collection](../api/README.md) in `documentation/api` holds an example request for every endpoint, including the main error cases.

WARNING: a change to the API changes the Pest tests and the Bruno collection in the same pull request.

### Slugs

We create slugs for the different concepts using the following library: https://github.com/cviebrock/eloquent-sluggable
Take a look at the `Community` model and `CreateCommunity` to see how it's used. Basically, the `slug` field need to exist on the model, the model must use certain traits, implement a function, and the library derives the slug automatically based on some other field. For instance, we use the "name" field for communities.
The library ensures that slugs are unique, which is key.

## WebSockets and Events

### Design

Knowii also includes WebSockets support for first-party use (i.e., for itself). The WebSockets API is used to avoid having to fetch/poll data through the RESTful API. When entities are created/updated/deleted, and at other occasions, events will be pushed to clients using WebSockets. Specific pages, such as the Dashboard, will listen to those events, and will be able to update the UI. This is nice because the UI is thus able to discover everything that has changed since it loaded, and to adapt accordingly.

Technically, Knowii uses:

- Laravel Reverb on the back-end (which uses the Pusher protocol)
- Laravel Echo on the front-end to connect, and listen to events
- An event queue stored in the database (not in Redis for now) and a worker to process that queue, and dispatch the events

For local development, Sail has been configured to run the WebSockets server (Laravel Reverb), and the worker.

### Event classes

Events that can be dispatched and processed within Knowii (with or without WebSockets) should be created in the `App\Events` directory. They should be named after the entity they relate to, and the event they represent (e.g., `CommunityCreated`). They should also be organized per entity in subdirectories. At the time of writing, we have:

- `App\Events\Communities\CommunityCreated`
- `App\Events\Communities\CommunityUpdated`
- `App\Events\Communities\CommunityDeleted`
- `App\Events\CommunityResources\CommunityResourceCreated` (also `Updated`, `Deleted`): on the channel of the resources space only ([ADR 0015](../adr/0015-resources-space-type.md))
- `App\Events\Spaces\SpaceTreeChanged`
- `App\Events\Spaces\SpaceContentChanged` (#1180): posts, comments and reactions (`space.post.created`, `.updated`, `.deleted`, `space.comment.*`, `space.reactions.changed`) on the channel of the space only. The payload holds identifiers only (`post_cuid`, `resource_cuid`, `comment_cuid`, `author_cuid`): the page loads the item again through the API, which checks the access and computes the fields of the member, such as their own reactions. New posts wait behind a "new posts" button, so the list does not move under the reader. Page spaces (#1179) and course spaces (#1188) add `space.page.created`, `.updated`, `.deleted` (`page_cuid`) and `space.pages.reordered`; the list and the page view reload their props. Chat spaces (#1185) add `space.message.created`, `.updated`, `.deleted` (`message_cuid`, `parent_cuid` for a thread reply) and send `space.reactions.changed` with `message_cuid`; the chat loads the message through the API.
- `App\Events\CommunityMembers\CommunityMembersChanged`, `App\Events\AccessGroups\AccessGroupsChanged`
- ...

https://ryanc.co/posts/understanding-laravels-serializesmodels

All event classes that send WebSocket events should...

Implement the following:

- `ShouldBroadcast`
- if relevant, implement `ShouldDispatchAfterCommit`, to ensure that the event is dispatched after the transaction is committed

The first enables broadcasting through WebSockets, and the second ensures that events are only broadcast after the commit has been completed.

Use the following traits:

- `Dispatchable`
- `InteractsWithSockets`

In addition, all events apart from the delete event should also use the following trait:

- `SerializesModels`

The `SerializesModels` trait does not work with delete events because of the way it works. It serializes only the id of the model, and tries to restore it when processing the event, which of course fails because the model has already been deleted by that point.

References:

- https://ryanc.co/posts/understanding-laravels-serializesmodels
- https://stackoverflow.com/questions/51065034/how-to-fire-laravel-5-6-event-when-model-is-deleted

In addition, each event class should:

- implement `broadcastWith` and use an HTTP Resource class to serialize the model in the exact same way as through the API
- implement `broadcastAs` to define the event name. Use the following form for event names: `<lowercase_model_name or logical_group_name>.<lower_case_event_name>` (e.g., `community.created`)
- implement `broadcastOn` to define the channel(s) the event should be broadcast on (respecting the naming conventions listed below)

Example `broadcastWith` implementation:

```php
/**
* Get the data to broadcast.
*
* @return array<string, mixed>
  */
  final public function broadcastWith(): array
  {
  return $this->community->toResource()->toArray(request());
  }
```

Notice that we reuse HTTP Resource classes to serialize the model in the exact same way as through the API. This ensures that the client can handle the event data in the same way as the API data.
Although, the HTTP Resource classes support serializing large fields or not. Always consider the size of the payloads you're sending through WebSockets and the number of potential connected clients (msg size \* number of clients). The maximum WebSocket message size is governed by the `REVERB_APP_MAX_MESSAGE_SIZE` environment variable. In general, keep messages small and avoid sending large fields.

Example of `broadcastAs` implementation:

```php
/**
 * The event's broadcast name.
 *
 * @return string
 */
final public function broadcastAs(): string
{
  return 'community.created';
}
```

Example of `broadcastOn` implementation:

```php
final public function broadcastOn(): array
{
  // Emit events to the community channel
  $retVal = [
    new PrivateChannel(Str::of(Constants::WS_CHANNEL_COMMUNITY)->replace(Constants::WS_CHANNEL_COMMUNITY_PARAM_COMMUNITY_CUID, $this->community->cuid)),
  ];

  // Emit events about public communities to the public channel
  if (KnowiiCommunityVisibility::Public === $this->community->visibility) {
    $retVal[] = new PrivateChannel(Constants::WS_CHANNEL_COMMUNITIES);
  }

  return $retVal;
}
```

Note how the `broadcastOn` method emits events to the community channel, and to the public channel if and only if the community is public. This is a good practice to ensure that the events are dispatched to the right clients, and it is actually critical for security reasons.

Check out the CommunityEvent abstract class for a complete example.

Note that all those event classes can serve other purposes as well (e.g., sending mails, notifications, etc).

### Event dispatching

The main events about models (e.g., created, updated, deleted) should be dispatched by Laravel itself. This can be achieved by mapping the event classes with the events in the `$dispatchesEvents` property of the model.

For instance, in the `Community` model, we have:

```php
/**
 * The event map for the model.
 *
 * @var array<string, class-string>
 */
protected $dispatchesEvents = [
  'created' => CommunityCreated::class,
  'updated' => CommunityUpdated::class,
  'deleted' => CommunityDeleted::class,
];
```

Other events can be dispatched manually using the `::dispatch` method of event classes (e.g., `NoSpaceLeft::dispatch($user)`, preferably from action classes.

### Channels

The channels listed in `broadcastOn` should all be defined in `channels.php`. And the channel names should be defined as constants in `Constants.php`:

```php
// WebSockets
public const string WS_CHANNEL_COMMUNITIES = 'communities';
public const string WS_CHANNEL_COMMUNITY_PARAM_COMMUNITY_CUID = '{communityCuid}';
public const string WS_CHANNEL_COMMUNITY = 'community.{communityCuid}';
```

Notice that for cases where the channel name includes variables, we also create constants for the variables, so that we can easily replace them with actual values using a simple search/replace operation.

Example of channel definitions in `channels.php`:

```php
Broadcast::channel(Constants::WS_CHANNEL_COMMUNITIES, static function (User $user) {
  // This is a public channel sharing events about public communities
  return true;
});

Broadcast::channel(Constants::WS_CHANNEL_COMMUNITY, static function (User $user, string $communityCuid) {
  // This is a private channel reserved to community members
  $community = Community::whereCuid($communityCuid)->firstOrFail();
  $retVal = $user->allCommunities()->contains($community);

  return $retVal;
});
```

### Course spaces

A lesson is a page of a course space with an optional `video_url`; a section is a page row of kind `section`, the heading of the rows after it ([ADR 0029](../adr/0029-course-spaces.md), #1188). `App\Support\VideoEmbed` accepts YouTube and Vimeo addresses only and gives the privacy-friendly player address; `frame-src` and the Permissions-Policy allow exactly those two player origins (`VideoEmbed::PLAYER_HOSTS`).

Videos in member content (#1206, [ADR 0031](../adr/0031-videos-in-member-content.md)): `App\Support\MarkdownVideos\VideoEmbedExtension` marks a YouTube or Vimeo link alone in its paragraph as a `div.video-embed` block with data attributes from `VideoEmbed::parse`. `MarkdownContent` turns the block into the `VideoPlayer` of the lessons.

### Direct messages

Direct messages (#1186, [ADR 0028](../adr/0028-direct-messages.md)) are chat messages with a `conversation_id`. `App\Events\Conversations\ConversationChanged` (`conversation.message.created`, `.updated`, `.deleted`, `conversation.reactions.changed`, with `conversation_cuid`, `message_cuid`, `author_cuid`) goes to `conversation.{conversationCuid}` (participants) and to `user.{userCuid}` of each participant (their lists and unread counts). `App\Support\ChatMessageEvents` sends the event of a chat message to its place: the space channel or the conversation channels.

### Presence channels

Presence (#1187, [ADR 0027](../adr/0027-presence-channels.md)) uses two presence channels, authorized by classes in `app/Broadcasting`: `community.{communityCuid}.online` (`CommunityOnlineChannel`) and `community.{communityCuid}.space.{spaceCuid}.presence` for chat spaces (`SpacePresenceChannel`). They identify members by cuid and share their public summary only (`Presence::info`). A member with `show_online_status` false never joins; their pages read `GET .../online-members`, which asks Reverb through `ReadsPresenceChannels`. Typing indicators are client events (`typing`, with a scope) on the space channel; `config/reverb.php` accepts client events from channel members only, and Reverb adds the cuid of the sender.

### Client-side

On the client-side, we have abstracted Laravel Echo behind a React hook called `useSocket`. This hook makes it easy to listen to channels/events, and to handle incoming events. Presence channels go through `usePresence` (members, typing indicators, polling for hidden members).
You can find examples of how to use this hook in the `Dashboard` page. See the [front-end design](frontend.md).
