# 0042. Notifications: one table, one action, four levels

- Status: Accepted
- Date: 2026-11-15
- Issue: #1474
- Pull request: #1488

## Context

Members need to know when a person writes to them: a reply to their post, a reply to their comment, a mention. Without it they must open every thread to find out. The approved design (#1392, #1393, #191, #841) tells them in the app first, then by email, once, with the real text and the reason, and stays silent when nothing is for them.

This record covers the first slice: the server part for the app. It has one table, one action and three kinds of notification. The email, the pop-up, the follows, the settings, the weekly summary and direct messages come in later slices. The record fixes the rules that those slices build on, so that they add cases and not new rules.

## Decision

### The four levels, and the level gives the channel

1. Every kind of notification has one level, written on the enum `KnowiiNotificationKind::level()`. Level 0 is "Account" (mandatory messages), level 1 is "Needs you" (a person wrote to the member), level 2 is "Following" (a thing the member chose to follow), level 3 is "Around you" (everything else).
2. The channel comes from the level, never from the kind. Level 0 and level 1 show in the bell and in the list, and (in later slices) go by email. Level 2 shows in the list and is never counted. Level 3 has no row: the navigation shows a dot from the content itself.
3. The three kinds of this slice are all level 1: `post_comment` (a first-level comment on a post tells its author), `comment_reply` (a reply to a comment tells its author) and `mention` (a mention in a post, a comment or a chat message tells the mentioned member). A later kind adds a case and a level. It does not add a rule about channels.

### One source of truth per thing

4. The bell number, the list, the markers in the navigation and the tab title use the same server values (design 5.2). New chat messages stay in `chat_reads`, direct messages in the cursor of the conversation, everything else in the notification rows. The server value replaces the value of the client on every event, on focus and on page load. The client never adds 1.
5. A mention in a chat is a row and is also behind the chat cursor. Reading the chat marks the mention rows of the space up to the message as seen and read. Marking a chat mention row as read moves the cursor of the member forward to its message (the root message for a reply in a thread), never backward. Marking a row as unread leaves the cursor where it is.

### The table

6. `notifications`, one row per recipient: `user_id` (deleted with the account), `community_id`, `space_id` (nullable, the scope of the access check), `kind`, `actor_id` (set to null when the account is deleted, the list shows "Deleted member"), the item, `dedupe_key`, `seen_at`, `read_at` and the timestamps.
7. The item follows [ADR 0014](0014-explicit-foreign-keys-instead-of-polymorphic-relations.md): `post_id`, `comment_id` and `chat_message_id`, each nullable and deleted with its item, and a check that exactly one is set (`num_nonnulls(...) = 1`). A later kind of item adds a column.
8. `dedupe_key` is a text key for bundling and idempotency, not a reference. A partial unique index on `(user_id, dedupe_key)` where `read_at` is null allows one open row per recipient and thing, and never blocks a new row after the member read the old one. A partial index on `(user_id)` where `seen_at` is null serves the count.
9. A row holds no text of content, ever. Titles and excerpts are built from the live content when the list is read. So a deleted or edited item shows its current state, and the table holds nothing that the privacy policy would have to explain beyond who, where, which item and when. Events carry identifiers only.
10. Seen and read are two states. Seen: the line was on screen, or the content itself was (the post page, the chat). Read: the line was opened or marked done, and a read row is also seen. The bell counts rows that are not seen. A read row is never opened again: the next comment makes a new row. Setting seen or read does not touch `updated_at`, which orders the list and ages a row.

### The action: who is told

11. One action behind a contract: `App\Contracts\Notifications\Notifies`, bound to `App\Actions\Notifications\Notify`. Callers never write rows. `CreatePost`, `CreateComment` and `CreateChatMessage` call `notify()` once the item exists. The `Update*` actions never call it: an edit tells nobody, and a mention that an edit adds tells nobody.
12. `notify()` queues the job `WriteNotifications` on the queue `notifications`, after the database commit of the caller. The job calls `deliver()`, which holds the rules.
13. One row per recipient and per item. When one comment gives the same member several reasons, the order is mention, then reply, then comment.
14. Nobody is told about their own action. Nobody is told when they cannot view the item at the moment of writing: the rule is the community membership plus `SpacePolicy::view`, the one place that knows it (ADR 0025, 0041). A member who blocked the author gets no row (ADR 0028).
15. Bundling (design 6.6): while the recipient has an unread row for the same thing (the same post for `post_comment`, the same parent comment for `comment_reply`), a new comment adds no row. It sets `actor_id` to the new author, clears `seen_at` (the line is "New" again) and moves `updated_at`. The subject stays the first comment that the member did not read. Mentions do not bundle.
16. Storm cap (design 6.10.2): when a member already has 3 unread mention rows from the same author that are less than one hour old, no further mention row is written. The wording "mentioned you 4 times" comes later, with the client.
17. `Notify::silenced(fn)` runs a callback without any notification: imports, seeders and bulk actions use it. A second guard skips an item that is older than 15 minutes at the moment of the event.
18. Direct messages give no notification in this slice.

### Built at read time, checked at read time

19. The list and the count check the access first, with the members, the communities and the spaces loaded once for all the rows. A row whose recipient cannot view its item is not returned, not counted, and is deleted. The list builds titles and excerpts (300 characters at most, plain text) and the authors of a bundle (up to 3 names and `others_count`, from the live comments, blocked authors left out) with a fixed number of queries, never one per row.
20. The count is the "Needs you" rows that the member did not see and that were updated 14 days ago at most. The list shows the rows of the last 14 days, and the rows that are older with `older=true` ("Older").

### Rows go away when they must

21. Database cascades cover a deleted post, comment, space, community and account. A chat message is only soft-deleted, so `DeleteChatMessage` deletes its rows. Posts and comments are deleted for real. After such a cascade the bell value is corrected on the next event, focus or page load, as for every other count.
22. `Community::removeUser` deletes the rows of that member in that community (leave and removal). A new block deletes the rows that the blocked member caused for the blocker (as last author, or as author of the item).
23. When who can view a space changes, the job `PruneUnviewableNotifications` runs on the queue `notifications` and deletes the rows of the "groups" spaces of the community that their recipient cannot view any more. `AccessGroupsChanged` starts it, and so does `CommunityMembersChanged` (a demoted admin no longer views every space). `UpdateSpace` starts it for the one space when its visibility, its space group or its groups change.

### API

24. Routes under `/api/v1/notifications`, for the signed-in member only, across all communities. List (20 per page, filter by community cuid, `older`), count, mark a list as seen, mark one row as read, open it again. A row of another member gives 404. No endpoint deletes a row. The token abilities are `notifications:read` and `notifications:write`, not in the default set of a new token: a token for a bot does not read who writes to a member.
25. The event `NotificationsChanged` goes to the private channel `user.{cuid}`, on the queue `broadcasts`, with the cuid of the notification (null when several rows changed) and the cuid of the community. It is sent when a row is written, bundled, seen, read or deleted, so the other tabs follow.

### Queues

26. The worker reads `broadcasts,notifications,default`, in this order. Live updates come first, then who is told, then the rest. No existing job or event moves. A later slice adds the queue `mail` with its own worker. `docker/8.5/supervisord.conf`, `SELF_HOSTING.md` and `CONTRIBUTING.md` carry the new command.

### Data kept

27. Retention ([ADR 0030](0030-retention-periods-enforced-by-scheduled-tasks.md)): `retention.notifications_read_days` (60 days after the member read the row, by `read_at`) and `retention.notifications_unread_days` (90 days when never read, by `created_at`), a step in `retention:prune`, and a line in the privacy policy with placeholders. The age of a row for the retention is not the age for the list.
28. The member export holds the notifications of the member: the kind, the community, the cuids of the items and the dates. It holds no text and no name of another member. The community export holds no notification rows: a notification belongs to its recipient, not to the community, and it holds no content.

## Consequences

1. The email, the pop-up, the follows and the settings of the next slices add cases to the enum and read the same rows. Nothing in them needs a new rule about who is told.
2. Every place that deletes the way into an item (a new block, a lost access) has to delete or recheck the rows. The read-time check is the safety net: a row that slips through is never shown or counted, and it goes at the first read.
3. `User` uses the `Notifiable` trait of Laravel, whose `notifications()` relation points at a table with the same name and another shape. Nothing calls it. Do not call it: use `App\Models\Notification` queries.
4. The queue `notifications` needs a worker. Without one, no notification is written and the prune jobs do not run. The bell shows what exists.
5. Every kind of data in the table is counted in the privacy policy, the retention and the member export (rules 8 and 10 of the data rules).

## Alternatives considered

1. Laravel database notifications (`Notifiable`, the `notifications` table with a JSON `data` column): the payload holds text and has no foreign keys, so deletions by cascade leave orphans and the text outlives its content.
2. A polymorphic subject (`subject_type`, `subject_id`): no foreign key, against ADR 0014.
3. A count of unread rows kept on the user: a second source of truth that drifts. The partial index makes the count cheap.
4. Writing the rows in the request: the fan-out of a later kind (an announcement to every member) would slow the request of the author. The same action serves both, queued.
5. One channel per kind, chosen by the member (a matrix of kinds by channels): 16 kinds times 3 channels are 48 questions, and members keep the defaults. The level gives the channel and the member answers one question per place.
