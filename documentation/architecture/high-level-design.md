# High-level design

## Domain model

Everything a member writes or reads belongs to one community. The community is the boundary for data, URLs, permissions and real-time channels ([ADR 0004](../adr/0004-multi-community-scoping.md)).

```
User ──< CommunityMember >── Community ──< SpaceGroup ──< Space
 │        (role: admin,          │                         │ type: discussion | chat | pages | course
 │         moderator, member)    │                         │ visibility, posting policy, position
 │                               │                         ├──< Post ──< Comment (one level of replies)
 │                               │                         ├──< ChatMessage (thread replies: parent)
 │                               │                         └──< Page (ordered; lessons in course spaces)
 │                               ├──< Conversation ──< ConversationParticipant (direct messages)
 │                               │         └──< DirectMessage
 │                               ├──< CommunityInvitation
 │                               └──< CommunityResourceCollection ──< CommunityResource ──> Resource
 ├── UserProfile
 └── Reactions, Mentions, Uploads (one nullable foreign key per kind of item: post, comment, message, page; ADR 0014)
```

State on 2026-11-09: users, profiles, communities, members, invitations, resource collections, space groups and spaces ([ADR 0012](../adr/0012-space-groups-and-typed-spaces.md)) posts, comments (one level of replies) and reactions exist. The rest of the content of the spaces (chat messages, pages, conversations) is built for the MVP (milestones M1 to M4, see the GitHub milestones). This diagram shows the target of the MVP. Each feature updates this paragraph when it ships.

### Concepts

| Concept             | Meaning                                                                                            |
| ------------------- | -------------------------------------------------------------------------------------------------- |
| Community           | A group of members with its own spaces. Visibility: `public`, `private` or `personal` (one member) |
| Community member    | The link between a user and a community, with a role. The owner of a community has all the rights  |
| Space group         | An ordered, named group of spaces in the navigation of a community                                 |
| Space               | A place in a community where members do one kind of thing. Its type decides what it holds          |
| Discussion space    | Holds posts. Posts have comments, reactions and mentions                                           |
| Chat space          | Holds chat messages, in real time. A reply to a message starts a thread                            |
| Pages space         | Holds ordered Markdown pages (Start Here, guides, resource libraries)                              |
| Course space        | Holds ordered lessons. A lesson is a page with an optional video                                   |
| Conversation        | Direct messages between 2 to 10 members of the same community                                      |
| Resource collection | A curated list of resources (web articles). Older than spaces; stays as its own concept            |

## Request flow

1. A page load is an Inertia request. The Inertia controller (`app/Http/Controllers/Inertia`) loads the initial data, checks the `view` permission and renders a React page with props.
2. Every change goes through the REST API (`/api/v1`): the page calls the Knowii API client (`libs/common`), the API controller hands the input to an action, the action checks the permission, validates the input and writes. The UI never changes data through Inertia form posts. This keeps the API complete: anything the UI does, an API client can do ([ADR 0006](../adr/0006-actions-behind-contracts.md)).
3. The model events (created, updated, deleted) broadcast to the WebSocket channels. The pages that show the data listen and update without a reload.

```
Page (React) ──fetch──▶ API controller ──▶ Action (authorize, validate, write) ──▶ Model ──▶ PostgreSQL
                                                                                     │
Other browsers ◀──WebSocket── Reverb ◀── queue worker ◀── broadcast event ◀──────────┘
```

## Authorization

1. Policies (`app/Policies`) hold the rules. The actions call `Gate::forUser($user)->authorize(...)`. Controllers never decide permissions themselves.
2. Community roles: owner (implicit), admin, moderator, member. Access groups (M2) add finer access to spaces.
3. The Inertia controllers pass a `permissions` object to the pages. The UI uses it only to show or hide controls. The server checks every action again.
4. WebSocket channels authorize in `routes/channels.php` with the same rules as the data they carry.

## Real time

Real time uses Reverb ([ADR 0009](../adr/0009-real-time-through-reverb.md)):

1. Private channels carry data events. A channel exists per community and per space or conversation, and its authorization equals the read permission of that data.
2. Presence channels show who is online and carry typing indicators (client events, never stored).
3. Event payloads use the same API resources as the REST API, so the client handles one shape.
4. When the payload would depend on the permissions of each member (the space tree), the event only names what changed (`community.space_tree.changed`) and the pages reload the data from the server.

## Security

1. Sessions: the front-end calls the API with the session cookie (Sanctum, stateful domains). API clients use tokens.
2. Content Security Policy: no `'unsafe-inline'` for scripts or styles, per-request nonces, no scheme-wide sources ([ADR 0007](../adr/0007-strict-content-security-policy.md)).
3. Member content (Markdown) is rendered on the server with raw HTML escaped, then shown as HTML. Members never inject HTML ([ADR 0013](../adr/0013-markdown-rendered-on-the-server.md)).
4. Rate limits on public and write endpoints. Cloudflare Turnstile on public forms.
5. Identifiers in URLs and APIs are cuid2 strings, never the integer keys ([ADR 0005](../adr/0005-cuid2-public-identifiers.md)).

## Accounts

The platform keeps its own accounts (Jetstream). The Knowii account (business, purchases) is one optional OpenID Connect sign-in provider, enabled by instance configuration. Entitlements from the Knowii account give access to some spaces of the Knowii Community only ([ADR 0010](../adr/0010-own-accounts-optional-oidc.md)).
