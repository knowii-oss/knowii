# 0004. Every piece of data is scoped to a community

- Status: Accepted
- Date: 2026-11-09
- Issue: #1173

## Context

Knowii first replaces Circle for the Knowii Community, but the product hosts many communities on one instance, and self-hosters run their own. A "Knowii only" shortcut today would be expensive to undo.

## Decision

1. The community is the boundary for data, URLs, permissions and real-time channels.
2. Every table with member content has a `community_id`, or a parent that always carries it. Space groups, spaces, posts, comments, chat messages, pages, conversations, uploads, reactions and mentions all resolve to one community.
3. Page URLs are nested under the community (`/community/{communitySlug}/...`). API paths are nested under the community cuid.
4. Membership and roles are per community (`community_members.role`: admin, moderator, member; the owner has every right).
5. A member can mention, message or react only within the community of the content.

## Consequences

1. Queries filter by community first. Policies check membership in the community of the model.
2. Slugs are unique within their community, not globally.
3. Cross-community features (global search, a user's feed across communities) must aggregate per community and respect each community's permissions.

## Alternatives considered

1. One instance per community: simpler queries, but heavy to run and no shared accounts.
2. A global `tenant_id` with a database scope: hides the scoping instead of making it explicit.
