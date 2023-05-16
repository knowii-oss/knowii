# Contributing to Knowii

Third party contributions are more than welcome! Here you'll find all the information you need to get started.

As a contributor, here are the guidelines we would like you to follow.

## Code of Conduct

Help us keep Knowii open and inclusive. Please read and follow our [Code of Conduct](/CODE_OF_CONDUCT.md).

## Got a question or problem?

Create GitHub discussions: https://github.com/DeveloPassion/knowii/discussions

## Don't know where to start?

Take a look at the project's open [issues](https://github.com/DeveloPassion/knowii/issues).
This contains tons of ideas to help us out.

## Found a bug?

If you feel like you've discovered a bug, then a GH issue is clearly the way to go, with a follow-up PR indeed ;-)

## Missing a feature?

You can _request_ a new feature by submitting an issue to our repository. If you would like to _implement_ a new feature, please submit an issue with a proposal for your work first, to be sure that we can use it.

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
Instead of running `git commit`, use `npm run commit`.
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
  - make changes: `git add ...` then `git commit -m '...'`
  - regularly commit the changes: `git commit -a -m 'refactor(core): made it great again'`
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

#### Type

Must be one of the following:

- **feat**: A new feature
- **fix**: A bug fix
- **docs**: Documentation only changes
- **style**: Changes that do not affect the meaning of the code (white-space, formatting, missing semi-colons, etc)
- **refactor**: A code change that neither fixes a bug nor adds a feature
- **perf**: A code change that improves performance
- **test**: Adding missing tests or correcting existing tests
- **build**: Changes that affect the build system or external dependencies
- **ci**: Changes to our CI configuration files and scripts
- **chore**: Changes to the build process or auxiliary tools and libraries such as documentation generation
- **revert**: Reverts a previous commit

#### Scope

Must be one of the following:

- **all**
- **a11y**
- **code**
- **deps**
- **docs**
- **i18n**
- **qa**
- **release**
- **sec**

If the scope you want to specify is not in the list, you can ask to add it or add it in the commitlint
configuration file (`commitlint.config.js`) in the root.

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

## Dev environment setup

- Install node 16.17.1+
- Install npm 8.15.0+
- Install Docker
- Install psql (to be able to execute SQL scripts against the database, including the db seed script)
- Login to Supabase: `npx supabase login`
- Run `npm install`
- Create a file called `gcloud.json` under apps/knowii with the Google Cloud service account key (used for local logging
  with Supabase)
- Create apps/knowii/.env.local based on .env.example
- Initialize the local Supabase instance: `npx supabase init`
- Prepare and run the local Supabase instance: `npm run db:setup`
- Install the Stripe CLI: https://stripe.com/docs/stripe-cli
- If needed, run `stripe listen --forward-to localhost:3000/api/stripe-webhook` to redirect Stripe events to your local
  instance (useful to fill in the local database with Stripe plans)

Useful IDE extensions:

- MJML: tooling to preview the mjml templates and generate the HTML files from those
- Prettier: Formatting
- ESLint: Linting

## App

- Start the database: `npm run db:start`
- Run: `npm run start`
- Stop the database: `npm run db:stop`

## Environments

For development, database credentials are stored in:

- "apps/knowii/.env.local" (there's an example in .env.example)

To deploy DB migrations to production:

- Create a file called `.env.prod` under "apps/knowii"
- Run `npm run db:migration:deploy:prod`

The script invokes Prisma but forces a load of the production env file instead of the default one, based on the approach
described here: https://github.com/prisma/prisma/discussions/2392

## DB schema and migrations

There are a number of scripts to handle/ease DB migrations:

- To prepare a migration, run `npm run db:migration:prepare`
- To deploy a migration locally, run `npm run db:migration:deploy`
- To deploy a migration to production, run `npm run db:migration:deploy:prod`
- To check the migration status locally, run `npm run db:migration:status`
- To check the migration status in production, run `npm run db:migration:status:prod`
- To validate the DB schema locally, run `npm run db:schema:validate`
- To validate the DB schema in prod, run `npm run db:schema:validate:prod`

Note that the `:prod` commands should be used primarily from the CI/CD environment, not from your development machine

## Git pre-commit hooks

When you try to create a commit, hooks are executed to perform verifications and ensure code quality/formatting. Those
are configured by Husky. Check out the `.husky/pre-commit` file, as well as the lint-staged
configuration: `.lintstaged.js`

## Environment variables

When new environment variables are necessary, they should:

- Be added to ./app/knowii/.env.example
- Be added to ./apps/knowii/env.mjs (for type safety)
- Be configured on Vercel

## Developing components

Create reusable components in the 'client-ui' library. Test those using Storybook: `npm run storybook:client-ui`

## Adding a locale

To add a locale:

- Create the file under apps/knowii/messages

To enable a new NX library to benefit from i18n strong typing, copy the index.d.ts at the root of client-ui over to the
new library.

## Color theme

- Edit apps/knowii/theme.js

Use tools such as https://www.tailwindshades.com/ to generate the different shades

## Checking PostgreSQL logs

When working locally, Supabase starts a number of Docker containers. To check the logs:

- Run `db:logs:list` to see the list of log files and identify the most recent one
- Run `db:logs:cat <file name>` to see its contents

## Adding auth providers

### When adding/removing a new provider

Make sure to configure it on Supabase, but also for local development (with dev credentials). In particular, update:

- The `supabase-set-auth-providers.sh` file so that it generates the correct configuration for local dev
- The .env.example file to include the new required environment variables
- The CONFIGURED_AUTH_PROVIDERS environment variable to list the newly added providers (WARNING: don't forget to also
  update it on Vercel)
- The auth-form-wrapper.tsx component
- The supabase-db-seed.sql script, in particular the triggers that create/update users and user profiles
- The technical documentation wiki

Once done, make sure to recreate/restart the local Supabase instance.

GitHub provides "name" and "user_name" fields. Knowii as well (email signup), but Google only provides "name", not "user_name"! Thus each provider potentially needs to be handled differently!

### GitHub

- Go to https://github.com/settings/applications/new to create a new OAuth app
- Homepage URL: Supabase auth URL, which can be found in https://app.supabase.com/project/<project id>/auth/providers
- Authorization callback URL: same url with /auth/v1/callback

### Google

- Go to https://console.cloud.google.com/apis/credentials
- Same as above

### Facebook

- Go to https://developers.facebook.com/apps/creation/
- Same as above
- Request user email

### Twitter

- Go to https://developer.twitter.com/en/portal/projects
- Same as above
- Request user email (project > User authentication settings > Edit > "Request email from users")

WARNING: For Twitter, you MUST use the API Key and API Key Secret, and NOT the Client ID and Client Secret! (Reference: https://github.com/orgs/supabase/discussions/2270)
