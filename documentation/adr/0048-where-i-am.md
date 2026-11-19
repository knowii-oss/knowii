# 0048. Where I am: the time zone of the account, the last visit, the visits of spaces and the "Catch up" data

- Status: Accepted
- Date: 2026-11-16
- Issue: #1476
- Pull request: #1517

## Context

[ADR 0042](0042-notifications.md) tells a member what needs them. The design of the notification experience (slice 3) also needs the platform to know a few things about where the member stands: in which time zone they live (quiet hours, the hour of the summary), when they were last here (the "Catch up" card after a long absence), which spaces hold content that they did not see (the dots of the navigation), and whether they want pop-ups. All of it is personal data about the member, so each piece has to answer three questions: who writes it, who reads it, and how long it lives.

The owner approved the design on 2026-11-15: the platform never changes the time zone alone, the last visit is one date that nobody reads, and the card never shows a count of missed things.

## Decision

### The time zone belongs to the member

1. `users.time_zone` is text, nullable. Null means "not known": the zone of the instance (`config('app.timezone')`) applies, and the settings say "Set your time zone".
2. A value is valid only when it is an IANA name that PHP knows (`DateTimeZone::listIdentifiers()`, in `App\Support\TimeZones`). `UTC` is valid. `europe/brussels` and `+02:00` are not. Since #1678 an older name of a zone is valid too, and it is stored under the current name (`TimeZones::current`, through `IntlTimeZone::getIanaID`): several browsers still give `Asia/Calcutta` for `Asia/Kolkata`, `Europe/Kiev` for `Europe/Kyiv` and `Asia/Saigon` for `Asia/Ho_Chi_Minh`, and the sign-up dropped these zones without a word. One zone has one name in the database. The same rule (`App\Rules\KnownTimeZone`) checks the zone of an event (ADR 0053).
3. Only a request of the member writes it: the sign-up form when it sends `time_zone` (an unknown or absent value gives null and never an error of the sign-up), `PUT /api/v1/user/time-zone` (ability `user:write`, an unknown value is a validation error) and the profile update of the web when it sends the field. The action is `App\Contracts\Users\UpdatesTimeZones`, bound to `UpdateTimeZone`. No visit, no API call and no browser header ever writes it. When the browser reports another zone, the client asks the member (design 6.7.5); the server never decides.
4. Only the member reads it: `UserResource` shows `time_zone` (and `notification_popups`) when the reader is the member (the shared `auth.user` prop and `GET /api/v1/user`), and no other resource, list, author or public profile holds it.

### The last visit is one date that nobody reads

5. `users.last_visit_on` is a date without an hour, nullable. The middleware `App\Http\Middleware\RecordLastVisit`, in the web group, writes today's date (UTC) for a signed-in member, for page requests only (a GET that asks for a page, or an Inertia visit; never the API, never assets), after it compared with the stored date: at most one write a day. The write is `App\Contracts\Users\RecordsLastVisits`, bound to `RecordLastVisit`, and does not touch `updated_at`.
6. The column is in `User::$hidden`, in no resource, no page prop and no API answer, also not for the managers of a community. It is in the own export of the member (`users:export`, `account.json`), because it is their data. It lives with the account and is replaced at each visit: there is no history.
7. The card: when the date that the middleware replaces is 7 days old or more, it puts the old date in the session under `catch_up_since`. The shared Inertia prop `catchUp` is then `{ since: "YYYY-MM-DD" }`, else null. A new account (no date) gets no card. The value is the member's own date, shown to the member only, for the length of the session. `POST /api/v1/notifications/caught-up` ("I am caught up") also forgets the value when the request has a session. There is no route that forgets only the value: the card of the design has one button, "I am caught up".

### The "Catch up" data

8. `GET /api/v1/communities/{community}/catch-up?since=YYYY-MM-DD` (ability `notifications:read`, members only; `App\Contracts\Notifications\BuildsCatchUp`, bound to `BuildCatchUp`). `since` is required, a valid date, not in the future and at most 366 days back.
9. At most 5 lines, in this order: up to 2 open level 1 notification rows of the member in the community, newest first (the rows of "Needs you"); up to 2 announcements since `since` (posts of other members in the discussion spaces where only the moderators post, newest first, never left out because of their age); 1 line for the thread with the most new comments since `since`, among the posts that the member follows and the posts of the spaces at "Everything" (not muted, not stopped). Each line holds `kind` (`needs_you`, `announcement`, `thread`), `title`, `excerpt`, `space`, `author`, `date` and the `target` of a notification. `has_more` is true when one source holds more than what is shown. The answer never holds a count of missed things.
10. Every line passes the reading rule of the member (`NotificationAccess::viewableSpaces`, which is `SpacePolicy::view` for each space with the roles read once), and nothing of an author whom the member blocked shows. A space at "Muted" gives no announcement. No text is stored: titles and excerpts come from the live content. The number of queries is fixed, whatever the number of spaces.

