# 0014. Explicit foreign keys instead of polymorphic relations

- Status: Accepted
- Date: 2026-11-09
- Issue: #1176

## Context

Reactions (#1176), mentions (#1177) and uploads (#1178) attach to several kinds of items: posts, comments, later chat messages, direct messages and pages. Laravel offers polymorphic relations (`reactable_type`, `reactable_id`), but they have no foreign key: the database cannot delete a reaction with its item. Items are often deleted by a database cascade (deleting a post deletes its comments without Eloquent events), so polymorphic rows would stay as orphans.

## Decision

1. A table that attaches to several kinds of items has **one nullable foreign key per kind** (`post_id`, `comment_id`, later `chat_message_id`...), each with `cascadeOnDelete`.
2. A check constraint makes sure that exactly one is set: `CHECK (num_nonnulls(post_id, comment_id) = 1)`. Adding a kind adds a column and updates the constraint in the same migration.
3. Unique indexes include the foreign key (`unique(post_id, user_id, kind)`): rows where it is null do not collide.
4. Models expose the relation through a trait (`HasReactions`) and name their column (`reactionForeignKey()`).

## Consequences

1. The database keeps the data consistent, also for deletions by cascade. No cleanup job.
2. Each new kind of item needs a migration. That is acceptable: kinds of items are few and change rarely.
3. No morph map is needed.

## Alternatives considered

1. Polymorphic relations with a cleanup on the `deleted` model event: misses the deletions done by database cascades.
2. Polymorphic relations with a scheduled orphan cleanup: data is wrong between two runs.
3. One table per kind (`post_reactions`, `comment_reactions`): duplicates the code and the API.
