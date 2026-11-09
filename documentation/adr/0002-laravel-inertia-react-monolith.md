# 0002. Laravel, Inertia and React in one Nx monorepo

- Status: Accepted
- Date: 2026-11-09
- Pull request: #1168

## Context

Knowii needs a server with accounts, permissions, queues and WebSockets, and a rich, interactive UI. The project started on Laravel with Jetstream. On 2026-11-09 the stack was upgraded (PHP 8.5, Laravel 13, React 19, Inertia 3, Nx 24) and the question "keep Laravel?" was answered yes.

## Decision

1. One application: Laravel serves the pages through Inertia.js, React renders them. No separate SPA, no separate API server.
2. The REST API (`/api/v1`) is part of the same application and is used by the UI for every change (see [ADR 0006](0006-actions-behind-contracts.md)).
3. Nx manages the TypeScript projects (`apps/knowii`, `libs/common`): builds, tests, lint, caching. Nx stays (issue #1116 to remove it stays open).
4. PHP 8.5 is the minimum version. TypeScript runs with the strictest configuration.

## Consequences

1. One deployment unit, one language per side, shared types between the API client and the pages through `libs/common`.
2. The session cookie authenticates the UI on the API (Sanctum stateful domains).
3. Inertia props and API resources must stay aligned.

## Alternatives considered

1. Separate SPA and API: more deployment and auth work for one team.
2. A full-stack JavaScript framework: would throw away the Laravel back-end (accounts, queues, Reverb).
