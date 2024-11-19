# Contributing to Knowii

As a contributor, here are the guidelines we would like you to follow.

## General guidelines

Third party contributions are more than welcome! Here you'll find all the information you need to get started.

First, make sure to read the [general guidelines](https://github.com/knowii-oss/.github/blob/main/CONTRIBUTING).

## Don't know where to start?

Take a look at the project's open [issues](https://github.com/DeveloPassion/knowii/issues).
This contains tons of ideas to help us out.

## Development

### Pre-requisites

- PHP
- PHP zip extension: `sudo apt install libzip-dev php-zip` and uncomment `extension=zip` in 'php.ini'
- Composer
- Docker
- docker-compose
- Sail

### Environment setup

- Copy the `.env.example` file to `.env` and fill in the necessary values
- Run `composer install` to install the dependencies
- Run `php artisan key:generate` to generate an encryption key
- Run `npm install` or `npm run install:linux` if you're on Linux
- Run `npm run build` at least once to build the assets under `public/build` (at least `.vite/manifest.json` which is required for the application to start)
- Run `composer clean` to build/rebuild the Sail docker images
- Run `sail up` to start the Sail containers
- Run `sail artisan migrate` to run the DB migrations in the Sail containers
- Run `sail down` to stop sail

Necessary environment keys for development:

- `RESEND_KEY`: for sending emails

When new environment variables are exposed to the front-end via `VITE_` in the env file, those should be added to `vite.env.d.ts`.

### Docker usage

During development, we use Laravel Sail with Docker and docker-compose.

- To start the application, run `./vendor/bin/sail up` or `./vendor/bin/sail up -d` to run it as a daemon
- To stop it, run `./vendor/bin/sail down`

WARNING: After making changes to the Dockerfile, make sure to rebuild the container images using `./vendor/bin/sail build --no-cache`

### Running the application

Run `composer start` to start Sail, and Vite (the frontend development server).

If you want more control you can run the parts separately:

- Run `./vendor/bin/sail up` to start the back-end
- Run `./vendor/bin/sail npm run dev` to start the front-end (inside the Docker container)
- Run `./vendor/bin/sail php artisan queue:listen` to start the queue worker (necessary for processing WebSocket events)

WARNING: You have to start the front-end server inside the Docker container, because it already reserves the port exposed by Vite.

You can also use `./vendor/bin/sail bash` to get a shell within the container.

If you want to run the production version locally, you can:

- Start Sail using `sail up` (so you can look at the back-end logs)
- Run `npm run build` to build the front-end application
- Go to `http://localhost:4200/`

### Useful commands

- `composer sail:cache:clean`: Clean the caches
- `composer sail:clean`: Stop the application and rebuild the container images
- `composer serve`: Start the application
- `composer start`: Start the application
- `composer test`: Test the back-end code
- `npm run test`: Test the front-end code

### IntelliJ / PHPStorm

First, install the following plugins:

- Docker (useful for Sail)
  - PHP
  - PHP Annotations
  - Php Inspections (EA Extended)
  - PHPUnit Enhancement
  - Pest
  - PhpClean
  - PHPStan Support
  - PHP Toolbox
  - PHP Advanced AutoComplete
  - PHP Command Line Tool
  - PHP Remote Interpreter
  - PHP Docker
  - PHP WSL Support
  - PHP RegExp Support
  - PHP Class Templates
  - PHP Foldings
  - JsonToPHP
  - Blade
  - Laravel Idea
  - Laravel Query
  - Inertia.js Support

Also, install the XDebug helper in your browser: https://chromewebstore.google.com/detail/xdebug-helper/eadndfjplgieldjbigjakmdgkmoaaaoc. It makes it easy to connect XDebug to IntelliJ / PHPStorm while using the Web application.

To run the tests from IntelliJ / PHPStorm, you need to configure the PHP interpreter, and the test framework.

Go to Settings > Languages & Frameworks > CLI Interpreter, and add a new one:

- From Docker, Vagrant, WSL, etc
- Choose Docker compose
- Locate your Docker engine
- Choose `./docker-compose.yml` for the configuration file
- Select `laravel.test` as service
- Select `Connect to existing container ('docker-compose exec')`

WARNING: Make sure that `.idea/php.xml` points to `/var/www/html` and NOT `/opt/...`. Otherwise, the tests won't run.
If needed, replace this line: `<remote_data INTERPRETER_PATH="php" HELPERS_PATH="/opt/.phpstorm_helpers" VALID="true" RUN_AS_ROOT_VIA_SUDO="false" DOCKER_ACCOUNT_NAME="Docker" DOCKER_COMPOSE_SERVICE_NAME="laravel.test" DOCKER_REMOTE_PROJECT_PATH="/var/www/html">`

For tests, go to Settings > Languages & Frameworks > PHP > Test Frameworks:

- Click on "+"
- Select Pest
- Choose the right PHP interpreter: `laravel.test`
- Set the path to the Pest executable: `/var/www/html/vendor/bin/pest`

### Adding new pages

To add new pages to the application, you can either create a dedicated controller (e.g., for API endpoints), or declare the page in the `web.php` routes file. Example: `Route::inertia('/contact', 'Contact');`.
The initial data the page needs should be passed via Inertia. All further actions should be handled through the API, not via Inertia. This ensures that the API is on par with the UI.

### Global data

If you need to pass data to all pages (e.g., the user's list of communities), you can update `HandleInertiaRequests` to load and include the data you need.
If it's completely on the client-side, then you can leverage the React Context defined in `app-context.tsx`. A provider has been added to `main.tsx` and is available on all pages. For instance, the `Toast` Prime React component is added to it by the `AppLayout`, then used in the `Dashboard` page and other components.

### Database

#### Creating new tables

When creating new database tables, make sure to include a `cuid` string field right after the `id` field. This field is used, and exposed by the API. The `cuid` column is filled in automatically when creating new entities, assuming that you don't forget to add `use GeneratesCuid;` to the model.

You can learn more about cuid2 here: https://github.com/paralleldrive/cuid2?tab=readme-ov-file#why

#### Administration

If you want to look at the database during development, you can use the `npm run db:admin` command. It will start a container with PgAdmin.
Once started, you can go to `http://localhost:5050, and log in using:

- Username: root@knowii.net
- Password: password

#### Cleanup

If you need to restart from scratch, you can reset the database to the default state and run all migrations using `composer db:clean` or `sail php artisan migrate:fresh`.

#### Backup

Database backups rely on the laravel backup package: https://spatie.be/docs/laravel-backup/v8/introduction
To backup the database, run the following command: `php artisan backup:run`.
If you want to test it locally in Sail, then run 'sail php artisan backup:run'

By default, backups are stored under `storage/app/Knowii`

### Actions

Modifications (e.g., creating a community, deleting a community, etc) should be implemented using actions. Actions are classes that encapsulate the logic of a single task. They are located in the `app/Actions` directory.
Actions should:

- Be named after the task they perform (e.g., CreateCommunity, DeleteCommunity, etc)
- Be placed in the `app/Actions` directory
- Be registered in the `app/Providers/AppServiceProvider.php` file
- Be used in the API controllers to perform the task (cfr API section below)
- Verify authorizations, perform input validation, perform business validation, and perform the actual changes (e.g., saving a new entity to the database)

To verify authorizations, use Gate: `Gate::forUser($user)->authorize('<operation>', $item);`. An AuthorizationException will be thrown if the user is not authorized.

#### Authorization

Authorization is done using Laravel's built-in authorization system. Policies are used to define the authorization rules. Policies are located in the `app/Policies` directory, and must be named after the model they apply to, with the `Policy` suffix (e.g., `CommunityPolicy`). When names don't match a given model, they must be registered manually in the `AppServiceProvider` file.

Authorizations are checked in Action classes.

More details: https://laravel.com/docs/11.x/authorization

### Error handling

- Validation should be done using `Validator::make(...)->validate()` calls. This will throw a `ValidationException` if the validation fails. Such exceptions will be converted automatically to a JSON response by the global exception handlers
- Validation constraints should use constants defined in the `App\Constants` class. Those should be reflected on the front-end as well in the `constants.ts` file
- When there are business related problems, a `BusinessException` should be thrown. This exception should be caught in the global exception handler, and converted to a JSON response as well
- Where there are other problems (e.g., unavailable database), a `TechnicalException` should be thrown. This exception should be caught in the global exception handler, and converted to a JSON response as well

The global exception handlers are located in `bootstrap/app.php`. They must translate all exceptions to JSON when the client requests a JSON response.

### API

#### Design

The API loosely takes inspiration from the following design guide: https://github.com/NationalBankBelgium/REST-API-Design-Guide/wiki
Notably, the way errors are returned is different/simpler, and the root-level response structure is different.

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

#### Architecture and data flow

- API Controllers are under `app/Http/Controllers/API`
- API Controllers are named `SomethingApiController`
- API Controllers are registered in the `routes/api.php` file
- API Controllers should include OpenAPI annotations
- They should NOT validate the input (not their responsibility)
- They should delegate operations to a dedicated action classes (e.g., CreateCommunity)
- They should not catch exceptions returned by the action classes (that handle validation). Those are handled by the global exception handler (without leaking sensitive information)
- They should leverage the ApiResponses trait to return consistent responses

Data flow:

- Request > API Controller > Action class (authorization, input validation, business validation, ...) > Database
- Action class > API Controller > ApiResponses > Response
- OR Action class > Exception > Global Exception Handler > Response

#### OpenAPI

This project is using the Laravel OpenAPI package:

- Documentation: https://vyuldashev.github.io/laravel-openapi
- Source code: https://github.com/TartanLeGrand/laravel-openapi

For parameters, make sure to read: https://vyuldashev.github.io/laravel-openapi/paths/parameters.html

For examples, look at the `LoginApiController`.

The OpenAPI documentation can be generated using the `php artisan openapi:generate` command.
It is also exposed via: http://localhost:4200/openapi

To test it, you can use Swagger and paste in the schema: https://editor.swagger.io/

#### Testing

The API can be tested via [Bruno](https://www.usebruno.com/). The Bruno workspace is included in the `documentation/api` folder, including example requests.
To use it, you can copy the `documentation/api/.env.example` file to `documentation/api/.env` and fill in the necessary values. This is a safety measure to avoid checking in secrets while testing the API. We use the approach documented here: https://docs.usebruno.com/secrets-management/dotenv-file

WARNING: If you make changes to the API, then don't forget to add tests, and to update that JSON file.

#### Slugs

We create slugs for the different concepts using the following library: https://github.com/cviebrock/eloquent-sluggable
Take a look at the `Community` model and `CreateCommunity` to see how it's used. Basically, the `slug` field need to exist on the model, the model must use certain traits, implement a function, and the library derives the slug automatically based on some other field. For instance, we use the "name" field for communities.
The library ensures that slugs are unique, which is key.

### WebSockets and Events

#### Design

Knowii also includes WebSockets support for first-party use (i.e., for itself). The WebSockets API is used to avoid having to fetch/poll data through the RESTful API. When entities are created/updated/deleted, and at other occasions, events will be pushed to clients using WebSockets. Specific pages, such as the Dashboard, will listen to those events, and will be able to update the UI. This is nice because the UI is thus able to discover everything that has changed since it loaded, and to adapt accordingly.

Technically, Knowii uses:

- Laravel Reverb on the back-end (which uses the Pusher protocol)
- Laravel Echo on the front-end to connect, and listen to events
- An event queue stored in the database (not in Redis for now) and a worker to process that queue, and dispatch the events

For local development, Sail has been configured to run the WebSockets server (Laravel Reverb), and the worker.

#### Event classes

Events that can be dispatched and processed within Knowii (with or without WebSockets) should be created in the `App\Events` directory. They should be named after the entity they relate to, and the event they represent (e.g., `CommunityCreated`). They should also be organized per entity in subdirectories. At the time of writing, we have:

- `App\Events\Communities\CommunityCreated`
- `App\Events\Communities\CommunityUpdated`
- `App\Events\Communities\CommunityDeleted`
- `App\Events\CommunityResourceCollections\CommunityResourceCollectionCreated`
- `App\Events\CommunityResourceCollections\CommunityResourceCollectionUpdated`
- `App\Events\CommunityResourceCollections\CommunityResourceCollectionDeleted`
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
  return (new CommunityResource($this->community))->toArray(request());
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
    new PrivateChannel(Str::of(Constants::$WS_CHANNEL_COMMUNITY)->replace(Constants::$WS_CHANNEL_COMMUNITIES_COMMUNITY_PARAM_COMMUNITY_CUID, $this->community->cuid)),
  ];

  // Emit events about public communities to the public channel
  if (KnowiiCommunityVisibility::Public === $this->community->visibility) {
    $retVal[] = new PrivateChannel(Constants::$WS_CHANNEL_COMMUNITIES);
  }

  return $retVal;
}
```

Note how the `broadcastOn` method emits events to the community channel, and to the public channel if and only if the community is public. This is a good practice to ensure that the events are dispatched to the right clients, and it is actually critical for security reasons.

Check out the CommunityEvent abstract class for a complete example.

Note that all those event classes can serve other purposes as well (e.g., sending mails, notifications, etc).

#### Event dispatching

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

#### Channels

The channels listed in `broadcastOn` should all be defined in `channels.php`. And the channel names should be defined as constants in `Constants.php`:

```php
// WebSockets
public static string $WS_CHANNEL_COMMUNITIES = 'communities';
public static string $WS_CHANNEL_COMMUNITIES_COMMUNITY_PARAM_COMMUNITY_CUID = '{communityCuid}';
public static string $WS_CHANNEL_COMMUNITY = 'community.{communityCuid}';
```

Notice that for cases where the channel name includes variables, we also create constants for the variables, so that we can easily replace them with actual values using a simple search/replace operation.

Example of channel definitions in `channels.php`:

```php
Broadcast::channel(Constants::$WS_CHANNEL_COMMUNITIES, static function (User $user) {
  // This is a public channel sharing events about public communities
  return true;
});

Broadcast::channel(Constants::$WS_CHANNEL_COMMUNITY, static function (User $user, string $communityCuid) {
  // This is a private channel reserved to community members
  $community = Community::whereCuid($communityCuid)->firstOrFail();
  $retVal = $user->allCommunities()->contains($community);

  return $retVal;
});
```

#### Client-side

On the client-side, we have abstracted Laravel Echo behind a React hook called `useSocket`. This hook makes it easy to listen to channels/events, and to handle incoming events.
You can find examples of how to use this hook in the `Dashboard` page.

### Configuration

#### Fortify

See `./config/fortify.php` for configuration options.

## Releasing a new version

If possible, add a `GITHUB_TOKEN` variable to your environment, so that the release script can create a release on GitHub. If it's not available, then the tag will be created and pushed, but the release don't be created and associated with the tag on GitHub.

To release a new version, follow these steps:

- Go to the "Release" action page of the project on GitHub: https://github.com/knowii-oss/knowii/actions
- Click on the "Run workflow" button
- Leave the default branch (main) as is, and click on the "Run workflow" button

Once completed, the release should be available on GitHub: https://github.com/knowii-oss/knowii/releases

## Deploying a new version to production

To deploy to production, all you have to do is merge changes into the `production` branch. To do so, run the "Deploy" action on GitHub.

This will create a PR that, once merged, will trigger a deployment to production. The deployment itself is handled by Laravel Forge.

## Submission guidelines

### Submitting an issue

Before you submit an issue, please search the issue tracker, maybe an issue for your problem already exists and the discussion might inform you of workarounds readily available.

We want to fix all the issues as soon as possible, but before fixing a bug we need to reproduce and confirm it. In order to reproduce bugs, we will systematically ask you to provide a minimal reproduction scenario.
Having a live, reproducible scenario gives us a wealth of important information without going back & forth to you with additional questions like:

- version of Knowii used
- a use-case that fails

A minimal reproducible scenario allows us to quickly confirm a bug (or point out coding problem) as well as confirm that we are fixing the right problem.

We will be insisting on a minimal reproducible scenario in order to save maintainers time and ultimately be able to fix more bugs.

Unfortunately, we are not able to investigate / fix bugs without a minimal reproduction, so if we don't hear back from you we are going to close an issue that doesn't have enough info to be reproduced.

You can file new issues by filling out our [new issue form](https://github.com/DeveloPassion/knowii/issues/new).

### Forking

Knowii's development is done using a forking model with Pull Requests (PRs), so the very first thing you should do is create your fork: https://help.github.com/articles/fork-a-repo/

### Integrating changes to your fork

Once you're found what you want to contribute to Knowii, then:

- Create a feature branch in your fork: `git checkout -b my-new-branch main`
- Configure the upstream
  - `git remote add upstream https://github.com/DeveloPassion/knowii.git`
  - reference: https://help.github.com/articles/configuring-a-remote-for-a-fork/

From then on, you may work on your feature at your own rhythm and commit/push to your fork.

Don't forget to write test cases (or adapt existing ones) for your changes!

### Keeping in sync

While you're busy developing your feature, and before you propose them, make sure that your fork is up-to-date with the upstream.

First, download the latest commits:

- `git fetch upstream`
- or `git remote update -p`

Then, integrate those changes to your fork (whatever branch you're working on).

First try a fast-forward merge: `git merge --ff-only upstream/main`

- that command tells git to merge the upstream branch ONLY if you local branch can be "fast forwarded" to the upstream branch (i.e., if it hasn't diverged)

If the fast-forward merge fails, then you'll have to rebase with the upstream (i.e., align): `git rebase -p upstream/main`

- the `-p` options tells git to preserve merges. This prevents git from linearizing the commits being rebased

Once done, make sure the history looks like what you expect: `git log --graph --oneline --decorate --date-order --color --boundary upstream/main`

Certainly so before creating a Pull Request (PR). If you don't do it then we'll request it anyways.

References:

- https://stackoverflow.com/questions/6406762/why-am-i-merging-remote-tracking-branch-origin-develop-into-develop
- https://help.github.com/articles/syncing-a-fork/

### Proposing your changes by submitting a Pull Request (PR)

Before you propose your changes, make sure that your fork is up-to-date with the upstream (see previous section) and that the whole test suite passes.

At this point, you'll probably want to do a rebase in order to squash your commits and align with our [commit message conventions](#commit) (you can always fix those during the rebase or using `git commit --amend ...`).

The idea there is to let you make as many commits as you want with or without respecting our commit message conventions, as long as you clean those when you're ready to send us a PR.

Once done, you may submit a new Pull Request (PR): https://github.com/DeveloPassion/knowii/pull/new/main

Note that if you didn't follow this, we'll probably ask for a cleanup/rebase before we merge your changes.

### Committing with Commitizen

To help you respect our [commit message conventions](#commit), you can use [Commitizen](https://github.com/commitizen/cz-cli).
Instead of running `git commit`, use `npm run commit` or `npm run cm`.
You'll be prompted to fill in any required fields and your commit message will be formatted according to our guidelines.

You can still use `git commit`, but be sure to follow the guidelines, otherwise [Commitlint](https://github.com/marionebl/commitlint) will throw an error during your commit.

### Workflow

Here's a summary of the workflow we recommend to contribute to Knowii along with the commands to use.

Workflow and commands:

- fork: through GitHub
- clone: `git clone https://github.com/<username>/knowii`
- add upstream: `git remote add upstream https://github.com/DeveloPassion/knowii`
  - that way you can fetch new commits that have been integrated into Knowii's main repository
- create a feature branch for whatever you want to work on
  - `git checkout -b feature/<name>`
  - you may include a specific issue number in the branch name (e.g., 24-awesome-feature)
- publish the feature branch on your fork
  - `git push -u origin feature/<name>`
- work on your feature branch
  - checkout the branch: `git checkout feature/<name>`
  - make changes
  - if you have made changes to model classes, then make sure to update the PHPdoc manually
  - if you have made changes to database migrations, then make sure to update the PHPdoc: `npm run generate-phpdoc`
  - ensure that the code is formatted correctly: `npm run format`
  - ensure that the code quality is maintained: `npm run lint`
  - regularly commit the changes: `git commit -a -m 'refactor(core): made it great again'`
- if needed, rebase your commits for a clearer change history
- push the changes to your fork's corresponding feature branch: `git push`
- update your fork/feature branch with upstream changes
  - first fetch the changes: `git fetch upstream`
    - alternative: `git remote update -p`
  - then merge or rebase
    - try fast-forward merge: `git merge --ff-only upstream/main`
    - rebase if fast-forward failed: `git rebase -p upstream/main`
  - see "Keeping in sync" section for details!
- create your Pull Request (PR); see "Proposing your changes by submitting a Pull Request (PR)" for details

Sometimes you might want to work with others on some feature.

In any case, after PRs are merged into Knowii, you can normally forget about the feature branches and delete those to keep your fork clean

- `git branch -d feature/<name>`

_It's important to keep in mind that anytime you want to continue working on an ongoing feature branch or start a new one, you'll need to fetch from upstream and merge (fast-forward) or rebase. Without this, you'll quickly fall out of sync and end up with nightmarish merges..._

Also, consider your fork to be transient. If you get lost at some point, then you can always rebase and accept everything from the upstream. If you still get lost with that and can't go back to a clean state, then just shelve your changes or create a patch file, then delete your fork/local repo and start over :)

Rinse and repeat :)

## <a name="commit"></a> Commit Message Guidelines

We have precise rules over how our git commit messages can be formatted. This leads to **more readable messages** that are easy to follow when looking through the **project history**.
We also use the git commit messages to generate our changelog.

We're using the following commit message format: `type(scope): subject`

#### Type & Scope

Each commit must specify a valid type and scope. Use the `npm run commit` command to make sure you comply.

#### Subject

The subject contains succinct description of the change:

- use the imperative, present tense: "change" not "changed" nor "changes"
- do not capitalize first letter
- do not place a period `.` at the end
- entire length of the commit message must not go over 50 characters
- describe what the commit does, not what issue it relates to or fixes
- **be brief, yet descriptive** - we should have a good understanding of what the commit does by reading the subject

### Body

Just as in the **subject**, use the imperative, present tense: "change" not "changed" nor "changes".
The body should include the motivation for the change and contrast this with previous behavior.

### Footer

The footer should contain any information about **Breaking Changes** and is also the place to
[reference GitHub issues that this commit closes](https://help.github.com/articles/closing-issues-via-commit-messages/).

**Breaking Changes** should start with the word `BREAKING CHANGE:` with a space or two newlines.
The rest of the commit message is then used for this.
