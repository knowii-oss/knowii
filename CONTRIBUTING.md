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

For the Docker-only setup below, only Docker with Compose and Linux containers is needed; PHP, Composer, and Node.js run inside the application container.

For the host-based setup:

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
- Run `npm ci` to install the front-end dependencies
- Run `npm run setup` to enable the Git hooks (see "Git hooks" below)
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

### Docker-only setup (PowerShell)

No host installation of PHP, Composer, Node.js, or Sail is required. Start Docker Desktop in Linux-container mode, then run from the repository root:

```powershell
Copy-Item .env.example .env
```

Set `APP_URL=http://localhost:4200` in `.env`. Keep `WWWUSER=1000` and `WWWGROUP=1000` when using Compose directly. For local development without an email provider, set `MAIL_MAILER=log`; emails will be written to the application logs rather than delivered.

If public container registries are restricted, set `SAIL_BASE_IMAGE`, `POSTGRES_IMAGE`, `REDIS_IMAGE`, and `BROWSERLESS_IMAGE` in `.env` to the equivalent image references from your approved registry. Defaults remain Ubuntu 24.04, PostgreSQL 18, Redis Alpine, and Browserless Chromium from GHCR. Authenticate using `docker login` if your registry requires it; never put registry credentials in tracked files. The application image build also needs access to its APT repositories and Composer/Node.js download sources.

If your organization uses HTTPS inspection, put its approved public CA certificates (PEM format, `.crt` extension) in `docker\8.5\certificates` before building. Files in that directory are ignored by Git. The image adds them to the system trust store and makes that store available to Node.js as well; TLS verification remains enabled. Never copy private keys into this directory or force-add certificates to Git. Images built with these certificates contain them, so keep those images within your organization's approved environment. Rebuild the application image after changing certificates.

Run each command in order, stopping if any fails:

```powershell
docker compose build laravel.test
docker compose run --rm --no-deps laravel.test composer install
docker compose run --rm --no-deps laravel.test php artisan key:generate
docker compose run --rm --no-deps laravel.test npm ci
docker compose run --rm --no-deps laravel.test npm run build
docker compose up -d --wait pgsql redis chrome
docker compose run --rm --no-deps laravel.test php artisan migrate
docker compose up -d laravel.test
```

Open `http://localhost:4200`. The queue worker and Reverb start with the application container. Built frontend assets do not require a separate Vite development server. Dependencies are installed inside containers but stored in the bind-mounted `vendor` and `node_modules` directories.

If Docker Desktop asks to share the repository directory, approve that specific directory. Dependency extraction on Windows bind mounts can be slow; keep the installs sequential. If Composer reports a process timeout while extracting packages, retry with a longer timeout:

```powershell
docker compose run --rm --no-deps -e COMPOSER_PROCESS_TIMEOUT=1800 laravel.test composer install
```

On subsequent runs, use `docker compose up -d` to start and `docker compose down` to stop. Database data remains in Docker volumes.

#### Upgrade from PostgreSQL 17 (volume `sail-pgsql`)

The development database moved to PostgreSQL 18 in a new volume, `sail-pgsql-18` (PostgreSQL 18 images keep their data under `/var/lib/postgresql`). The old volume `sail-pgsql` is not used anymore. To keep your local data, dump it before you pull this change, then restore it:

```bash
# Before the upgrade (PostgreSQL 17 still running)
docker compose exec -T pgsql pg_dump -U sail -d laravel --format=custom > laravel-pg17.dump

# After the upgrade
docker compose up -d --wait pgsql
docker compose exec -T pgsql pg_restore -U sail -d laravel --no-owner --exit-on-error < laravel-pg17.dump
```

If you do not need the data, run `php artisan migrate --seed` instead. When everything works, remove the old volume with `docker volume rm knowii_sail-pgsql`.

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

### Architecture and design

The architecture, the design of the back-end and the front-end, the naming conventions, the rules and the architecture decision records (ADRs) are in [`documentation/`](documentation/README.md). Read them before you change the structure of the code, and update them in the same pull request.

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

### Git hooks (Git 2.54+)

This repo uses [Git's built-in config-based hooks](https://github.blog/open-source/git/highlights-from-git-2-54/) (introduced in **Git 2.54**) instead of Husky. The hook definitions live in a tracked `.gitconfig` file at the repo root and are committed to source control.

#### Enable the hooks once per clone

After the first `npm install`, run:

```bash
npm run setup
```

That script runs `git config --local --replace-all include.path ../.gitconfig` — the path is relative to `.git/`, so `../.gitconfig` resolves to the repo root. Git then reads the tracked `.gitconfig`, picking up:

- **`pre-commit` → `scripts/git-hooks/pre-commit.sh`** — runs `npm run lint`, `npm run format:affected` and `composer audit`.
- **`commit-msg` → `npx --no -- commitlint --edit`** — validates the commit message against `commitlint.config.js`.

Confirm the hooks are wired up:

```bash
git hook list pre-commit
git hook list commit-msg
```

#### Requirements

- Git **≥ 2.54** is required for config-based hooks. Older Git versions silently ignore them, so the hooks won't run (but nothing will break).

#### Why not Husky?

Husky requires a `prepare` post-install script to inject shims into `.git/hooks/`. Git 2.54 provides the same functionality natively, the hook config is plain text in version control, and there's no install-time magic.

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
  - if you have made changes to database migrations or model classes, then update the model docblocks: `composer generate-phpdoc`, then follow the conventions in `documentation/architecture/backend.md` (Model docblocks)
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
