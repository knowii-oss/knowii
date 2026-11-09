# 0012. Space groups and typed spaces structure a community

- Status: Accepted, amended by [0015](0015-resources-space-type.md) (the `resources` type replaces the resource collections)
- Date: 2026-11-09
- Issue: #1173

## Context

The Knowii Community on Circle is organized in space groups and spaces (40 spaces). The MVP needs several kinds of places in a community: discussions (posts), chat (real-time messages with threads), pages (Start Here, guides) and courses (ordered lessons with videos). Each kind holds different content, but they share the navigation, the visibility and the posting rules.

## Decision

1. A community has ordered **space groups**. A space group has ordered **spaces**. The order is a `position` column (0-based, gaps allowed). Reordering is one action per list (`ReorderSpaceGroups`, `ReorderSpaces`) that receives the complete list of cuids.
2. A space has a **type**: `discussion`, `chat`, `pages` or `course`. The type decides which content tables point to the space (posts, chat messages, pages). **The type never changes** after creation: the content depends on it. Course spaces reuse the pages model (a lesson is a page with a video, #1188). Resource collections stay a separate concept for now.
3. `spaces.community_id` is stored next to `space_group_id` (denormalized) so that queries, slugs and permissions are scoped to the community without a join ([ADR 0004](0004-multi-community-scoping.md)). Actions keep both consistent: a space only moves to a group of the same community.
4. Slugs are unique per community (`unique(community_id, slug)`) and do not change on rename, so links keep working.
5. **Visibility** per space: `public` (anyone can read; visitors in M2) or `members`. **Posting policy** per space: `members` or `moderators` (for Announcements). Access groups (M2) will add a third visibility option.
6. Permissions: `CommunityPolicy::manageSpaces` (owner and admins) for the structure; `SpacePolicy::view`, `contribute` and `moderate` for the content of a space. Every content feature checks `contribute` before a write.
7. A space group with spaces cannot be deleted (its spaces must be moved or deleted first), so content never disappears with a group. Deleting a space deletes its content (cascade).
8. The navigation sent to a page (`SpaceTree::for($community, $user)`) only contains the spaces the user can see. Members do not get empty groups; managers do.
9. Any change to the tree broadcasts `community.space_tree.changed` on the community channel, with only the community cuid. Open pages reload their navigation, which keeps per-user visibility on the server.

## Consequences

1. Each content feature (posts #1174, pages #1179, chat #1185, courses #1188) adds a table with a `space_id` (cascade on delete) and checks the space type in its actions.
2. Limits: 50 space groups and 200 spaces per community (`Constants`).
3. The API exposes the tree at `GET /communities/{community}/space-groups` and the changes as separate endpoints ([ADR 0011](0011-rest-api-design-guide.md)).

## Alternatives considered

1. One table per space type: duplicates navigation, ordering and permissions four times.
2. A free-form tree (nested groups): Circle has two levels and members expect two levels. More levels make the navigation harder to use.
3. A mutable type: content conversion between types (posts to chat messages) has no clear meaning.
