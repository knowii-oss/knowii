# Knowii

Documentation: https://www.notion.so/Knowii-05e0911d5fcb413388fc07b59eae7354

## Dev environment setup

- Install node 16.17.1+
- Install npm 8.15.0+
- Install Docker
- Install psql (to be able to execute SQL scripts against the database, including the db seed script)
- Login to Supabase: `npx supabase login`
- Run `npm install`
- Prepare and run the local Supabase instance: `npm run db:reset`
- Create apps/knowii/.env.local based on .env.example
  - For DATABASE_URL and DIRECT_URL: http://localhost:12345
  - For SHADOW_DATABASE_URL: https://app.supabase.com/project/cipnhztmttjipgawzbqt
- Install the Stripe CLI: https://stripe.com/docs/stripe-cli
- If needed, run `stripe listen --forward-to localhost:3000/api/stripe-webhook` to redirect Stripe events to your local instance (useful to fill in the local database with Stripe plans)

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

The script invokes Prisma but forces a load of the production env file instead of the default one, based on the approach described here: https://github.com/prisma/prisma/discussions/2392

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

When you try to create a commit, hooks are executed to perform verifications and ensure code quality/formatting. Those are configured by Husky. Check out the `.husky/pre-commit` file, as well as the lint-staged configuration: `.lintstaged.js`

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

To enable a new NX library to benefit from i18n strong typing, copy the index.d.ts at the root of client-ui over to the new library.

## Color theme

- Edit apps/knowii/theme.js

Use tools such as https://www.tailwindshades.com/ to generate the different shades

## Checking PostgreSQL logs

When working locally, Supabase starts a number of Docker containers. To check the logs:

- Run `db:logs:list` to see the list of log files and identify the most recent one
- Run `db:logs:cat <file name>` to see its contents

## Adding auth providers

### When adding a new provider

Make sure to configure it on Supabase, but also for local development (with dev credentials). In particular, update:

- The `supabase-set-auth-providers.sh` file so that it generates the correct configuration
- The .env.example file to include the new required environment variables
- The technical documentation wiki

Once done, make sure to restart the local Supabase instance

### GitHub

- Go to https://github.com/settings/applications/new to create a new OAuth app
- Homepage URL: Supabase auth URL, which can be found in https://app.supabase.com/project/<project id>/auth/providers
- Authorization callback URL: same url with /auth/v1/callback

### Google

- Go to https://console.cloud.google.com/apis/credentials
