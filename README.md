# Knowii

Documentation: https://www.notion.so/Knowii-05e0911d5fcb413388fc07b59eae7354

## Dev environment setup

- Install node 16.17.1+
- Install npm 8.15.0+
- Install pnpm: `npm install --global pnpm`
- Install Docker
- Install psql (to be able to execute SQL scripts against the database, including the db seed script)
- Login to Supabase: `pnpx supabase login`
- Run `pnpm install`
- Prepare and run the local Supabase instance: `pnpm run db:reset`
- Create apps/knowii/.env.local based on .env.example
  - For DATABASE_URL and DIRECT_URL: http://localhost:12345
  - For SHADOW_DATABASE_URL: https://app.supabase.com/project/cipnhztmttjipgawzbqt

## App

- Start the database: `pnpm run db:start`
- Run: `pnpm run start`
- Stop the database: `pnpm run db:stop`

## Environments

For development, database credentials are stored in:

- "apps/knowii/.env.local" (there's an example in .env.example)

To deploy DB migrations to production:

- Create a file called `.env.prod` under "apps/knowii"
- Run `pnpm run db:migration:deploy:prod`

The script invokes Prisma but forces a load of the production env file instead of the default one, based on the approach described here: https://github.com/prisma/prisma/discussions/2392

## DB schema and migrations

There are a number of scripts to handle/ease DB migrations:

- To prepare a migration, run `pnpm run db:migration:prepare`
- To deploy a migration locally, run `pnpm run db:migration:deploy`
- To deploy a migration to production, run `pnpm run db:migration:deploy:prod`
- To check the migration status locally, run `pnpm run db:migration:status`
- To check the migration status in production, run `pnpm run db:migration:status:prod`
- To validate the DB schema locally, run `pnpm run db:schema:validate`
- To validate the DB schema in prod, run `pnpm run db:schema:validate:prod`

Note that the `:prod` commands should be used primarily from the CI/CD environment, not from your development machine

## Git pre-commit hooks

When you try to create a commit, hooks are executed to perform verifications and ensure code quality/formatting. Those are configured by Husky. Check out the `.husky/pre-commit` file, as well as the lint-staged configuration: `.lintstaged.js`

## Environment variables

When new environment variables are necessary, they should:

- Be added to ./app/knowii/.env.example
- Be added to ./apps/knowii/env.mjs (for type safety)
- Be configured on Vercel

## Developing components

Create reusable components in the 'client-ui' library. Test those using Storybook: `pnpm run storybook:client-ui`

## Adding a locale

To add a locale:

- Create the files under apps/knowii/public/locales/<lang>
- Edit i18next.d.ts
