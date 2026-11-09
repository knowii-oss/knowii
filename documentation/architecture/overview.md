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
 External services: Resend (email), EmailOctopus (waitlist), Cloudflare Turnstile (bot check),
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
