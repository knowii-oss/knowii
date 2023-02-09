# Knowii

Documentation: https://www.notion.so/Knowii-05e0911d5fcb413388fc07b59eae7354

## Dev environment setup

- Install node 16.17.1
- Install npm 8.15.0
- Install Docker
- Run `npm install`
-

## App

- Run: `npm nx serve knowii`

## Understand this workspace

Run `nx graph` to see a diagram of the dependencies of the projects.

Visit the [Nx Documentation](https://nx.dev) to learn more.

## Environments

For development, database credentials are stored in:

- "apps/knowii/.env.local" (there's an example in .env.example)

To deploy DB migrations to production:

- Create a file called `.env.prod` under "apps/knowii"
- Run `npm run db:migration:deploy:prod`

The script invokes Prisma but forces a load of the production env file instead of the default one, based on the approach described here: https://github.com/prisma/prisma/discussions/2392
