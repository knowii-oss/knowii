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

The data that Knowii keeps only for a time is deleted by scheduled tasks ([ADR 0030](../adr/0030-retention-periods-enforced-by-scheduled-tasks.md)): `uploads:prune`, `retention:prune` (`App\Actions\Retention\PruneExpiredData`: invitations, API tokens, failed jobs, notifications, password reset tokens, database sessions and cache entries, and the export files of communities and members) and `mailing-lists:prune` (`App\Actions\EmailOctopus\PruneEmailOctopusContacts`). `mail_usage_months` (the count of sent mail, see Email delivery) holds no personal data and has no period. The periods come from `App\Support\Retention`, which reads `config/retention.php` and the Laravel keys. The privacy policy shows the same periods through `Retention::periods()`. Each command has `--dry-run`.

### Community export

The DPA promises the owner an export of its data before the deletion (GDPR art. 28(3)(g), [#1353](https://github.com/knowii-oss/knowii/issues/1353)). The operator runs `php artisan communities:export {slug}` on request: the thin command `ExportCommunityCommand` calls the contract `App\Contracts\Communities\ExportsCommunities`, bound to `App\Actions\Communities\ExportCommunity`. The action writes one zip file under `exports/` of the `local` disk: JSON files (`format_version` 1, pretty printed, one list per kind of content), the stored uploads and course material files, and a `README.txt` that tells what is left out. It reads the models with eager loading and `lazyById()`, and `App\Support\JsonExportFile` writes each list one item at a time, so a big community never sits in memory. Public identifiers are cuids: the export has no internal id, password hash, token, two-factor secret or derived HTML column. Direct messages go in only with `--with-direct-messages`: they are private between their participants. The export is a copy of personal data, so `retention:prune` deletes the file after `retention.exports_days` (data rule 8). The steps for the operator are in the [community export runbook](../runbooks/community-export.md).

The acceptance of the DPA is stored on the community: `CreateCommunity` sets `dpa_accepted_at` and `dpa_version` (the `updated` date of the DPA, from `LegalPages::updated()`) for a shared community, and leaves them empty for a personal one.

### Member data export

The privacy policy promises a copy of their data to every member (section 8, GDPR art. 15 and 20, [#1343](https://github.com/knowii-oss/knowii/issues/1343)). The operator runs `php artisan users:export {email}` on request: the thin command `ExportUserCommand` calls the contract `App\Contracts\Users\ExportsUsers`, bound to `App\Actions\Users\ExportUser`. The action writes one zip file under `exports/` of the `local` disk, named `user-<username>-<YYYYMMDD-HHMMSS>.zip`. It holds JSON files (`format_version` 1, written with `JsonExportFile`, one list per kind of data), the avatar, the uploaded images and a `README.txt` that tells what is left out. The checklist of the data is the list of [ADR 0024](../adr/0024-account-deletion-removes-personal-data.md) and section 2 of the privacy policy: the account, the profile, the memberships, the communities that removed the member and where they cannot join again by themselves (`join_blocks`, #1486), the access groups, the lesson progress, the quiz results, the read markers, the blocks, the mentions, the notifications (kind, community, items by cuid, dates, never a text), the connected provider accounts (the issuer, the identifier of the member at the provider and the dates, #1385), the API tokens (name, abilities and dates, never the token), the sessions (IP address, browser, last activity, never the payload), the events that the member hosts (`events.json`: title, space, times, zone and state, #1513) and the content of the member. It holds only what the member wrote or what is about them: a comment shows the cuid and the title of its post, and a conversation shows only the messages of the member. The file is a copy of personal data, so `retention:prune` deletes it after `retention.exports_days`. The steps for the operator are in the [member data export runbook](../runbooks/member-data-export.md). A self-service button is a later step of the issue.

### Account deletion

`DELETE /user` (route `current-user.destroy`, `DeleteAccountController`, after the password check) calls the contract `App\Contracts\Users\DeletesAccounts`, bound to `App\Actions\DeleteUser` ([ADR 0024](../adr/0024-account-deletion-removes-personal-data.md)): the personal data goes, the content of the member stays under "Deleted member". The API has `DELETE /user` (`UserApiController::destroy`, #1492): the password through `ConfirmPassword`, the code or recovery code through `App\Support\SecondFactor` (shared with the API sign-in), then the same contract; token ability `account:delete` (not in the default set), limiter `account-deletions`. The option `delete_content` (boolean, validated by the action, off by default) also deletes the posts, comments and messages of the member in every community ([ADR 0043](../adr/0043-delete-content-with-the-account.md), #1344): `DeleteUser` calls `App\Contracts\Users\DeletesMemberContent`, bound to `App\Actions\Users\DeleteMemberContent`, in its own transaction, in chunks of 500 ids. Posts, comments and chat messages that other members still answer stay as markers (`posts.deleted_at`, `comments.deleted_at`, `chat_messages.deleted_at`: no text, no author, and for a post no title and a slug without the old title); the direct messages of the member are always markers; everything else is deleted. The resources, pages and lessons of the member are not touched. The markers of posts and comments come from `App\Contracts\Content\LeavesMarkers` (`LeaveMarkers`), the same as an ordinary delete (#1565, [ADR 0050](../adr/0050-a-delete-keeps-the-replies-of-other-people.md)): their reactions, mentions and upload usages go with it, the notification rows through `PrunesNotifications` (`forPosts`, `forComments`; `forChatMessages` for the chat messages), the rest through the cascades. The action sends no real-time event per item. The resources never return the old content of a marker (`PostResource`, `CommentResource`, `ChatMessageResource`), the post list and the community export skip the marker posts, and the policies refuse to edit, pin, comment on or react to a marker.

An account without a password ([ADR 0052](../adr/0052-account-deletion-after-a-new-sign-in.md), #1521) proves the intent with a new sign-in at the OpenID Connect provider. `App\Support\AccountDeletion::proofOf()` gives the case (`KnowiiAccountDeletionProof`: `password`, `sign_in`, or `unavailable` when the account has no password and no connected identity of a configured provider). `POST /auth/oidc/confirm-deletion` (`OpenIdConnectController::confirmDeletion`) opens a flow with the purpose `deletion` and asks the provider for `max_age=0` and `prompt=login`; the shared callback tells the purposes apart by the flow in the session. After the checks of the ID token, `App\Contracts\Auth\ConfirmsAccountDeletions` (`App\Actions\Auth\ConfirmAccountDeletion`) requires `auth_time` (an integer, at most `services.oidc.fresh_sign_in_seconds` old, at most 60 seconds ahead) and the `sub` of the identity of the account, and records `users.deletion_signed_in_at`. The callback never deletes. `DeleteAccountController` and `UserApiController::destroy` then take that proof instead of the password, with the two-factor code when it is on, and call `DeletesAccounts::deleteAfterSignIn()`, which locks the row of the account, checks the proof again and clears it in the transaction of the deletion. Without a valid proof the API answers 422 with the address of the deletion section (`metadata.sign_in_url`). The profile page gets the prop `accountDeletion` (`JetstreamServiceProvider::withAccountDeletion`).

### Delete of a post or a comment

An ordinary delete of a post or a comment ([ADR 0050](../adr/0050-a-delete-keeps-the-replies-of-other-people.md), #1565) keeps the words of other people. `DeleteComment` (contract `DeletesComments`) turns a comment that has a reply of other people (not a marker, not written by the person who deletes, a deleted account included) into a marker, and `DeletePost` (contract `DeletesPosts`) does the same for a post that has a comment of other people; both use `LeavesMarkers`, in one transaction, and return the marker (or null when the item went with everything under it, by cascade). The options `delete_replies` and `delete_comments` (booleans, validated in the actions) delete everything under the item, a marker too; they need `CommentPolicy::deleteWithReplies` or `PostPolicy::deleteWithComments` (a moderator of the space). `delete` is false for a marker, like a chat message. A marker whose last reply goes stays. After the commit, `SpaceContentChanged` sends `space.comment.updated` or `space.post.updated` for a marker and `.deleted` when the item went. The API answers 200 with a message for a marker and 204 otherwise.

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

#### Visitors

A visitor (not signed in) reads the public spaces ([ADR 0044](../adr/0044-public-spaces-for-visitors.md), #1387). The routes that allow it sit in a group behind the middleware `auth.optional` (`AuthenticateIfPresent`), in `routes/web.php` (the space, post, page and resource pages) and in `routes/api.php` (ten `GET` reads, limited per IP address by `throttle:public-reads`). The middleware signs the request in with the Sanctum guard when there is a session or a token (and then requires a verified email address, like `verified`), refuses a token that Sanctum does not accept, and otherwise lets a visitor go on and marks the request. `RequireTokenAbility` lets only a marked request pass without a user, so the `ability:` check of a token stays. The policies decide what a visitor reads (`SpacePolicy::view` refuses a chat space), the controllers pass cut props, and the resources cut the member data (`CommunityResource` for a non-member, `SpaceResource.access_groups` for managers only). Every other route keeps `auth:sanctum`. On the web, a visitor who may not read a page (also a page that does not exist) goes to the sign-in page and back to the address; on the API a visitor refused on a public route gets a 401. A page of a community stays `noindex` for everyone (`Robots::directive`).

#### Joining

A shared community has a `join_policy` (`AppEnumsKnowiiCommunityJoinPolicy`: `invitation`, the default, or `open`; [ADR 0044](../adr/0044-public-spaces-for-visitors.md), "Who can join", #1486). `UpdateCommunityJoinPolicy` (contract `UpdatesCommunityJoinPolicy`, route `PATCH /v1/communities/{community}/join-policy`, gate `CommunityPolicy::manageJoinPolicy`: owner and admins) changes it, and refuses a personal community. `JoinCommunity` (contract `JoinsCommunities`, `POST /v1/communities/{community}/membership`, limiter `joins`, ability `communities:write`) adds the signed-in person as a member when the community is open (`Community::isOpen()`), their address is verified, they are not a member and no manager removed them; it locks the community row, sends `CommunityMembersChanged` and answers 422 with a message for each refusal. `RemoveCommunityMember` writes a row of `community_join_blocks` (`AppModelsCommunityJoinBlock`: community, person, date; unique pair; cascades with the community and the account) in the transaction of the removal. `LeaveCommunity` writes none. `AcceptCommunityInvitation` deletes the row, and `LiftCommunityJoinBlock` (contract `LiftsCommunityJoinBlocks`, `DELETE /v1/communities/{community}/join-blocks/{member}`, gate `removeCommunityMember`) does too. `GET /v1/communities/{community}/join-blocks` lists the people (same gate). `Community::removeUser()` stays the shared part of leaving and removing: never write the block there.

More details: https://laravel.com/docs/13.x/authorization

## Instance values

The values that describe one instance come from configuration, never from the code (#1653, [ADR 0055](../adr/0055-instance-values-come-from-configuration.md)). `SELF_HOSTING.md` ("Instance values") has the table for an operator.

1. `config/legal.php` holds the two required values: `operator` (`LEGAL_OPERATOR`) and `contact_email` (`LEGAL_CONTACT_EMAIL`). A value that is empty or blank is not set. Outside production a value that is not set gets a placeholder (`Constants::INSTANCE_PLACEHOLDER_OPERATOR`, `Constants::INSTANCE_PLACEHOLDER_CONTACT_EMAIL`). In production it stays empty.
2. `config/instance.php` holds the optional values as trimmed text: `publisher.name`, `publisher.url`, `footer.note`, `footer.links`, `social_links` and `seed.admin_email`. Empty means hidden.
3. `App\Support\InstanceValues` is the only reader. `missing()` returns the environment names to set (always empty outside production; in production a value is missing when it is empty, a placeholder, or not an email address for the contact). `footerLinks()` and `socialLinks()` parse the two lists and leave a wrong entry out with a warning in the log. `pageProp()` is the shared Inertia prop `instance`. `structuredData()` is the JSON-LD block of `app.blade.php`, which names a publisher only when the instance names one.
4. The start check, in production only (`app()->isProduction()`). The global middleware `App\Http\Middleware\RefuseUnconfiguredInstance` is first in the stack (`bootstrap/app.php`): while a value is missing, every address answers `503` with the message as plain text, `/up` too, and the log gets the message at the level `critical`. The listener `App\Listeners\RefuseUnconfiguredInstance` hears `CommandStarting` and throws `InstanceNotConfiguredException` for the commands of `Constants::INSTANCE_CHECKED_COMMANDS` (the queue worker, the scheduler, Reverb). Every other command runs.
5. `/contact` answers 404 without a contact address. The invitation email names `legal.operator`. `DatabaseSeeder` creates its first user with `instance.seed.admin_email`.
6. Tests: the test environment runs with the placeholders. A test that switches to production calls `configuredInstance()` (`tests/Pest.php`) first, else every request answers 503. `tests/Unit/InstanceNeutralNamesTest.php` fails when a value of one instance comes back in the code, the configuration, the views, the front-end, the tests, `public/humans.txt` or the example environment files.

## Types

PHPStan runs at the highest level (`level: max` in `phpstan.neon`), which already refuses an unchecked `mixed` value. The code also keeps the word `mixed` out of its signatures and PHPDoc (#726): a parameter, a return value or an array shape names its real type, a union type or an array shape.

`mixed` is allowed only where a value really comes from outside, and the code narrows it at once (`is_string`, `is_array`, a validator):

1. The `$input` of an action: the request input before the validation (`@param array<mixed> $input`).
2. The value of a closure validation rule (`mixed $value, Closure $fail`).
3. `toArray()` of an API resource (`@return array<string, mixed>`, the signature of the framework).
4. Configuration values, decoded JSON of a third party, and the signatures of the framework and of libraries (Inertia props, Eloquent attributes, slug configuration, HTTP client options).

`tests/Unit/MixedTypeUsageTest.php` holds the list of the files of point 4, with the number of uses in each. The list can only shrink: a new use of `mixed` fails the test.

Shared shapes of the API envelope (`App\Traits\ApiResponses`): the metadata is `array<string, bool|int|string|array<string, int|null>|null>` (pagination and flags), and the errors are `array<string, array<int, string>>` (messages per field).

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

The name of a shared (private or public) community is unique as well: no other shared community may have the same name, whatever the case, or the address that the name gives. `App\Rules\AvailableCommunityName` holds the rule, the create and update actions apply it, and the forms ask `POST /api/v1/communities/is-name-available` while the user types. Personal communities never take a name ([ADR 0033](../adr/0033-unique-names-for-shared-communities.md), #1136). Some names are reserved for the administrators of the instance (`App\Support\ReservedCommunityNames`, `config/communities.php`): for everyone else they are taken ([ADR 0036](../adr/0036-reserved-community-names.md), #1340).

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
- `App\Events\Spaces\SpaceContentChanged` (#1180): posts, comments and reactions (`space.post.created`, `.updated`, `.deleted`, `space.comment.*`, `space.reactions.changed`) on the channel of the space only. A post or a comment that becomes a marker (#1565) sends `.updated`: the pages load it again and show the marker, and the post lists drop a marker post. The payload holds identifiers only (`post_cuid`, `resource_cuid`, `comment_cuid`, `author_cuid`): the page loads the item again through the API, which checks the access and computes the fields of the member, such as their own reactions. New posts wait behind a "new posts" button, so the list does not move under the reader. Page spaces (#1179) and course spaces (#1188) add `space.page.created`, `.updated`, `.deleted` (`page_cuid`) and `space.pages.reordered`; the list and the page view reload their props. Chat spaces (#1185) add `space.message.created`, `.updated`, `.deleted` (`message_cuid`, `parent_cuid` for a thread reply) and send `space.reactions.changed` with `message_cuid`; the chat loads the message through the API. Events spaces (#1513, ADR 0053) add `space.event.created`, `.updated` (also for a change of state) and `.deleted` (`event_cuid`, `author_cuid`): never a title, a meeting address or a place, and nothing for a draft.
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

### Images in member content

Members add images to a post, a comment, a message or a page through the uploads endpoint (#1178): one request per image, with its alt text. The editor takes up to 10 images at a time (#1207) and writes them one per line, so images that follow each other can form a gallery. A text holds 20 images at most: `App\Rules\MaxMarkdownImages` (`Constants::MAX_IMAGES_PER_CONTENT`) is in the `body` rules of posts, comments, chat messages, direct messages and pages, and it counts the `<img>` elements of the rendered text, so no way of writing an image gets around the limit. The editor checks before it uploads (`countMarkdownImages` in `libs/common`); the server has the last word.

Images that follow each other with no text between them form a gallery ([ADR 0039](../adr/0039-images-that-follow-each-other-form-a-gallery.md)): `App\Support\MarkdownGalleries\ImageGalleryExtension` replaces their paragraphs with `<div class="image-gallery not-prose" data-image-count="N">`, where each image is a link to its file. One image alone in its paragraph is a gallery of one. An image inside a sentence and an image that is a link stay as they are. The renderer writes the images itself: it applies the same rule against unsafe addresses as CommonMark and escapes the attributes.

An upload has smaller versions ([ADR 0040](../adr/0040-stored-sizes-of-uploaded-images.md)): `UploadImage` stores WebP files that are 640 and 1280 px wide next to the original (`<cuid>-640.webp`), only for the widths smaller than the image, and keeps the widths in the JSON column `uploads.sizes` (null for an upload of before, until `uploads:build-sizes` runs). After parsing, `App\Support\MarkdownUploads\UploadImagesExtension` finds the uploads of all the images of the text in one query. An image whose address is the stored address of an upload gets `width`, `height`, `srcset` and `sizes` (written by `ImageGalleryRenderer` for a gallery and by `InlineImageRenderer` for an image in a sentence). Any other image stays as it is. `Upload::deleteFiles()` deletes the original and its versions: the prune and the community deletion use it, and any new code that deletes an upload must too. `php artisan content:render-again` renders the stored HTML again from the Markdown (posts, comments, chat and direct messages, pages, quiz questions). It writes with the query builder: no mention, no notification, no event, and `updated_at` and `edited_at` stay.

### Access groups

An access group opens the spaces with the `groups` visibility ([ADR 0025](../adr/0025-access-groups.md)), one by one (`access_group_space`) or through a whole space group (`access_group_space_group`, [ADR 0041](../adr/0041-access-groups-open-space-groups.md), #1422). The reading rule lives in one place: `Space::openingAccessGroupIds()` returns the groups of the space and the groups of its space group, `User::isInAccessGroupOf()` checks the member against them, and `SpacePolicy::view` is the only caller. Every read goes through `SpacePolicy::view`, so a new read path must not test the groups by itself. `SpaceTree` loads `accessGroups` of the space groups and of the spaces, to avoid a query per space. The two `PUT` routes (`access-groups/{group}/space-groups` and `space-groups/{spaceGroup}/access-groups`) replace the links with the actions `SetAccessGroupSpaceGroups` and `SetSpaceGroupAccessGroups`, which share `AccessGroupSpaceGroups`.

### Course spaces

A lesson is a page of a course space with an optional `video_url`; a section is a page row of kind `section`, the heading of the rows after it ([ADR 0029](../adr/0029-course-spaces.md), #1188). `App\Support\VideoEmbed` accepts YouTube and Vimeo addresses only and gives the privacy-friendly player address; `frame-src` and the Permissions-Policy allow exactly those two player origins (`VideoEmbed::PLAYER_HOSTS`).

Videos in member content (#1206, [ADR 0031](../adr/0031-videos-in-member-content.md)): `App\Support\MarkdownVideos\VideoEmbedExtension` marks a YouTube or Vimeo link alone in its paragraph as a `div.video-embed` block with data attributes from `VideoEmbed::parse`. `MarkdownContent` turns the block into the `VideoPlayer` of the lessons.

### Course materials

A course space has supplementary materials (#1336, [ADR 0037](../adr/0037-course-materials.md)): files and links for the whole course, a section or a lesson (`course_materials`, `App\Models\CourseMaterial`). The managers of the course change them (`SpacePolicy::managePages`); members who can read the space list and download them. The files are on a private disk (`config/courses.php`: `COURSE_MATERIALS_DISK`, `local` by default) and never have a public address: `GET .../materials/{material}/download` checks the access, then streams the file as an attachment or redirects to a temporary address of the storage service. `App\Support\CourseMaterials` holds the accepted types and the size limit, and `App\Support\ContentFiles` deletes the files with the material, the lesson, the space and the community.

### Lesson quizzes

A lesson of a course space has at most one quiz (#1335, [ADR 0038](../adr/0038-lesson-quizzes.md)): `quizzes`, `quiz_questions` (kind `single` or `multiple`, Markdown prompt and explanation), `quiz_choices` and `quiz_results` (one row per member and quiz: best score, last score, last answers, attempts, first pass). The managers of the course write the whole quiz in one `PUT .../pages/{page}/quiz` (`SaveQuiz`): new questions delete the results, a new pass mark alone keeps them. Members answer with `POST .../quiz/attempts` (`AttemptQuiz`, limiter `quiz-attempts`): the server grades (`App\Support\Quizzes::grade`), and a pass marks the lesson as done through `TracksLessonProgress`.

The correct answers stay on the server. `QuizResource` serializes the rendered prompts and the labels only; `->withAnswers()` adds the Markdown sources, `is_correct` and the explanations, and only `Quizzes::for()` and the quiz controller call it, for a user with `SpacePolicy::managePages`. A member gets the correct choices and the explanations in the review of their own attempt (`Quizzes::result`). Lists of pages load `has_quiz` with `withExists('quiz')`.

### Events

An events space (#1513, [ADR 0053](../adr/0053-events-a-space-type-with-its-own-table.md)) holds events: `events`, `App\Models\Event` (not to confuse with the broadcast events of `app/Events/`: a file that uses both imports one under an alias). An event has one host (`host_id`, `nullOnDelete`), a title and a slug unique in its space that never changes, a Markdown description rendered by `Markdown::render()` (no mentions, like pages), `starts_at` and `ends_at` (`timestampTz`, UTC instants, the truth) and `time_zone` (the IANA zone in which the host entered them), `meeting_url`, `place`, `location_visibility` (`members` or `going`), a cover (`cover_upload_id`, `cover_alt`), `state` (`draft`, `published`, `cancelled`), `sequence` and `published_notified_at`. "Past" is derived from `ends_at` (`is_past`), never stored.

1. Actions behind contracts in `App\Contracts\Events`: `CreatesEvents`, `UpdatesEvents`, `SetsEventStates`, `DeletesEvents`, `DuplicatesEvents`. `CreateEvent` refuses another space type (`BusinessException`). `App\Support\EventTimes` parses the times: ISO 8601 with an explicit offset, the offset must be the real offset of the zone at that instant, a local time that the zone skips is a field error with the two hours around the gap, a local time that exists twice is accepted with either offset. The end is after the start, at most `Constants::MAX_EVENT_DURATION_HOURS` later; a start in the past is for the owner and the admins only.
2. `EventPolicy`: a draft for its host, the owner and the admins; a published or cancelled event follows `SpacePolicy::view`. `update` (edit, publish, back to draft, restore, duplicate): the owner, the admins and the host (a member who can still read the space). `cancel` and `delete`: also `SpacePolicy::moderate`. `viewLocation`: members only, every member with `members`, the host, the owner and the admins with `going`.
3. `EventResource` cuts `meeting_url` and `place` on the server (`can_view_location`). Lists never hold a draft for a reader who may not view it (`Event::scopeVisibleTo`).
4. States: draft to published, published to draft (not once it started), published to cancelled, cancelled to published; each real change adds 1 to `sequence`, and so does a change of the start, the end, the address or the place. `DuplicateEvent` makes a draft 7 days later at the same wall-clock time in `time_zone`.
5. Uploads: `Event::syncUploads()` writes one `upload_usages` row (`event_id`) per image of the description and one for the cover, so `uploads:prune` keeps them. The rows go with the event by cascade.
6. The first publication of an event whose start is in the future calls `Notifies::notify()` once (`published_notified_at`), outside `Notify::silenced()`: kind `event_new` (see Notifications). Back to draft deletes its rows (`PruneNotifications::forDraftEvent`). The first publication from a draft also calls `TracksSpaceVisits::wroteIn()`.
7. The community export has `events.json`; the member export has `events.json` (the events that the member hosts) and names the event of a notification row.
8. A draft sends nothing on the space channel. The first publication sends `space.event.created`, a change of a published or cancelled event `space.event.updated`, a delete or a return to draft `space.event.deleted`. A person who may not see an event gets 404 from every write route, as for an event that does not exist. `DuplicateEvent` also needs `SpacePolicy::contribute`, goes through the time rules of a create (`EventTimes::daysLaterInZone`, `CreateEvent::checkTimes`), refuses a copy whose length a change of the clocks would change, and gives the copy to the person who duplicates when the host may not host any more. Create and duplicate share the limiter `events`.
9. The pages (#1514): `Inertia\EventController` only reads. `events.show` is in the `auth.optional` group of `routes/web.php`; `events.create` (`.../space/{spaceSlug}/events/new`) and `events.edit` are in the signed-in group. A reader who may not see an event gets 404. `SpaceController` gives an events space `events` (the first `EVENTS_PER_PAGE`, 20) and `eventsScope`; `Event::scopeUpcoming` puts the drafts first, then the next start first, for the page and for the API. `App\Support\UpcomingEvents` holds the query of `GET communities/{community}/events` and of the prop `nextEvents` (the first `Constants::NEXT_EVENTS`, 3) of the home and of the public page of a community. `tests/Feature/EventPagesTest.php` covers the pages for each reader.

### Direct messages

Direct messages (#1186, [ADR 0028](../adr/0028-direct-messages.md)) are chat messages with a `conversation_id`. `App\Events\Conversations\ConversationChanged` (`conversation.message.created`, `.updated`, `.deleted`, `conversation.reactions.changed`, with `conversation_cuid`, `message_cuid`, `author_cuid`) goes to `conversation.{conversationCuid}` (participants) and to `user.{userCuid}` of each participant (their lists and unread counts). `App\Support\ChatMessageEvents` sends the event of a chat message to its place: the space channel or the conversation channels.

### Notifications

Notifications (#1474, [ADR 0042](../adr/0042-notifications.md)) tell a member when a person writes to them. One table (`notifications`, one row per recipient, no text of content), one action behind a contract (`App\Contracts\Notifications\Notifies`, `App\Actions\Notifications\Notify`) and nine kinds (`KnowiiNotificationKind`, which holds `level()` and `reason()`: the level gives the channel): the level 1 kinds ("Needs you") `post_comment`, `comment_reply` and `mention`, and since #1475 the level 2 kinds ("Following") `thread_comment`, `thread_reply`, `space_post`, `space_chat`, `lesson_new` and, since #1513, `event_new` (a new event in an events space, for the members at "Everything").

1. Callers never write rows. `CreatePost`, `CreateComment` and `CreateChatMessage` call `Notifies::notify()`, which queues `App\Jobs\WriteNotifications` on the queue `notifications`, after the commit. The `Update*` actions never call it. `Notify::silenced(fn)` runs a callback without any notification (imports, seeders), and an item older than 15 minutes at the moment of the event is skipped.
2. The rules live in `Notify::deliver()`: one row per recipient and item (mention, then reply, then comment), never for the own action, only when the recipient can view the item (`NotificationAccess::canView`: the membership and `SpacePolicy::view`), never for a member who blocked the author. An open (unread) row for the same post or parent comment bundles the new comment (the author changes, `seen_at` is cleared, `updated_at` moves). Mentions do not bundle, and 3 unread mention rows from one author in an hour are the cap.
3. Rows go away: the cascades (post, comment, space, community, account), `DeleteChatMessage` (a soft delete), `LeaveMarkers` (posts and comments that stay as markers, for `DeleteComment`, `DeletePost` and `DeleteMemberContent`, ADR 0043 and 0050), `DeleteMemberContent` (chat messages that stay as markers), `Community::removeUser`, `BlockMember`, and the job `PruneUnviewableNotifications` (started by `AccessGroupsChanged`, `CommunityMembersChanged` and `UpdateSpace`). `ListNotifications` runs `NotificationAccess::prune()` before every list and count: a row that the member cannot view any more is not returned, not counted, and deleted.
4. The list builds the titles, the excerpts and the authors of a bundle (`NotificationBundles`) from the live content with a fixed number of queries. `NotificationResource` is the one serialization. Its `target` holds what the client needs to open the exact item: `post_slug`, `post_cuid` (the thread to follow or to mute), `comment_cuid`, `chat_message_cuid`, `thread_cuid`, and `resource_cuid` with `resource_slug` (the resource page is addressed by its slug). The bell and the panel are described in [the front-end design](frontend.md#notifications-the-bell-and-the-panel).
5. Seen and read: `MarkNotificationsSeen` (the lines on screen, the post page through `PostController::show`) and `MarkNotificationsRead` (opened or done, undo, and `readChat()` called by `MarkChatAsRead`). A read chat mention moves the chat cursor forward, never backward.
6. `App\Events\Notifications\NotificationsChanged` (`notifications.changed`, with `notification_cuid` and `community_cuid`) goes to `user.{userCuid}` on the queue `broadcasts`.
7. The worker reads `broadcasts,notifications,default`, in this order (`docker/8.5/supervisord.conf`, `SELF_HOSTING.md`). A second worker reads only the queue `mail` of the notification mail (see Email delivery).
8. API: `GET /notifications` (with `level`), `GET /notifications/count`, `POST /notifications/seen`, `POST /notifications/caught-up`, `PUT /notifications/{notification}/read`, `POST /notifications/{notification}/unread`, with the abilities `notifications:read` and `notifications:write` (not in the default set).
9. Follows and space levels (#1475, [ADR 0042](../adr/0042-notifications.md), "Slice 2"). `notification_follows` (a post or a first-level chat message, the state `following`, `muted` or `stopped`, the reason `wrote`, `replied` or `manual`) is written by `FollowThreads` (contract `FollowsThreads`; a mute keeps the earlier state in `state_before_mute` and `reason_before_mute`, and `unmute()` gives it back, #1509: no row again for a thread that had none, `DELETE .../mute`; any other change of the state forgets it): `CreatePost`, `CreateComment` and `CreateChatMessage` call `followByWriting()`, a mention never does. `notification_space_levels` (`all`, `mine`, `none`) and `spaces.default_notification_level` are read through `SpaceNotificationLevels`, the one place of the rules (the choice of the member, then the default that a manager set with `UpdateSpace`, then the built-in default of the kind of space), and written by `ChooseSpaceLevels` (contract `ChoosesSpaceLevels`).
10. Level 2 is written by `Notify::fanOut()`, from the queued job `FanOutNotifications` (the first one of a new post waits 5 minutes, `notify_now` skips the wait). `LevelTwoFanOut` finds the recipients of one chunk (the followers, the members at "Everything", the members who started the course) and applies the access, the blocks and the levels; `LevelTwoWriter` bundles and inserts the rows with a fixed number of queries. A chunk is 500 members and its own job on the queue `notifications`; it queues the next one when it was full. A member who gets a level 1 row for the item gets no level 2 row for it. A muted space or thread gives nothing at any level: `Notify::deliver()` removes the members who muted (`SpaceNotificationLevels::mutedAmong`) before it writes. `CommunityRoles::withKnownAdmins` lets the check of many members of a `groups` space read the roles with one query.
    10a. `event_new` (#1513, ADR 0053): `CreateEvent` and `SetEventState` call `notify()` at the first publication of an event whose start is in the future. The age guard does not apply (a draft can be old), there is no level 1 row, and the fan-out waits 5 minutes unless `notify_now` (`SpacePolicy::moderate`). The job reads the event again: an event that is not published any more, that started, or that has no host tells nobody. The recipients are the members at "Everything" for the space (the built-in default of an events space), never the host, never a member who blocked the host. One row per member and event (`dedupe_key` `event_new:event:{id}`, `notifications.event_id`, the partial unique index `notifications_event_unique`): these rows do not bundle. `NotificationResource` gives the title and the excerpt of the event, `target.event_cuid`, `target.event_slug` and `event_starts_at`; the client writes the start in the zone of the reader.
11. The resources read what the controllers set for a page with one query (`NotificationFollowStates::attach()`): `follow_state` on `PostResource` and `ChatMessageResource`. `NotificationBundles` gives `items_count`, the authors and the `follow_reason` of a page of rows with a fixed number of queries. `NotificationResource` adds `reason`, `follow_reason`, `items_count` and `target.page_slug`.
12. Rows go away: `PruneNotifications::forMember` also deletes the follows and the levels of the member in the community, `forPosts`, `forChatMessage` and `forChatMessages` the follows of a marker, `forMutedSpace` and `forMutedThread` the open rows of a mute, and `forAccess` (the job `PruneUnviewableNotifications`) also deletes the follows and the levels of the members who cannot view the space any more (`NotificationAccess::pruneChoices`). The member export lists the follows and the levels (`notification_follows`, `notification_levels`).
13. Where I am (#1476, [ADR 0048](../adr/0048-where-i-am.md)): `users.time_zone` (set only by a request of the member: `UpdatesTimeZones`, the sign-up form, the profile form, `PUT /v1/user/time-zone`; `App\Support\TimeZones`), `users.last_visit_on` (one date, written by the middleware `RecordLastVisit` through `RecordsLastVisits`, at most once a day, hidden everywhere, in the own export; a date 7 days old or more waits in the session key `catch_up_since` and becomes the shared prop `catchUp`, which `POST /v1/notifications/caught-up` forgets), `users.notification_popups` (`PUT /v1/user/notification-settings`). `GET /v1/communities/{community}/catch-up` (`BuildsCatchUp`, `BuildCatchUp`) builds the card: at most 5 lines from the live content, with a fixed number of queries. `space_visits` (`TracksSpaceVisits`) and `spaces.last_content_at` feed `has_new` and `notification_level` in `SpaceTree` and `SpaceResource`; the pages of a space and the create actions of posts, comments, pages and resources write them, `Community::removeUser` forgets the visits. `GET /v1/notifications/count` also gives `messages` (`Conversations::unreadConversations`).

### Email delivery

The road of the notification mail (#1477, [ADR 0054](../adr/0054-email-delivery-the-mail-queue-the-budget-bounces-and-the-one-click-stop.md)). No notification mail exists before slice 4c: this is the ground it runs on.

1. Queues: only notification mail goes on the queue `config('mail.queue')` (`MAIL_QUEUE`, default `Constants::QUEUE_MAIL`, `mail`), read by a worker of its own (`queue_worker_mail` in `docker/8.5/supervisord.conf`, the service `queue-mail` in `compose.production.yaml`), so that a slow send never holds up `broadcasts`. The account mail (`MagicLink`, `CommunityInvitation`, `CommunityOwnershipTransferred`, `MailBudgetAlert`, the address verification) stays on `default`, with no limiter and no hold. `App\Support\MailSettings` reads and checks the values of `config/mail.php`.
2. `App\Mail\NotificationMail` is the base of every notification mailable: the queue (set in `queue()` and `later()`), the middleware `HoldNotificationMail` then `RateLimited('mail')`, `retryUntil()` 2 hours after the mail is due (queued, or the end of the delay of `later()`). In that time the worker ignores `--tries`, so a release of the limiter never fails a job; after it, the worker fails the job when it takes it, so a mail that waited for too long is not sent late. `$maxExceptions = 3`, `backoff()` 60, 300 and 1 800 seconds by the number of the attempt (a release counts as an attempt).
3. The limiter `mail` (`AppServiceProvider`): `MAIL_RATE_LIMIT_PER_SECOND` per second (default 5, 0 for none), one key for all mail workers.
4. `App\Jobs\Middleware\HoldNotificationMail`: while `ChecksMailBudgets::isHeld()`, the job ends as done and sends nothing. A `TransportExceptionInterface` whose previous exceptions hold a `Resend\Exceptions\ErrorException` of the type `monthly_quota_exceeded` or `daily_quota_exceeded` sets the hold (to the next month, or to the next 00:00 UTC) and ends the job; any other error is thrown again.
5. The count: `App\Listeners\CountSentEmails` (`MessageSent`) adds the recipients of the envelope of every sent mail to `mail_usage_months` (one row per calendar month in UTC) through `App\Contracts\Mail\RecordsSentEmails` (`RecordSentEmails`, one `insert ... on conflict` statement). It never throws.
6. The budget: `mail:check-budget` (hourly) calls `App\Contracts\Mail\ChecksMailBudgets` (`CheckMailBudget`). The quota is `MAIL_MONTHLY_QUOTA`, else the monthly limit of the provider; the use is the count of the provider (`App\Contracts\Mail\ReadsMailUsage`, `ResendMailUsage`, only for the mailer `resend` with a key), else `sent_count`. At 80% a warning and `MailBudgetAlert` to `MAIL_ALERT_TO`, once a month (`alert_80_sent_at`); at `MAIL_NOTIFICATION_HOLD_PERCENT` the hold (`held_until`) and a second alert (`hold_notified_at`); the daily limit of the provider holds until the next day with a log line only. Below the hold percent, the check clears the hold, but not after a failed read of the provider. A new month has a new row, so its hold starts empty.
7. `app:health --only=mail-queue` fails when a job of the mail queue (database driver) waits for more than `Constants::MAIL_QUEUE_STALE_MINUTES` and no job of that queue ended in the same time. `App\Listeners\RecordMailWorkerActivity` (`JobProcessed`) notes each ended job of the mail queue in the cache (`App\Support\MailWorkerActivity`): a long backlog behind the rate limit is not a stopped worker. The listener never throws.
8. Nothing of it is a route, an ability or an API document: the operator reads the numbers in the log, the alert and `mail:check-budget`.

### Sign in with an OpenID Connect provider

The provider is an external account service: what it must implement (the sign-in, the new sign-in, the entitlements, the change calls) is in [the external account service](external-account-service.md).

Sign-in with a provider (#1385, [ADR 0045](../adr/0045-openid-connect-sign-in.md)) is a generic OpenID Connect client, on only when `OIDC_ISSUER`, `OIDC_CLIENT_ID` and `OIDC_CLIENT_SECRET` are set (`App\Support\OpenIdConnect` reads `config/services.php`). Without them the two web routes answer 404 and the shared prop `oidc` is null.

1. `GET /auth/oidc/redirect` and `GET /auth/oidc/callback` (`OpenIdConnectController`, limiter `oidc`) keep `state`, `nonce`, the PKCE verifier and the start time in the session (`oidc.flow`), used once. The callback checks the flow, asks the client for the identity, asks `SignsInWithOpenIdConnect` which account it signs in, then signs in (or goes to the two-factor challenge of Fortify with `login.id`), or sends the member back with `oidc_error` in the flash (shared with the pages as `oidc.error`).
2. `App\Contracts\Auth\ReadsOpenIdConnectIdentities`, bound to `App\Actions\Auth\OpenIdConnectClient`, does the discovery, the keys, the code exchange and every check of the ID token with `firebase/php-jwt`. Requests go through `Http` with a timeout, no redirect and `SafeUrl` (loopback only in the local environment). The discovery document and the keys are cached for one hour under `oidc:discovery:*` and `oidc:jwks:*`, and the keys are read again once for an unknown key id. The client returns an `App\Support\OpenIdIdentity` and nothing else: no token leaves it.
3. `App\Contracts\Auth\SignsInWithOpenIdConnect`, bound to `SignInWithOpenIdConnect`, holds the cases of the ADR, in order. `LinksIdentities` (`LinkIdentity`) and `UnlinksIdentities` (`UnlinkIdentity`) are the only writers of `user_identities`. A new account goes through `CreateNewUser::createAccount()`, the same path as the sign-up form.
4. `user_identities` (model `UserIdentity`): `cuid`, `user_id` (cascade), `issuer`, `subject` (hidden from every serialization), `linked_at`, `last_used_at`, unique `(issuer, subject)` and `(user_id, issuer)`. `users.password` is nullable: `User::hasPassword()` tells whether the account can sign in with a password, and code that checks a password reads `$user->password` as nullable.
5. API: `GET /user/identities` and `DELETE /user/identities/{identityCuid}` (`UserIdentityApiController`, abilities `identities:read` and `identities:write`, not in the default set). `GET /ping` answers `data.oidc` with the label of the button, or null. The profile page gets `connectedAccounts` and `hasPassword` from `JetstreamServiceProvider::withConnectedAccounts()` when the instance has a provider.
6. Logs hold the reason code, the issuer and cuids. Never a token, a code, a subject or an address.

### Entitlements of the provider feed the access groups

The entitlements of the provider (#1386, [ADR 0047](../adr/0047-entitlement-rules-feed-access-groups.md)) are on only when the sign-in is on and `OIDC_ENTITLEMENTS_URL` is a safe address with `{subject}` once in its path (`App\Support\OidcEntitlements` reads `config/services.php`). Without it there is no route (404), no job, no scope and no screen.

1. `App\Contracts\Auth\ReadsSubjectEntitlements`, bound to `EntitlementsClient`, reads the keys of one subject with the client credentials grant. It uses `OpenIdConnectClient` for the discovery, the keys and the safe requests (`clientCredentialsToken()`, `authorizedGet()`, `verifySignedEvent()`). The token is cached encrypted under `oidc:entitlements:token:*` until a minute before it expires. A failure is an `EntitlementsReadException` with `temporary` true (network, timeout, 5xx, 429, a body that is not what the provider documents) or false (a 401 or 403 after a fresh token).
2. `App\Contracts\Entitlements\RefreshesEntitlements` (`RefreshEntitlements`) reads, then replaces the rows of `user_entitlements` (`user_id`, `issuer`, `key`) and sets `user_identities.entitlements_checked_at` in one transaction, under a cache lock per identity, then runs the sync. 404 deletes the rows. Only keys are stored.
3. `App\Jobs\ReadEntitlements` (the id of an identity) runs a read: after a sign-in through the provider and after a connection (`EntitlementReads::afterSignIn()`), after a join or an accepted invitation in a listed community that has rules (`EntitlementReads::afterJoin()`), when the change call arrives, and for every identity of the provider once a night (`php artisan entitlements:sync`, 02:30, one a second). A temporary failure releases the job after 60 s, 5 min, 30 min, 2 h and 6 h. A refusal by the provider is logged once an hour.
4. `POST /webhooks/oidc/entitlements` (`Webhooks\OidcEntitlementsWebhookController`, in `routes/web.php` without the cookie, session, CSRF and Inertia middleware, limiter `oidc-entitlements`) hands the body to `ReceivesEntitlementEvents` (`ReceiveEntitlementEvent`), which asks `OpenIdConnectClient::verifySignedEvent()` (algorithm, `typ`, key, signature, `iss`, `aud`, `exp`, `iat` at most 5 minutes old), checks `event`, `sub` and `jti`, and records the `jti` in the cache for 24 hours with an atomic add, after every other check. Valid or repeated: 202. Invalid: 401, empty body.
5. `access_group_rules` (model `AccessGroupRule`) and the flags `manual` and `by_rule` of `access_group_members` (model `AccessGroupMember`, a check constraint keeps one of them true). `App\Support\AccessGroupMemberships` is the only writer of the rows: `addManual()`, `removeManual()` (clears `manual` only), `setByRule()` and `releaseByRule()`, each on a locked row. `AddAccessGroupMember` and `RemoveAccessGroupMember` use it. `Community::removeUser()` still deletes every row of the member in the community.
6. `App\Contracts\Entitlements\SyncsEntitlementRules` (`SyncEntitlementRules`): `syncMember()`, `syncCommunity()` (members in chunks, from `SyncCommunityEntitlementRules`, queued after the commit when a rule changes), `clearMember()` (an unlink) and `clearUnlisted()` (the nightly clean-up of communities that are not listed, or the feature off). It sends `AccessGroupsChanged` and `SpaceTreeChanged` once per community that changed. It never calls the provider.
7. `SetsAccessGroupRules` (`SetAccessGroupRules`) replaces the keys of a group, for `manageAccessGroups`, in a listed community. API: `GET` and `PUT /communities/{community}/access-groups/{accessGroup}/rules`, `GET /user/entitlements` (`entitlements:read`, not in the default set). `GET .../access-groups/{accessGroup}/members` returns `sources` for each member (`AccessGroupMembershipResource`). `AccessGroupResource` adds `entitlement_keys` only for those who manage the groups in a listed community. The profile page gets `entitlements` (`keys`, `checked_at`) from `JetstreamServiceProvider::withConnectedAccounts()`, and the settings page of a community `permissions.canManageEntitlementRules`.
8. `UnlinkIdentity` deletes the keys of the account and, for the account of the provider of this instance, every rule membership of the member. The member export lists the keys and where each membership comes from, the community export the keys of the rules.

### Presence channels

Presence (#1187, [ADR 0027](../adr/0027-presence-channels.md)) uses two presence channels, authorized by classes in `app/Broadcasting`: `community.{communityCuid}.online` (`CommunityOnlineChannel`) and `community.{communityCuid}.space.{spaceCuid}.presence` for chat spaces (`SpacePresenceChannel`). They identify members by cuid and share their public summary only (`Presence::info`). A member with `show_online_status` false never joins; their pages read `GET .../online-members`, which asks Reverb through `ReadsPresenceChannels`. Typing indicators are client events (`typing`, with a scope) on the space channel; `config/reverb.php` accepts client events from channel members only, and Reverb adds the cuid of the sender.

### Client-side

On the client-side, we have abstracted Laravel Echo behind a React hook called `useSocket`. This hook makes it easy to listen to channels/events, and to handle incoming events. Presence channels go through `usePresence` (members, typing indicators, polling for hidden members).
You can find examples of how to use this hook in the `Dashboard` page. See the [front-end design](frontend.md).
