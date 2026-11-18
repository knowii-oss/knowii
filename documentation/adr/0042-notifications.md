# 0042. Notifications: one table, one action, four levels

- Status: Accepted, amended by [0053](0053-events-a-space-type-with-its-own-table.md) (point 34: events spaces, the kind `event_new`), and by [0054](0054-email-delivery-the-mail-queue-the-budget-bounces-and-the-one-click-stop.md) (point 26: the queue `mail` with its own worker)
- Date: 2026-11-15
- Issue: #1474
- Pull request: #1488
- Extended by: #1475 (section "Slice 2: follows and space levels")

## Context

Members need to know when a person writes to them: a reply to their post, a reply to their comment, a mention. Without it they must open every thread to find out. The approved design (#1392, #1393, #191, #841) tells them in the app first, then by email, once, with the real text and the reason, and stays silent when nothing is for them.

This record covers the first slice (the server part for the app) and, in the section "Slice 2: follows and space levels", the second (#1475: thread follows, the level of a space, the "Following" kinds). The section extends the same decision, with no new rule about channels, so it is not a new record.

The first slice: It has one table, one action and three kinds of notification. The email, the pop-up, the follows, the settings, the weekly summary and direct messages come in later slices. The record fixes the rules that those slices build on, so that they add cases and not new rules.

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

## Slice 2: follows and space levels

A change inside the same decision (#1475, the server part of the second slice; the screens are #1497). It adds five kinds of level 2 ("Following") to the same table and the same action, the choices of the member (follows and levels), and the API for them. The level still gives the channel: level 2 shows in the list and is never counted. It is an extension of this record and not a new one (a record numbered 0048), because it keeps the table, the action, the levels, the access rule and the queues, and only adds cases and tables that the first slice announced ("the follows, the settings").

### The choices of the member

29. `notification_follows`, one row per member and thread. A thread is a post with its comments, or a first-level chat message with its replies, so it follows [ADR 0014](0014-explicit-foreign-keys-instead-of-polymorphic-relations.md): `post_id` and `chat_message_id`, each nullable and deleted with its thread, and a check that exactly one is set. `user_id` and `community_id` are deleted with the account and with the community. The state has three values, not two: `following`, `muted` and `stopped`. `stopped` is the memory of a "Stop following" by hand: the row stays, so that the next comment of the member does not follow the thread again without a new request. No row means that the member does not follow the thread. The reason is `wrote`, `replied` or `manual`.
30. A member who writes a post, comments a post, or replies in a chat thread follows the thread (`wrote` or `replied`), unless they have a row already (stopped, muted, or following). A mention does not make them follow. The author of a first-level chat message follows its thread from the first reply, with the reason `wrote`: a chat does not write a row for each message, only for the threads that exist. Nothing follows inside `Notify::silenced()`, for a marker, or for a person who is not a member any more. A comment on a resource has no thread.
31. The actions are "Follow", "Stop following" and "Mute this thread". Only a member of the community who can view the space has a state: a visitor or a signed-in person who is not a member is never told about anything (ADR 0044), so they follow nothing. A marker (ADR 0043) has no follows and loses its follows.
32. `notification_space_levels`, one row per member and space: `all` ("Everything"), `mine` ("Only what is for me") or `none` ("Muted"). No row means the default of the space. The most specific choice wins: the thread, then the member's level for the space, then the default of the space that a manager set, then the built-in default. `SpaceNotificationLevels` is the one place that knows it.
33. `spaces.default_notification_level` (null means the built-in default) is set by the people who manage the spaces (`CommunityPolicy::manageSpaces`: the owner, the admins, and the moderators with the right), with the field of the space update. It accepts `all` and `mine`: a manager does not mute a space for the members, a mute is the choice of each one. `all` exists for a discussion space only, and a chat space is never at "Everything" by default. The built-in default is `all` for a discussion space where only moderators post (the place where the team speaks to everyone), and `mine` for every other space. An owner or an admin who sees a `groups` space only because of the role, and not through an access group, gets `mine` there, whatever the default.
34. A member can choose "Everything" for a discussion space or a chat space only (the two kinds of space that have a stream of new items), and no level exists for a link space.

### Mute is full

35. A muted space or thread gives the member nothing: no level 2 row and no level 1 row either (no reply, no mention, no comment line). `Notify` removes the muted members before it writes the level 1 rows, and the fan-out never reads them. Muting deletes the open rows of that space (every row), or of that thread (the rows about the post and its comments, or about the chat message and its replies; the rows that bundle a space, such as `space_post`, are not about one thread and stay).

### The five kinds

36. All written by the same action, with the access and block rules of the first slice. `thread_comment` (a new comment or reply in a post thread tells its followers), `thread_reply` (a reply in a chat thread tells its followers), `space_post` (a new post in a discussion space tells the members at "Everything"), `space_chat` (a new first-level message in a chat space tells the members who chose "Everything") and `lesson_new` (a new lesson of a course tells the members who have a progress in it, if they did not mute the space). Reactions give nothing.
37. Never the author, never a member who gets a level 1 row for the same item (a reply, a mention, the author of the post: level 1 wins over level 2, so one event gives one line), never a member who muted, who cannot view the space, who is not a member of the community, or who blocked the author. A mention in a post gives the mentioned member the mention and no `space_post`.
38. Bundling: one open row per member and thread (`thread_comment`, `thread_reply`), per member and space (`space_post`, `space_chat`, `lesson_new`). A new item sets `actor_id` to its author, clears `seen_at` and moves `updated_at`; the subject stays the first item that the member did not read. `space_chat` is updated and never more than one row is open. The list gives the number of live posts, comments, replies or lessons of a bundle (`items_count`), with the authors, from the live content and with a fixed number of queries.
39. `notifications.page_id` is the item of `lesson_new` (ADR 0014: a new kind of item adds a column), and the check says that exactly one of the four items is set. A partial unique index on `(user_id, kind, page_id)` makes a retry harmless for the lessons.

### The fan-out

40. `Notify::notify()` queues the level 1 job as before, and the job `FanOutNotifications` for the level 2 kinds. A new post waits 5 minutes (design 6.11): the job reads the post again when it runs, so an edit inside the wait changes what the rows show and tells nobody, and a post that was deleted or became a marker tells nobody (`deleteWhenMissingModels` drops the job of a deleted post). `notify_now` on the creation of a post skips the wait, for the people who can moderate the space (`SpacePolicy::moderate`): the others get a 403, not a silent ignore.
41. The fan-out reads its recipients in chunks of 500 (`Constants::NOTIFICATIONS_FANOUT_CHUNK`), ordered by member id, after a cursor. Each chunk is its own job on the queue `notifications` and queues the next one when it was full. A chunk runs a fixed number of queries whatever the number of members: one query for the candidates, one for the blocks, three to load the members with their communities and groups, one for their levels, one query for the roles of the members of a `groups` space (`CommunityRoles::withKnownAdmins`, which only holds for the length of the check), then one query to find the rows that exist, one to find the open rows, one update to bundle, one insert for the new rows. A retry is harmless: a member who has the row of the item is skipped, the unique index of the open rows keeps one open row per member and thing, and `insertOrIgnore` never fails on it. A job that lost a race joins the open row of the other.
42. Events: one `NotificationsChanged` per member and community, queued on `broadcasts` (as a queued event, never in the request).

### API

43. Under `/api/v1`, with the abilities `notifications:read` and `notifications:write` (not in the default set): `GET`, `PUT` and `DELETE` `/communities/{community}/spaces/{space}/posts/{post}/follow` (the state, follow, stop following), `PUT .../posts/{post}/mute`, the same four for a first-level chat message under `.../messages/{message}`; `GET`, `PUT` and `DELETE` `/communities/{community}/spaces/{space}/notification-level` (read, choose, go back to the default); `GET /communities/{community}/notification-levels` (all the levels of the member for one community, in one call, for a settings screen); `POST /notifications/caught-up` ("I am caught up": the open rows of level 2 are read, in every community or in one; it never touches level 1). `GET /notifications` takes `level` (1, the default, or 2). Level 2 rows older than 14 days are never listed and have no "Older"; the count of the bell never counts level 2. Each notification has `reason` (`wrote_post`, `wrote_comment`, `mentioned`, `follows_thread`, `space_everything`, `started_course`), `follow_reason` and `items_count`. The post resource and the chat message resource carry `follow_state` (`following`, `muted`, `stopped`, or null), set for a whole page with one query. The space resource carries `default_notification_level` for the people who manage the spaces only.

### Rows go away

44. A member who leaves or is removed loses their follows and levels in that community. A deleted thread or space takes its follows and levels with it (cascades). A marker loses its follows. The job `PruneUnviewableNotifications` also deletes the follows and levels of a member who cannot view the space any more.

### Data kept

45. The follows, the mutes and the levels are new personal data ("the choices of the member"): kept while the member is in the community (no step in `retention:prune`; the notification rows keep their two periods), in the member export (`activity.json`: `notification_follows` and `notification_levels`, with no text and no name of another member), and in the privacy policy (a sentence for the owner to approve, see the issue). The community export holds none: they belong to the member.

### The undo of a mute (#1509)

46. A mute remembers what it replaced: `notification_follows.state_before_mute` and `reason_before_mute` (both null when the thread had no row before). `DELETE` on `.../posts/{post}/mute` and `.../messages/{message}/mute` (`notifications:write`, the access rule of the mute) takes the mute back: a thread that had no row has no row again, a followed thread is followed again with its earlier reason (`wrote`, `replied` or `manual`), a stopped thread is stopped again. A second mute keeps what the first one remembered. Any other change of the state (follow, stop following) forgets it. On a thread that is not muted nothing changes, and the answer is the current state. The notifications that the mute deleted do not come back. The panel calls this route for "Undo" and no longer reads the state before the mute.

## Consequences

1. The email, the pop-up, the follows and the settings of the next slices add cases to the enum and read the same rows. Nothing in them needs a new rule about who is told.
2. Every place that deletes the way into an item (a new block, a lost access) has to delete or recheck the rows. The read-time check is the safety net: a row that slips through is never shown or counted, and it goes at the first read.
3. `User` uses the `Notifiable` trait of Laravel, whose `notifications()` relation points at a table with the same name and another shape. Nothing calls it. Do not call it: use `App\Models\Notification` queries.
4. The queue `notifications` needs a worker. Without one, no notification is written and the prune jobs do not run. The bell shows what exists.
5. Every kind of data in the table is counted in the privacy policy, the retention and the member export (rules 8 and 10 of the data rules).
6. Slice 2 added choices that outlive the rows: a follow or a level stays while the member is in the community. Every place that makes a member lose the way into a space deletes them too (rule 44).
7. A fan-out of 2 000 members at "Everything" runs 4 chunks, one job each, with the same number of queries per chunk as a fan-out of 10. The queue `notifications` needs a worker for it, as for the first slice.

## Alternatives considered

1. Laravel database notifications (`Notifiable`, the `notifications` table with a JSON `data` column): the payload holds text and has no foreign keys, so deletions by cascade leave orphans and the text outlives its content.
2. A polymorphic subject (`subject_type`, `subject_id`): no foreign key, against ADR 0014.
3. A count of unread rows kept on the user: a second source of truth that drifts. The partial index makes the count cheap.
4. Writing the rows in the request: the fan-out of a later kind (an announcement to every member) would slow the request of the author. The same action serves both, queued.
5. One channel per kind, chosen by the member (a matrix of kinds by channels): 16 kinds times 3 channels are 48 questions, and members keep the defaults. The level gives the channel and the member answers one question per place.
6. A new record (0048) for slice 2: the decision is the same, and the cases that it adds (kinds, choices, API) are what the first slice announced. Two records would force a reader to open both to know who is told.
7. Two states for a follow (`following` and `muted`) and the deletion of the row on "Stop following": the next comment of the member would follow the thread again, against what they asked. The third state, `stopped`, costs one row and keeps the choice.
8. A row of `notification_follows` for each chat message that a member writes: a chat of 2 000 members would grow the table with every message. The row of a chat thread starts with its first reply.
