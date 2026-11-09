# 0006. Changes go through actions behind contracts, the API is complete

- Status: Accepted
- Date: 2026-11-09

## Context

Permissions, validation and business rules must be applied the same way whether a change comes from the UI, an API client, a console command or a job. Logic in controllers gets duplicated and drifts.

## Decision

1. Each change is an action class (`app/Actions/<Domain>/<Verb><Noun>`) implementing a contract (`app/Contracts/<Domain>/<Verbs><Nouns>`), bound in `AppServiceProvider`.
2. The action authorizes (policy through `Gate`), validates (limits from `App\Constants`), checks the business rules, and writes.
3. API controllers only collect the input, call the action and return an `ApiResponses` response. Exceptions are turned into JSON by the global handler in `bootstrap/app.php`.
4. The UI makes every change through the REST API. Inertia only delivers the initial data of a page.

## Consequences

1. Every feature adds a contract, an action, a binding, an API endpoint, a client method and tests.
2. The API is always as complete as the UI, which is the base for API clients and the future MCP server (#1189).

## Alternatives considered

1. Inertia form posts to web controllers: quicker, but leaves the API behind.
2. Form request classes for validation: validation would sit outside the action, so commands and jobs would skip it.
