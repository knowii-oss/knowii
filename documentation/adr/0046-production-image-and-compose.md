# 0046. One production image, one Compose project with five services

- Status: Accepted
- Date: 2026-11-15
- Issue: #1172
- Pull request: #1490

## Context

Knowii runs in development in the Sail image (`docker/8.5`): Ubuntu, Node.js, Xdebug, Swoole, Supervisor, the development server of Artisan. It must not run in production. The acceptance environment (planned for `staging.knowii.net`) and the later production need one image that contains only what the application needs, starts the same way everywhere, and can be pulled by a server that never builds anything.

The application needs four long-running processes (the web server, the queue worker, Reverb and the scheduler) and PostgreSQL. `SELF_HOSTING.md` describes them for a host with PHP-FPM, nginx and Supervisor. The old `deploy.yml` workflow did not deploy anything itself: it rebased the `production` branch on `main`, and a service that watched that branch did the rest.

## Decision

### The image

1. One `Dockerfile` at the root with four stages: PHP with the extensions, Composer without dev packages, the Vite build, and the runtime. The runtime has no Composer, no Node.js, no test code and no Git folder (`.dockerignore`).
2. FrankenPHP in classic mode (no worker mode). One binary serves HTTP and runs PHP, so the image needs neither nginx nor PHP-FPM nor a process manager. Classic mode keeps the request model of PHP-FPM: no state survives a request, so the code needs no change. Worker mode would be faster, but it demands code that is safe to keep in memory (static state, singletons) and is not a goal of this decision.
3. The PHP extensions are the ones the code uses: `pdo_pgsql`, `pcntl`, `intl`, `bcmath`, `zip`, `exif`, `gd` (with WebP, for the re-encoding of uploads) and `opcache`. Redis is not installed: sessions, cache and queue use the database.
4. The image runs as a non-root user, listens on port 8000, sets `expose_php=Off` (#887) and writes logs to stderr.
5. The image does not depend on the environment. The `VITE_*` values that Vite writes into the JavaScript at build time are placeholders, and the entrypoint replaces them with the values of the container at start (`REVERB_HOST`, `REVERB_APP_KEY`, `REVERB_PORT`, `REVERB_SCHEME`, `APP_NAME`). The same image therefore runs in acceptance and in production. The Laravel caches (`config`, `route`, `view`, `event`) are built at start, because they need the environment.

### The stack

6. `compose.production.yaml` has five services: `app`, `queue` (`queue:work` on `broadcasts,notifications,default`, ADR 0042), `reverb`, `scheduler` (`schedule:work`) and `postgres` (PostgreSQL 18). The first four use the same image with another command. The project name is mandatory (`COMPOSE_PROJECT_NAME`), so that two environments never share a project, its volumes or its network.
7. The app and Reverb share one host name. The proxy in front sends `/app/` and `/apps/` to the `reverb` service and the rest to `app`. Both ports are published on `127.0.0.1` only.
8. Two named volumes: the database, and `storage/app` (uploads, private files, exports), shared by the four application services.
9. Only the `app` service runs `php artisan migrate --force`, at start (`MIGRATE_ON_START=true`). Its host port is fixed, so Compose cannot start a second replica that races with it. The other three services wait until `app` is healthy, which happens after the migrations. A deployment has one migration step and no extra service.

### Health checks

10. `/up` stays the cheap check of the proxy and of Compose: it answers from the framework without touching the database.
11. `php artisan app:health` is the deep check that a deployment waits on: the database answers, every migration ran, the cache works, the queue connection works, Reverb answers. It exits with 1 when a check fails. It runs on the command line, so it shows no detail to the internet. The `reverb` service uses `app:health --only=reverb` as its health check; the `queue` and `scheduler` services check that their process runs.
12. `TRUSTED_PROXIES` (`config/app.php`) tells Laravel which proxies to trust for `X-Forwarded-*`. The default is none.

### The workflows

13. `image.yml` builds the image on every pull request (no push, with a cache, and a small smoke test) and, on a push to `main`, builds it again and, only when the repository variable `PUBLISH_IMAGE` is `true` (not set by default), pushes `ghcr.io/<owner>/<repo>:main` and `:sha-<short>` with the permissions `contents: read` and `packages: write`.
14. `deploy-acceptance.yml` is manual (`workflow_dispatch`), behind the GitHub environment `acceptance`. It is a skeleton: resolve the tag, connect, pull, start, run `app:health`, roll back to the previous tag on failure. The connection step fails on purpose until the owner chooses how GitHub Actions reaches the server (#1172 step J).
15. `deploy.yml` is removed. The `production` branch mechanism it drove is not used by this decision.

## Consequences

1. A migration runs on a database that the previous version still uses during a roll back. A migration must therefore stay compatible with the previous version (add before you remove, in two releases), as `documentation/conventions/rules.md` already asks for destructive changes.
2. The JavaScript files change at container start. Their names carry the hash of the build, not of the values of the environment: if `REVERB_HOST` changes while the tag stays the same, a browser that kept the old file for a year (`immutable`) keeps the old value. Change the value and the tag together, or wait for a new build.
3. The `VITE_*` values in a `.env` do not work in production: the entrypoint reads the server values.
4. Scaling the web service needs another way to run the migrations (a one-shot service or `migrate --isolated`).
5. FrankenPHP is an extra dependency on the image side. The Sail image and `docker-compose.yml` do not change.

## Alternatives considered

1. PHP-FPM and nginx in one image with Supervisor: two servers and a process manager to maintain, and logs to merge, for no gain over one binary.
2. FrankenPHP in worker mode: faster, but the code is not reviewed for state that survives a request.
3. A one-shot `migrate` service: clean, but a sixth service. The fixed port of `app` already prevents the race.
4. Build the image on the server: slow, and it needs Node.js and Composer there. The server pulls a tested image instead.
5. Redis for cache, sessions and queue: not used by the application (ADR 0008 and `SELF_HOSTING.md`).