### Visits of spaces: the read marker behind the dots

11. Table `space_visits`: `user_id` and `space_id` (both cascade), `visited_at`, unique on (`user_id`, `space_id`). One row per member and space, replaced at each visit, no history. Like the follows and the levels, it has no cuid: it is private data with no resource.
12. A visit is written by `App\Contracts\Notifications\TracksSpaceVisits` (bound to `TrackSpaceVisits`) when a member who can view the space (a) opens its page or a page inside it (the four controllers `SpaceController`, `PostController`, `PageController` and `CommunityResourceController`, before the navigation is built), (b) writes content in it (the create actions of posts, comments, pages and resources) or (c) calls `PUT /api/v1/communities/{community}/spaces/{space}/visit` (ability `notifications:write`, 403 otherwise). A visitor and a signed-in person who is not a member never get a row, also in a public space.
13. `spaces.last_content_at` (nullable) is set to now by the create actions of posts, comments on posts, pages and lessons (not sections: a section is a heading), and resources. A comment on a resource gives the author a visit but is not new content of the space. Chat messages never set it: a chat space keeps its own cursor (`chat_reads`). The update does not touch `updated_at`.
14. The navigation (`SpaceTree` and `SpaceResource`) gives a member two fields per space: `has_new` and `notification_level` (`all`, `mine` or `none`, the level that applies: `SpaceNotificationLevels::levelsForSpaces`). `has_new` is true when `last_content_at` is later than the later of the visit of the member and the day they joined the community, the level is not `none`, and the space is not a chat space and not a link space (a link space has no level and no `notification_level`). A visitor and an outsider get neither field. The navigation reads the visits and the choices with one query each, whatever the number of spaces.
15. Known limits, accepted: content that was deleted, and content of an author whom the member blocked, can leave a dot until the next visit. The dot is a hint, not a count, and `last_content_at` is one date per space, so the server never evaluates the content per member.
16. The rows go with the account and with the space (cascade), and with the membership: `Community::removeUser` (leaving and removal) calls `TracksSpaceVisits::forget`. They are in the own export (`activity.json`, `space_visits`).

### The pop-up switch and the tab title

17. `users.notification_popups` is a boolean, default true. `PUT /api/v1/user/notification-settings` with `{ "popups": false }` (ability `notifications:write`) changes it. It shows in the resource of the own account only.
18. `GET /api/v1/notifications/count` also answers `messages`: the number of conversations with new messages for the member, in every community, read from the cursor of the conversations (`conversation_participants.last_read_message_id`) through the same query as the unread count of the navigation (`Conversations::unreadMessages`). One source per thing: nothing is counted twice, and the bell number never holds messages.

### Retention

19. Nothing here accumulates, so `retention:prune` has no step for it: the time zone lives with the account, the last visit date is replaced at each visit, and the visits are replaced at each visit and go with the membership. `config/retention.php` says so in a comment. The words of the privacy policy (list of collected data, rule 10 of `documentation/conventions/rules.md`, and the register of processing) change only after the owner approves them: this decision does not touch `resources/markdown/policy.md`.

## Consequences

1. The platform can show times and quiet hours in the zone of the member, show the "Catch up" card after a week away, and show the dots, with three small pieces of personal data that are each replaced, never accumulated.
2. The privacy policy is behind the code until the owner approves the words (`users.time_zone`, `users.last_visit_on`, `users.notification_popups` and `space_visits` are collected data). This must happen before the release that holds this change.
3. `has_new` can be wrong in the two cases of the decision 15. A per-member evaluation of the content would remove them and cost a query per space and member: not worth it for a dot.
4. A member who signs up through the OpenID Connect provider has no zone until they set one: no browser takes part in that path.
5. The `mixed` type stays out of the new code: the lines of the card have an array shape (`BuildsCatchUp`).

## Alternatives considered

1. Detect the zone on the server from the browser at each visit: the platform would change a personal setting without a request of the member, against design 6.7.5.
2. Store the last visit with an hour: more precise than any screen needs, and more personal. A date is enough for "7 days or more".
3. Let a manager read the last visit ("who is inactive"): refused by the design (8.5, "never shown to anyone, managers included"). Engagement signals come from what members do, not from when they look.
4. Store a count of unread items per space and member: a counter to keep right at every write, with every edge (deletes, blocks, level changes). A date compared with another date gives the dot with no upkeep.
5. A route that closes the card without clearing "Following": the card has one button, "I am caught up"; a second route would add a state with no screen.
