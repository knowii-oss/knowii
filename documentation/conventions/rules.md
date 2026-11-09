# Rules

These rules keep the code structurally sound as it grows. Each rule has a reason. To break a rule, write an ADR that changes it first.

## Architecture

1. **Everything belongs to a community.** Every table that holds member content has a `community_id` (directly, or through a parent that has one and is always loaded with it). Every query, URL, channel and permission check is scoped to one community. There is no "Knowii only" shortcut. Reason: the platform hosts many communities ([ADR 0004](../adr/0004-multi-community-scoping.md)).
2. **Changes go through actions.** Controllers, commands and jobs call an action through its contract. No other code writes to the database. Reason: one place for permissions, validation and business rules ([ADR 0006](../adr/0006-actions-behind-contracts.md)).
3. **Permissions live in policies and are checked in the actions.** Controllers do not decide permissions. The UI only hides controls. WebSocket channels check the same rules as the data they carry.
4. **The API is complete.** The UI changes data only through the REST API. Anything a member does in the UI, an API client can do.
5. **Public identifiers are cuid2.** APIs, channels and events expose `cuid`, never `id`. Page URLs use slugs. Models hide `id` and foreign keys in serialization.
6. **One serialization per model.** The API, the Inertia props and the broadcast events use the same API resource class (`app/Http/Resources`).
7. **Member content is Markdown, rendered on the server with HTML escaped** (`App\Support\Markdown`, [ADR 0013](../adr/0013-markdown-rendered-on-the-server.md)). No raw HTML from members reaches a page. `<MarkdownContent>` is the only place that sets inner HTML.
8. **Real time goes through Reverb.** No polling. Payloads stay small: send the changed item, not lists.
9. **No inline styles and no scheme-wide CSP sources.** Every new external origin is a configuration value ([ADR 0007](../adr/0007-strict-content-security-policy.md)).
10. **PostgreSQL only.** Use its features (JSONB, full-text search, partial indexes) where they help. Do not write code for SQLite or MySQL ([ADR 0008](../adr/0008-postgresql-only.md)).
11. **No new runtime dependency without a reason.** Prefer the framework and the existing libraries. A new dependency that shapes the code (UI kit, state library, editor, ORM extension) needs an ADR.

## Data

1. Every table has `id` (big integer, internal), `cuid` (unique) and timestamps.
2. Foreign keys are constrained. Pick `cascadeOnDelete` or `nullOnDelete` on purpose, and write the choice in the migration when it is not obvious. No polymorphic relations for member data: one nullable foreign key per kind of item, with a check constraint ([ADR 0014](../adr/0014-explicit-foreign-keys-instead-of-polymorphic-relations.md)).
3. Ordered items have a `position` column and a unique or indexed `(parent_id, position)`. Reordering is one action that rewrites the positions of the parent in a transaction.
4. Slugs are unique within their parent (a space slug is unique in its community), not globally.
5. Migrations never change data in a way that cannot be undone without a backup. Destructive migrations (drop a column with data) need an issue and a note in the pull request.
6. Limits (lengths, counts) are constants in `App\Constants`, mirrored in `libs/common/src/lib/constants.ts`.
7. An API resource returns every field of the client schema, also the ones with a database default: set them in the action or refresh the model after `create()` (a new model does not hold database defaults).

## Front-end

1. Strict TypeScript, no `any`, `import type` for types.
2. Use the components in `apps/knowii/src/Components/ui`. A new building block goes there with a test.
3. Keyboard support and a visible focus for every interactive element. Check every screen in the dark and the light themes.
4. Use the brand tokens only. No raw hex values, no Tailwind palette colors.
5. Never put text written by a member in an `href` without `safeHttpUrl()` (libs/common): only absolute http and https URLs become links.
6. Inertia props are not parsed by Zod: their dates are ISO strings even when the type says `Date`. Components that format dates accept both (see `RelativeTime`).
7. Call the API through `apiRequest()`: it sends uppercase HTTP methods (`fetch` does not uppercase `patch`, and servers reject it).

## Definition of done

A pull request is done when all of these are true:

1. The feature works in the browser (Docker development environment), in both themes, with the keyboard, without a CSP violation in the console.
2. Pest tests cover the actions, the policies and the API endpoints (success and main failures). Vitest tests cover the new components.
3. `npm run tsc`, `npx nx run-many -t lint typecheck test`, `composer lint` and `php artisan test` pass.
4. The Bruno collection (`documentation/api`) has a request for each new or changed endpoint.
5. The generated OpenAPI document (`/docs/api`) describes the new endpoints correctly (Scramble reads the controllers, the validation rules and the resources).
6. The documentation in `documentation/` is current, and a structural decision has an ADR.
7. `SELF_HOSTING.md` and `.env.example` list each new configuration value.
8. The commits follow Conventional Commits with an allowed scope.
