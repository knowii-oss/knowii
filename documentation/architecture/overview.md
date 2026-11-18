# Architecture overview

Knowii is a community platform: members of a community share posts, chat, pages, courses, events and resources. One instance hosts many communities. The code is open source (AGPL) and self-hostable.

## System context

```
                 ┌────────────────────────────────────────────────────────────┐
 Browser ──HTTPS──▶ nginx ──▶ Laravel (PHP-FPM / artisan serve)                │
    │            │              │  Inertia pages (initial data)               │
    │            │              │  REST API /api/v1 (all changes)             │
    │            │              │  OpenAPI docs /docs/api (Scramble)          │
    │            │              ├──▶ PostgreSQL 18 (data, jobs, cache)        │
    │            │              ├──▶ Redis                                    │
    │            │              └──▶ Reverb (broadcast events)                │
    └──WSS───────▶ nginx ──▶ Reverb (Pusher protocol, WebSockets)            │
                 │   queue worker (php artisan queue:work)                    │
                 └────────────────────────────────────────────────────────────┘
 External services: Resend (email), EmailOctopus (newsletter), Cloudflare Turnstile (bot check),
 Plausible-compatible analytics. Resources are captured by a queued job with a plain HTTP fetch (ADR 0018).
```

## Runtime processes

| Process      | Command                    | Role                                                                       |
| ------------ | -------------------------- | -------------------------------------------------------------------------- |
| Web          | PHP-FPM behind nginx       | Inertia pages, REST API, broadcasting authorization (`/broadcasting/auth`) |
| Reverb       | `php artisan reverb:start` | WebSocket server. Pushes events to the browsers, hosts presence channels   |
| Queue worker | `php artisan queue:work`   | Runs queued jobs and queued broadcasts                                     |
| Scheduler    | `php artisan schedule:run` | Periodic tasks (backups, digests, entitlement refresh when they exist)     |

In the Docker development environment, `supervisord` runs the web server, Reverb and the queue worker in the `laravel.test` container (`docker/8.5/supervisord.conf`).

## Repository layout

The repository is an Nx monorepo with a Laravel application at its root.

| Path               | Content                                                                              |
| ------------------ | ------------------------------------------------------------------------------------ |
| `app/`             | Laravel back-end: actions, contracts, models, policies, events, controllers          |
| `routes/`          | `web.php` (Inertia pages), `api.php` (REST API), `channels.php` (WebSocket channels) |
| `database/`        | Migrations, factories, seeders                                                       |
| `config/`          | Laravel configuration, including the Content Security Policy (`secure-headers.php`)  |
| `apps/knowii/src/` | React front-end: `Pages/`, `Components/`, `Components/ui/`, `Layouts/`               |
| `libs/common/`     | Shared TypeScript: Zod schemas, constants, the Knowii API client, hooks              |
| `tests/`           | Pest tests (`Feature/`, `Unit/`)                                                     |
| `documentation/`   | This documentation, the ADRs and the Bruno API collection                            |
| `docker/`          | Docker images of the development environment                                         |

## Technology

| Concern     | Choice                                                                   | ADR                                                   |
| ----------- | ------------------------------------------------------------------------ | ----------------------------------------------------- |
| Back-end    | PHP 8.5, Laravel 13, Jetstream (accounts), Fortify, Sanctum              | [0002](../adr/0002-laravel-inertia-react-monolith.md) |
| Front-end   | React 19, Inertia.js 3, TypeScript (strictest), Zod 4, react-hook-form   | [0002](../adr/0002-laravel-inertia-react-monolith.md) |
| UI          | Tailwind CSS 4, own components, Headless UI for dialog, listbox and menu | [0003](../adr/0003-own-ui-components.md)              |
| Database    | PostgreSQL 18 only (development, CI and production)                      | [0008](../adr/0008-postgresql-only.md)                |
| Real time   | Laravel Reverb, Laravel Echo                                             | [0009](../adr/0009-real-time-through-reverb.md)       |
| Identifiers | cuid2 in URLs and APIs, internal integer keys                            | [0005](../adr/0005-cuid2-public-identifiers.md)       |
| Tests       | Pest (back-end), Vitest and Testing Library (front-end)                  |                                                       |
| Build       | Nx 24, Vite 8                                                            |                                                       |
| API docs    | Scramble (generated OpenAPI), Bruno collection                           |                                                       |

## Environments

| Environment | Where                                         | Data                    | Deployed from         |
| ----------- | --------------------------------------------- | ----------------------- | --------------------- |
| Development | Docker (Sail) on the developer machine        | Seed data               | The working copy      |
| Acceptance  | Knowii VPS, closed to the public, not indexed | Test data only          | Every merge to `main` |
| Production  | Knowii VPS                                    | Real data, with backups | A release tag         |

Acceptance and production are tracked in issues #1172 and #1182.

## Deployment

([ADR 0046](../adr/0046-production-image-and-compose.md), `SELF_HOSTING.md` section "Docker")

1. **The image.** The `Dockerfile` builds one production image: PHP 8.5 on FrankenPHP in classic mode, the Composer packages without dev packages, the Vite build, a non-root user, no development tools. The image does not depend on the environment: the `VITE_*` values of the browser build are filled in from the container environment at start. The Sail image (`docker/8.5`) is for development only.
2. **The five services.** `compose.production.yaml` runs `app` (web), `queue` (`queue:work` on `broadcasts,notifications,default`), `reverb`, `scheduler` (`schedule:work`) and `postgres` (PostgreSQL 18). The first four use the same image with another command. Two named volumes hold the database and `storage/app`. The ports of `app` and `reverb` are published on `127.0.0.1` only: a proxy in front ends the TLS and forwards `/app/` and `/apps/` to Reverb, so the app and Reverb share one host name.
3. **Migrations.** Only the `app` service runs `php artisan migrate --force`, at start. The other services wait until `app` is healthy.
4. **Health checks.** `/up` is cheap (proxy, Docker). `php artisan app:health` is the deep check (database, migrations, cache, queue connection, Reverb), with exit code 1 on failure: the deployment runs it after the start. The `queue` and `scheduler` services check that their process runs.
5. **Workflows.** `.github/workflows/image.yml` builds the image on every pull request and on a push to `main`. It pushes `:main` and `:sha-<short>` to the GitHub container registry only when the repository variable `PUBLISH_IMAGE` is `true` (not set by default). `.github/workflows/deploy-acceptance.yml` is manual, behind the GitHub environment `acceptance`, and a skeleton until the connection to the server is decided (#1172 step J). The server pulls the image, it never builds.
