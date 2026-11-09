# Naming conventions

Names are in English. One concept has one name everywhere: in the database, the PHP code, the TypeScript code, the API, the UI and this documentation. When the code and the UI need different words, write the mapping here.

## Domain words

| Use            | Do not use                  | Meaning                                                                |
| -------------- | --------------------------- | ---------------------------------------------------------------------- |
| community      | group, workspace, tenant    | The top-level boundary                                                 |
| member         | participant (except in DMs) | A user in a community (`CommunityMember`)                              |
| space group    | category, section           | A group of spaces in the navigation                                    |
| space          | channel, room, forum        | A place of one type in a community                                     |
| post           | thread, topic, article      | An entry in a discussion space                                         |
| comment        | reply (for the first level) | A response to a post. A response to a comment is a reply (`parent_id`) |
| chat message   | message (alone)             | A message in a chat space. A reply in a thread has a `parent_id`       |
| conversation   | chat, DM thread             | A set of 2 to 10 members who exchange direct messages                  |
| direct message | private message             | A message in a conversation                                            |
| page           | document, wiki page         | An ordered Markdown page in a pages space                              |
| lesson         | chapter, module             | A page of a course space                                               |
| reaction       | like, vote                  | A fixed emoji reaction by a member                                     |
| mention        | tag, ping                   | `@username` in a text                                                  |
| upload         | attachment, file            | A file a member sent (images first)                                    |

## PHP

| Element             | Convention                                         | Example                                                |
| ------------------- | -------------------------------------------------- | ------------------------------------------------------ |
| Model               | Singular, PascalCase                               | `Space`, `SpaceGroup`, `ChatMessage`                   |
| Table               | Plural, snake_case                                 | `spaces`, `space_groups`, `chat_messages`              |
| Pivot or link table | Both names, singular first, snake_case             | `community_members`, `conversation_participants`       |
| Foreign key         | `<singular>_id`                                    | `space_group_id`, `author_id` (role name allowed)      |
| Order column        | `position` (integer, 0-based, gaps allowed)        | `spaces.position`                                      |
| Public identifier   | `cuid` (string, unique)                            | every table                                            |
| URL slug            | `slug`, unique in its parent                       | `spaces.slug` unique per community                     |
| Enum                | `Knowii<Concept>` in `app/Enums`, string-backed    | `KnowiiSpaceType`                                      |
| Action              | Verb + noun, in `app/Actions/<Domain>/`            | `CreateSpace`, `ReorderSpaces`                         |
| Contract            | Third-person verb + plural noun, `app/Contracts/`  | `CreatesSpaces`, `ReordersSpaces`                      |
| API controller      | `<Model>ApiController` in `Http/Controllers/API`   | `SpaceApiController`                                   |
| Inertia controller  | `<Model>Controller` in `Http/Controllers/Inertia`  | `SpaceController`                                      |
| API resource        | `<Model>Resource` in `app/Http/Resources`          | `SpaceResource`                                        |
| Policy              | `<Model>Policy`; abilities in camelCase            | `SpacePolicy::update`, `CommunityPolicy::manageSpaces` |
| Event               | `<Model><PastTenseVerb>` in `app/Events/<Models>/` | `SpaceCreated`                                         |
| Constant            | `UPPER_SNAKE_CASE` in `App\Constants`              | `MAX_LENGTH_SPACE_NAME`                                |
| API route constant  | `UPPER_SNAKE_CASE` in `App\ApiRoutes`              | `COMMUNITY_SPACES`                                     |
| Test file           | `<Subject>Test.php`                                | `CreateSpaceTest.php`, `SpaceApiControllerTest.php`    |

## API

1. Paths are plural nouns in kebab-case, nested under the community: `/api/v1/communities/{community}/space-groups/{spaceGroup}`.
2. Path parameters are cuid values. Slugs are for the page URLs only.
3. Methods: `GET` reads, `POST` creates or runs a command, `PATCH` updates, `DELETE` deletes. A command that is not a CRUD operation is a sub-resource: `POST .../spaces/reorder`.
4. JSON fields are camelCase in requests and responses (`spaceGroupCuid`, `createdAt`).

## Web routes

1. Page URLs use slugs, nested under the community: `/community/{communitySlug}/space/{spaceSlug}`.
2. Route names are `<plural>.<action>` in kebab-case: `spaces.show`, `space-groups.index`. The front-end refers to them through constants in `constants.ts` (`SPACE_URL`).

## WebSockets

1. Channel names are dot-separated, lowercase, with snake_case words: `community.{communityCuid}`, `community.{communityCuid}.space.{spaceCuid}`. Each channel and each of its parameters is a constant in `App\Constants` and in `use-socket.ts`.
2. Event names (`broadcastAs`) are `<scope>.<model>.<past_tense_verb>` in lowercase snake_case: `community.space.created`, `space.post.deleted`.
3. Client events (typing) start with `client-`, as the Pusher protocol requires: `client-typing`.

## TypeScript and React

| Element                | Convention                                 | Example                                             |
| ---------------------- | ------------------------------------------ | --------------------------------------------------- |
| React component file   | PascalCase, one component per file         | `SpaceNavigation.tsx`                               |
| Component test         | Same name, `.spec.tsx`                     | `SpaceNavigation.spec.tsx`                          |
| Page                   | `Pages/<Area>/<Name>.tsx`                  | `Pages/Spaces/Show.tsx`                             |
| Schema file            | kebab-case, `.schema.ts`                   | `space.schema.ts`, `create-space-request.schema.ts` |
| Hook                   | `use<Thing>`, file `use-<thing>.ts`        | `useSocket`, `use-socket.ts`                        |
| Type from a Zod schema | PascalCase, schema in camelCase + `Schema` | `Space`, `spaceSchema`                              |
| Constant               | `UPPER_SNAKE_CASE`                         | `MAX_LENGTH_SPACE_NAME`                             |

## Git

1. Branches: `<type>/<issue number>-<short-name>`, for example `feat/1173-spaces`, `fix/1183-csp-explicit-hosts`.
2. Commits: Conventional Commits with an allowed scope (see `commitlint.config.js`): `feat(ui): ...`, `fix(sec): ...`. The message says what changed and why.
3. One pull request per feature, merged with a rebase merge, so the history keeps the conventional commits for the changelog.

## ADRs

`documentation/adr/NNNN-<kebab-case-title>.md`, numbered in order, never renumbered. See the [ADR guide](../adr/README.md).
