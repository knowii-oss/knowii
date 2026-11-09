# 0011. The REST API follows the NBB REST API Design Guide

- Status: Accepted
- Date: 2026-11-09
- Pull request: #1191

## Context

The REST API grows with every MVP feature (spaces, posts, comments, chat, direct messages, pages, courses), and it is the base of API clients and the future MCP server (#1189). Without one reference, each endpoint invents its own paths, pagination, filters and errors. The project already took inspiration from the [REST API Design Guide](https://github.com/NationalBankBelgium/REST-API-Design-Guide/wiki) of the National Bank of Belgium (NBB), loosely.

## Decision

The REST API follows the NBB REST API Design Guide. Its MUST rules are rules for us, its SHOULD rules are the default. A deviation is listed below or in a later ADR, never made silently.

The rules we apply most:

1. Resources: plural nouns in kebab-case (`/space-groups`), nested to show ownership (`/communities/{community}/spaces/{space}`). Query parameters in camelCase.
2. Versioning in the path (`/api/v1`).
3. Methods: `GET` (read), `POST` (create, or an action on a resource: `POST .../spaces/reorder`), `PATCH` (partial update), `PUT` (full replacement, rarely), `DELETE`.
4. Status codes: 200, 201 with the created item, 204 for a delete, 400 or 422 for validation and business errors, 401, 403, 404, 405, 409 for conflicts, 412 for failed preconditions, 429 for rate limits, 500.
5. Collections: offset pagination with `limit` (default 10, maximum 1000) and `offset` (default 0), and the pagination metadata of the guide (`limit`, `offset`, `previousOffset`, `nextOffset`, `currentPage`, `pageCount`, `totalCount`) in `metadata.pagination`. Sorting with `sort` (`sort=-createdAt,name`), filtering with named query parameters.
6. Errors: one structure for every error, with a human message and the details per field for validation errors.
7. Dates and times in ISO 8601 with a time zone (UTC on the wire).
8. No hypermedia controls (HATEOAS), like the guide itself.
9. Security recommendations of the guide: no internal identifiers (we expose cuid2, [ADR 0005](0005-cuid2-public-identifiers.md)), no sensitive data in URLs, rate limits.

## Deviations

1. **Response envelope.** Every response uses the Knowii envelope from `App\Traits\ApiResponses`: `category`, `type`, `message`, and optional `data`, `metadata` and `errors`. A collection is in `data` (the guide uses `items`), its pagination in `metadata.pagination` (as in the guide). Reason: one shape for success and failure, already used by the API client.
2. **Validation errors** return 400 with the field errors in `errors` (Laravel's format: `{ "field": ["message"] }`). Business rule failures return 422.
3. **JSON field names** are snake_case (`created_at`, `space_group_cuid`), like the existing API resources and the database columns. The guide does not fix the case of JSON fields. A few older request fields are camelCase (`turnstileToken`, `includeToken`): keep them, do not add new ones.
4. **Cursor pagination is allowed for real-time feeds** (chat messages, direct messages), where new items arrive while the member scrolls and offsets shift. Such an endpoint uses `before` or `after` (a cuid) and `limit`, and returns `metadata.pagination` with `limit`, `nextCursor` and `previousCursor`. Each one is noted in its endpoint documentation.

## Consequences

1. New endpoints are reviewed against the guide and this ADR. The [naming conventions](../conventions/naming.md) and the [back-end design](../architecture/backend.md) point here.
2. The Bruno collection shows the guide in practice (pagination, sort and filter parameters, error cases).
3. Existing endpoints that differ are fixed when they are touched.

## Alternatives considered

1. JSON:API: rigid document structure, more work for the clients for little gain here.
2. GraphQL: a second API style next to REST, and harder caching and rate limiting.
3. No reference: endpoints drift.
