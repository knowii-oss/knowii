# Events design for the Knowii Platform (2026-11-16)

Status: approved by the owner on 2026-11-16; his answers to the decisions of this design are in the comments of #1388 and in the build issues. Tracking issue: #1388, with the build slices #1513 to #1516. Slice E1 is built: the server part (#1513) and the screens (#1514), see [ADR 0053](../../adr/0053-events-a-space-type-with-its-own-table.md) and the [release test](release-test.md). The server part of slice E2 is built (#1515, part E2a: the answers of members, the limit of places, the Yes part of `going`, the API, retention, export and policy words; see "The answers and the places" in ADR 0053). The screens of the answers, the calendar file and "My events" (parts E2b and E2c) and the later slices are not built.

This design covers #1388 (events with answers, calendar file, reminders, replay). It touches #1444 (members host, drafts), #1443 (series, calendar view), #1413 (places, waitlist, public page, paid), #1412 (live rooms: out of the MVP), #1423 and #1476 (time zone), #1482 (notification slice 8), #1396 and #1431 (the move). Nothing here is approved. The owner approves section 13 point by point, then the build issues follow section 14.

The model is the approved notification design ([`../notifications/design.md`](../notifications/design.md), "the notification design" below, with its section numbers). This file does not change it. Where events need something that it does not say, the text says so and asks.

Facts about `main` that shape the design (read in the repository, not run):

1. Notification slices 1 and 2 are built (ADR 0042). `app/Enums/KnowiiNotificationKind.php` has eight kinds and no event kind. Email (#1393, slice 4) and the weekly summary (#191, slice 5) are not built: `app/Mail` holds only `CommunityInvitation.php`, `CommunityOwnershipTransferred.php`, `MagicLink.php`.
2. `users.time_zone` does not exist (no migration, nothing in `app/Models/User.php`). It comes with notification slice 3 (#1476).
3. No event code exists. A search for "rsvp" in `app`, `routes`, `database`, `resources`, `apps`, `libs` finds nothing.
4. A space has a fixed type (`KnowiiSpaceType.php`: discussion, chat, pages, course, resources, link), a visibility (`public`, `members`, `groups`) and a posting policy (`members`, `moderators`). The reading rule is one method: `SpacePolicy::view` (`app/Policies/SpacePolicy.php`).
5. `routes/console.php` holds nightly commands only. A reminder one hour before a start needs a command that runs every minute.

## 1. The promise and the principles

### 1.1 The promise (what a member can read)

"Knowii shows you what is planned in your community, at your own time, with the time zone in words. You answer Yes, Replay only or No, and that is all we ask. If you say Yes, we remind you, and we tell you at once if the event moves or is cancelled. If you do not answer, we send nothing about it. We do not record who came. The meeting happens where the host says: you get the link, and after the event the host can add the replay."

### 1.2 Principles (each has a yes or no test)

1. One moment, true everywhere. Every screen, email and file shows the same moment in the member's zone, zone in words. Test: change the account zone and read the page, the list and the calendar file: the same moment.
2. The answer belongs to the member. Only the member, the host and the owner and admins see it. Test: as a member who did not answer, look for any name or count of answers. None exists (a limit of places shows "N places left", 7.7).
3. No attendance. The platform stores what a member said they will do, never what they did. Test: no column, table or log about joining the meeting.
4. Silence without an answer. No answer means no reminder and no follow-up. Test: answer nothing, wait past both reminder times: the mailbox is empty.
5. A change tells those who care. A move or cancel reaches Yes and Replay only, nobody else. Test: move an event and count the lines.
6. Same rules as the space. Visibility, access groups, posting policy and what a visitor sees come from the space. Test: change the space visibility and the event follows.
7. The platform runs no meeting. It links to one. Test: no video, audio or attendance code for events.
8. API parity. Test: `OpenApiDocumentTest` and `BrunoCollectionTest` pass.
9. For everyone. Keyboard, screen reader, 360 px, both themes, no signal by colour alone. Test: section 11.6.

## 2. Scope

### 2.1 First version

1. A space type `events` and the event in it (section 3). States draft, published, cancelled; "past" is derived (3.4).
2. Start and end with a time zone, shown in the member's zone (section 4).
3. A meeting address or place, with a choice of who sees it (3.7). Markdown description, cover, one host, optional limit of places.
4. The answers Yes, Replay only, No (3.6). "Add to calendar": one `.ics` file per event (6.5).
5. The list of a space (Upcoming, Past), the event page, the host form, "Next events" on the community home, "My events" (section 6).
6. Comments, shared with posts (7.6). After the event: a replay link and notes (2.4).
7. Kinds 25 to 29 (section 8). API with abilities, Bruno, Pest, Vitest, an ADR. "Duplicate" for weekly events (3.5).

### 2.2 Later

1. Series, an events page across spaces, a month grid (#1443). Waitlist, public landing page, paid events (#1413).
2. A private calendar feed (6.6). Several hosts, moving an event between spaces, pin, a "now" block.
3. A CSV of answers with a log line. Phone alert for the 1-hour reminder (notification slice 10). Topics (#1404).

### 2.3 Never

1. A live room, stream or recording inside the platform (#1412). An event holds an outside meeting address.
2. A record of who joined, for how long, or who watched the replay.
3. A reminder for a member who did not answer. An automatic Yes for every member (an option of the tool that the community uses today, off today). A count of people used as pressure.
4. A second notification system. Events use `Notify` and the four levels.

### 2.4 Recordings

1. The platform never receives a recording. After the event the host pastes a link in "Replay link" (3.3).
2. A YouTube or Vimeo link shows the click-to-load player of lessons (`app/Support/VideoEmbed.php`, ADR 0029, 0031). Any other http or https address is a link.
3. The replay link has the readers of the meeting address: members who can see the event, never a visitor (7.5).
4. The first time the link is set, kind 29 runs (8.5).
5. The form says: "Before you add a recording, make sure the people in it agreed." The words need approval (decision 13).
6. A replay link lives only as long as the place behind it. This is a risk for the move (section 12).

## 3. The model

### 3.1 Three options, compared with the code

Option A: a new space type `events`, with a table `events`.

1. A space already carries visibility, access groups, posting policy, navigation, API and live updates (`app/Models/Space.php`, ADR 0012). Resources (ADR 0015) and links (ADR 0026) joined the same way, and ADR 0015 gives the reason: one navigation, one permission model, one settings screen.
2. `SpacePolicy::contribute` returns true for any type except `link`, by posting policy. So "who creates" needs no new rule: policy `moderators` is today's community, policy `members` is #1444.
3. The notification design already treats events as a kind of space (2.3: "Events (after #1388)", default "Everything").
4. Cost: every switch on the space type gets a case: `Space.php`, `SpacePolicy.php`, `SpaceTree.php`, `PublicCommunity.php`, `SpaceController.php`, `CommunityController.php`, `apps/knowii/src/Pages/Spaces/Show.tsx` (`EMPTY_STATES` is typed on `Space['type']`), and `space.schema.ts` (kept in sync, see the comment in `KnowiiSpaceType.php`). `ChooseSpaceLevels.php` (line 81) and `UpdateSpace.php` (line 46) refuse "Everything" outside discussion and chat (ADR 0042, point 34): they change (3.8).

Option B: an event is a post with start and end columns in a discussion space.

1. It reuses comments, reactions, follows and slugs. But a post has no draft state, start, capacity or answers (`app/Models/Post.php`), and its list is ordered by creation, not start.
2. Upcoming and Past need a flag "events space": a type under another name. Every post would carry empty event columns, and `space_post` would say "new post" where kind 25 needs a time.
   Option C: events with no space.

3. It rebuilds visibility, access groups, navigation, permissions and settings: the duplicate that ADR 0015 removed. The tool of today also uses event spaces (3 of them, #1388 comment of 2026-11-15).
4. Rejected.

### 3.2 Recommendation: option A

1. A case `events` in `KnowiiSpaceType`, a table `events`, the same names in `libs/common` (`space.schema.ts`, `SPACE_TYPE_LABELS`).
2. A new ADR amends ADR 0012, ADR 0042 (point 34) and ADR 0044 (the public reads). Shape: ADR 0015. Next number: 0048 (to check).
3. The type never changes (ADR 0012, point 2). Default posting policy in the form: `moderators`.

### 3.3 The fields

Table `events` (rules.md "Data": `id`, `cuid`, timestamps, constrained keys).

| Field                                                                              | Rule                                                                                                 |
| ---------------------------------------------------------------------------------- | ---------------------------------------------------------------------------------------------------- |
| `community_id`, `space_id`                                                         | cascade; the type `events` is checked in the actions, as `CreateCommunityResource` does              |
| `host_id`                                                                          | `users`, nullable, `nullOnDelete` (like `posts.author_id`): "Deleted member"                         |
| `title`, `slug`                                                                    | 3 to 200 characters; slug unique per space, never changes on rename (like posts)                     |
| `body`, `body_html`                                                                | Markdown and server-rendered HTML (ADR 0013), up to 20 000 characters (new constant)                 |
| `starts_at`, `ends_at`                                                             | `timestamptz`, UTC; end after start, at most 24 h later                                              |
| `time_zone`                                                                        | IANA name, the zone in which the host entered the time (4.1)                                         |
| `meeting_url`                                                                      | http or https, up to 2048, nullable                                                                  |
| `place`                                                                            | text up to 255, nullable                                                                             |
| `location_visibility`                                                              | `members` or `going` (3.7)                                                                           |
| `cover_upload_id`, `cover_alt`                                                     | upload, `nullOnDelete`; alt up to 125, empty means decorative                                        |
| `capacity`                                                                         | 1 to 5 000, nullable (7.7)                                                                           |
| `state`                                                                            | `draft`, `published`, `cancelled`                                                                    |
| `replay_expected`                                                                  | boolean: shows the answer "Replay only" (3.6)                                                        |
| `replay_url`, `notes`, `notes_html`                                                | the recording link and the Markdown notes after the event                                            |
| `sequence`                                                                         | integer from 0; goes up on a change of time, place, address or state; used by the `.ics` file (6.5)  |
| `published_notified_at`, `replay_notified_at`, `reminded_24h_at`, `reminded_1h_at` | guards so that each notification runs once; the two reminder guards reset when the start moves (8.3) |

1. The address and the place may be empty, also when published: the page says "Meeting address to come". A later change follows 8.2.
2. Indexes: `(space_id, starts_at)` for the lists; a partial index on `(starts_at)` where `state = 'published'` for reminders and the home block.
3. A model docblock, `EventResource` with `#[UseResource]` and `toResource()` (rules.md rule 6). Large fields only with `->withLargeFields()`. The host uses `AuthorResource`, the same cut for visitors as for post authors (ADR 0044, point 6).

### 3.4 The state: draft, published, cancelled, and "past"

1. `draft`: seen by the host, the owner and the admins only. No notification.
2. `published`: seen by those who can see the space (7.1).
3. `cancelled`: seen, with a banner "Cancelled", no new answers. It stays in "Upcoming" until its start passes, then in "Past" with the label, so a member who has an email or a bookmark finds the news.
4. "Past" is not stored. An event is past when `ends_at` is before now. A stored state needs a job to flip it and flip it back when a host moves the event. The API gives `is_past`, computed on the server.
5. Allowed: draft to published; published to draft only if nobody answered (else "Cancel it instead"); published to cancelled; cancelled to published. A started event cannot go back to draft.
6. Actions behind contracts in `app/Contracts/Events` (ADR 0006): `CreateEvent`, `UpdateEvent`, `SetEventState`, `DeleteEvent`, `DuplicateEvent`, `AnswerEvent`.

### 3.5 Recurring events: out of the first version

1. Each date of a series needs its own answers, reminders and edit rules ("this, this and following, all"), and its local hour over summer time (#1443). That is a second model on the first.
2. The model prepares it at no cost: later a table `event_series` and `events.series_id`. Each date stays a row of `events`, so answers, reminders and notifications do not change.
3. For now: "Duplicate" makes a draft with the same fields and the date 7 days later in the host's zone (same wall-clock hour, not same number of hours: journey 5.I), with no answers.

### 3.6 The answers of members

1. Three answers, in the words of the notification design (4.F): "Yes", "Replay only", "No". The brief for this design said "Maybe"; the notification design does not contain it (decision 2).
2. "Replay only" means "I cannot come, send me the replay." It shows only when the host ticked `replay_expected`. Otherwise the member sees Yes and No.
3. Table `event_answers`: `event_id`, `user_id`, `community_id` (all cascade), `answer` (`yes`, `replay`, `no`), timestamps, unique `(event_id, user_id)`. A new item column, never a polymorphic link (ADR 0014).
4. No row means no answer. A "No" row exists because the summary rule needs "an event that I can join and did not answer" (notification design 5.8, point 1).
5. A member changes or clears their answer until the event ends. Only the member changes their own answer. A cancelled or past event takes no new answer; the old one stays visible to its member.
6. Only a community member who can see the event answers. A visitor and an outsider see "Sign in to answer" (the pattern of ADR 0044, point 15) or nothing.

### 3.7 Who sees the meeting address and the place

1. `location_visibility`: `members` (default) is every member who can see the event; `going` is members who said Yes, plus the host, the owner and the admins. `going` fits a limit of places or a paid space.
2. A visitor or outsider never sees the address, the place or the replay link (7.5).
3. The address never appears in a line, a subject or an alert. The line says "Join" and links to the page. The 1-hour email has "Join the meeting".
4. The server does not fetch the address. It checks the form (`url:http,https`, like link spaces in `SpaceInputRules.php`). The client shows it only through `safeHttpUrl()` (rules.md, Front-end rule 5), in a new tab with `rel="noopener noreferrer"` (ADR 0026, point 4).

### 3.8 The level of the space for members

1. The notification design wants "Everything" as the default for event spaces (2.3). The code gives `all` only to discussion and chat (3.1) and as built-in default only to a discussion space where moderators post (`SpaceNotificationLevels.php`, line 31).
2. The ADR of events adds `events` to the types that may be at "Everything", and makes `all` the built-in default of an events space (kind 25).
3. A manager may set "Only what is for me" as default. A member may mute the space. Mute is full (notification design 2.2).

### 3.9 The cover image

1. Uploaded with the existing endpoint, re-encoded and sized by `ImageProcessor` (ADR 0040), never loaded from another origin (ADR 0007). Ratio 16:9, `object-cover`, a fixed box so the list does not jump.
2. `upload_usages` needs an `event_id` for the cover and the description images, or `uploads:prune` deletes them after 7 days (`config/retention.php`). Its check constraint changes (ADR 0014). To check: how `app/Support/UploadReferences.php` counts usages.

## 4. Time zones

### 4.1 Stored

1. `starts_at` and `ends_at` are UTC instants: the truth.
2. `events.time_zone` is the IANA zone of the host's entry ("Europe/Brussels"). It gives "the host's time" (4.3) and later keeps the local hour of a series over summer time (#1443, point 4). It does not define the moment.
3. If a government changes a zone's rules later, the instant stays: members agreed on it. Zone data comes with the PHP and ICU of the image (to check how it is updated).

### 4.2 Entered by a host

1. The form has "Start date", "Start time", "End time" ("End date" only when the end is on another day) and "Time zone". The zone starts as the host's (`users.time_zone` after slice 3, else the browser zone) and can change: an event in New York is entered in New York time.
2. A live sentence shows the result: "Tuesday 18 November 2026, 17:00 to 18:00, Central European Time (UTC+1). In your zone: ...". It is the check against a wrong zone.
3. The API takes `starts_at` and `ends_at` as ISO 8601 with an offset ("2026-11-18T17:00:00+01:00") and `time_zone`. The server converts to UTC and checks that the offset is the zone's real offset at that moment (else 422).
4. A time that does not exist (28 March 2027, 02:30 in Brussels, clocks go forward): 422 "This time does not exist in this zone. Choose 01:59 or earlier, or 03:00 or later." The form prevents it.
5. A time that exists twice (31 October 2027, 02:30 in Brussels): the form asks "02:30 in summer time (UTC+2) or in winter time (UTC+1)?". The offset in the call decides.
6. A start in the past is refused for members and accepted for the owner and the admins (to enter past events for the move). Such an event notifies nobody.

### 4.3 Shown to a member

1. Every time is in the member's zone with the zone in words: "Tuesday 18 November, 11:00 to 12:00 Eastern Standard Time". In a list: "Tue 18 Nov, 11:00 EST", with the long form as visually hidden text and in the `time` element. The weekday and date are always in words, never "18/11/26".
2. The date follows the zone: Tuesday 17:00 in Brussels is Wednesday 00:00 in Singapore.
3. When the member's zone differs from the event's, a second line says "The host's time: Tuesday 18 November, 17:00 Central European Time".
4. The zone comes from `users.time_zone` after slice 3; before it, from the browser (`Intl.DateTimeFormat().resolvedOptions().timeZone`) on the client only. To check: whether Inertia server rendering is on (`config/inertia.php` has an `ssr` block); if so, the first paint must not show a wrong zone.
5. Emails and the `.ics` description need the zone on the server, so they wait for `users.time_zone` (decision 3).
6. Formatting: `Intl.DateTimeFormat` with `timeZoneName: 'long'` on the client, `IntlDateFormatter` on the server. No new dependency (rules.md, Architecture rule 11).

### 4.5 Summer time

1. The instant is stored, so no clock change moves an event by accident. "Duplicate" keeps the host's local hour (3.5), so a member in another zone sees the hour move, which is true (journey 5.I).
2. The form shows the member's version when it differs: "For you: Tuesday 30 March, 11:00 Eastern Daylight Time (12:00 last week)".

### 4.6 The link with `users.time_zone` (slice 3)

1. #1476 builds the column (IANA, validated, fallback), the browser fill, and the question when the browser reports another zone (notification design 6.7, points 4 and 5). Events read it and add nothing.
2. Events need it for email times, the `.ics` description, the "Coming up" block and reminder texts. In-app screens work without it (4.3, point 4).
3. Recommendation: build it first (decision 3). No screen shows a member's zone to others (#1476).

## 5. Journeys

The same moment: Tuesday 18 November 2026, 16:00 UTC = 17:00 in Brussels, 11:00 in New York, Wednesday 00:00 in Singapore. Names are examples.

### 5.A A host creates and publishes (Sébastien, Brussels)

1. In the space "Office hours" (type events, policy `moderators`) he chooses "New event".
2. He enters the title, a Markdown description, a cover and the meeting address. Start 17:00, end 18:00, 18 November, zone Central European Time. The form says "In your zone: the same." He ticks "A replay will exist" and sets 40 places.
3. "Save as draft": nobody sees it. He previews it. He clicks "Publish".
4. After 5 minutes (the wait of notification design 6.11; "Publish now" skips it) members of the space get one line under "Following": "Tue 18 Nov, 17:00 Central European Time: Weekly review office hour".

### 5.B A member finds it, answers, adds it to a calendar (Nora, New York)

1. The community home shows "Next events". Nora opens the event: "Tuesday 18 November, 11:00 to 12:00 Eastern Standard Time" and "The host's time: 17:00 Central European Time".
2. She chooses "Yes" in the group "Your answer". The page says "Saved: Yes" and, with 12 of 40 places used, "28 places left".
3. "Add to calendar" downloads `weekly-review-office-hour-2026-11-18.ics`. Her calendar shows 11:00 her time.

### 5.C The event is moved (Sébastien)

1. On Monday he changes the start to 18:00. `sequence` goes up and both reminder guards reset (8.3).
2. Anna (Singapore, Yes) and Nora (Yes) get a level 1 line at once: "Moved: Weekly review office hour. New time: Wednesday 01:00 Singapore Standard Time." A member who said Replay only gets it. A member who did not answer gets nothing.
3. With email (slice 4): the new time in each member's zone, a new `.ics` file (same `UID`, higher `SEQUENCE`), and "I cannot come, send me the replay". It passes quiet hours only when the start is within 12 hours (notification design 6.7, point 2).
4. Never: a line for the whole space; two lines for two quick fixes (the row bundles).

### 5.D The event is cancelled (Sébastien)

1. "Cancel event" asks: "This tells 14 members who said Yes or Replay only." The count holds only members who can see the event, never names.
2. The page shows a "Cancelled" banner in text; the answer group goes; old answers stay. The 14 get "Cancelled: Weekly review office hour." The `.ics` file says `STATUS:CANCELLED`. No reminder goes.
3. If he publishes it again, the answers are still there, and the 14 get "Back on: ..." (kind 26).

### 5.E The hour before (Anna, Singapore; Marc, Paris)

1. T minus 24 h, for a Yes: a line "Tomorrow 00:00 Singapore Standard Time: ..." and, with email, one mail with the `.ics` and "I cannot come, send me the replay".
2. T minus 1 h, for a Yes: a line and one email "In 1 hour: ... Join". Anna gets it at 23:00 Singapore time, inside quiet hours: it passes, because she said Yes.
3. Not sent when the member saw the line (notification design 6.2, point 5), when the answer changed, when the event moved, or when the member answered after the reminder time.

### 5.F After the event (Sébastien, Nora, Anna)

1. The event ends and moves to "Past" with no job. The answer group goes.
2. Sébastien pastes a YouTube link in "Replay link" and writes notes. Kind 29 runs: Anna (Replay only) gets a level 1 line, Nora (Yes) a level 2 line, same text: "The replay of Weekly review office hour is ready." The platform has no attendance data, so it never says "You could not join".
3. "Who came" is not stored. Sébastien sees only the list of answers, which are intentions (6.4, section 10).

### 5.G A member who joins later (Tom, Lisbon)

1. Tom joins on Monday. He gets no line for events published before: lines are written at the moment of the event (ADR 0042).
2. The home shows "Next events" and the "Catch up" card ends with the next event (notification design 5.5). He can answer an upcoming event and read "Past" with replay links, without answers or counts.

### 5.H A visitor on a public space (Vik, no account)

1. Vik sees the title, cover, description, time in his browser zone with the zone in words, and "Hosted by <name>". He sees no address, place, replay link, answers or counts: "The meeting address is for members. Sign in." The answer group says "Sign in to answer".
2. No websocket opens (ADR 0044, point 13). A draft, or an event in a members space, sends him to the sign-in page and back, with the same answer as a page that does not exist (ADR 0044, point 4).

### 5.I Weekly office hours across a summer time change (Sébastien, Nora)

1. Tuesday 23 March 2027: 17:00 Brussels (UTC+1) is 16:00 UTC, and 12:00 in New York (EDT since 14 March).
2. He duplicates it for 30 March. The form proposes 17:00 Brussels, now UTC+2 (since 28 March): 15:00 UTC, so 11:00 in New York. The form says "For you: Tuesday 30 March, 11:00 Eastern Daylight Time". Nora sees the event an hour earlier than last week, which is true. (To verify against the real zone data in tests.)

## 6. Surfaces

### 6.1 The events list of a space

1. A new branch in `Show.tsx` for `space.type === 'events'`, with `events: Paginated<Event>` like `posts` and `resources`, and a new `EMPTY_STATES` entry.
2. Two links "Upcoming" and "Past" with `aria-current="page"` (rules.md, Front-end rule 10), the URL `?scope=past`, `preserveState`. They are links, not an ARIA tab widget, because each loads a list.
3. "Upcoming": `ends_at` now or later, by start. An event in progress is first, labelled "Happening now". Cancelled ones carry "Cancelled". "Past": the rest, newest first, 20 per page, "Load older".
4. A card: cover, title as the link, date and time in the member's zone, the host, "N places left" only when a limit exists, "You said Yes" for a member who answered.
5. "New event" shows to those who may create (7.2). Drafts show to their host and managers, labelled "Draft", first.
6. The list reloads from the space channel `community.{c}.space.{s}` (identifiers only; rules.md rule 8, ADR 0015 point 8).

### 6.2 The event page

1. Route `/community/{communitySlug}/space/{spaceSlug}/event/{eventSlug}` (`events.show`), in the `auth.optional` group of `routes/web.php`, like `resources.show`.
2. Order: title (the only `h1`), state line, cover, date block, host, answer group, meeting block, description, notes and replay (after the event), comments.
3. The date block is text: weekday, date, time range, zone in words, and the host's time when it differs. Never an image of a calendar page.
4. "Join the meeting" is always there for those who may see it, because the member needs the link for the calendar. A cancelled event shows the banner; a past one says "This event is over".

### 6.3 The host form

1. A page, not a dialog: it is long, and a page is simpler at 360 px (if a dialog, `Dialog` from `@/Components/ui`, rules.md Front-end rule 11).
2. Fields: title, description (`MarkdownEditor`), cover with its description, date and time fields (4.2), zone, address, place, who sees them, limit, "A replay will exist", host (a community member, default the author).
3. Buttons: "Save as draft", "Publish". A published event: "Save changes" and a menu "Cancel event", "Back to draft", "Duplicate", "Delete".
4. react-hook-form with a Zod schema in `libs/common`; limits as constants in `App\Constants` and `constants.ts` (rules.md, Data rule 6).

### 6.4 The answers, and who sees who answered

1. The member sees one `SegmentedControl` (`apps/knowii/src/Components/ui/SegmentedControl.tsx`, a `radiogroup`) with the legend "Your answer" and the radios "Yes", "Replay only", "No". It saves at once; a status says "Saved: Yes".
2. The member sees their own answer, no other, no count. With a limit: "28 places left", or "Full".
3. The host, the owner and the admins open "Answers": names of Yes, then Replay only (alphabetical, linked to the member page), and the count of No without names. Read only, no export in the first version.
4. A moderator with `delete_content` does not see the list (7.2, point 2).

### 6.5 "Add to calendar": the `.ics` file

1. A button on the event page, in the card menu and in the 24-hour reminder. One event per file.
2. Route `GET /communities/{community}/spaces/{space}/events/{event}/calendar-file`, `text/calendar; charset=utf-8`, `Content-Disposition: attachment`, access checked first, like `GET .../materials/{material}/download` (ADR 0037). To check: the file-route naming in ADR 0011.
3. A small class `App\Support\IcsFile` writes RFC 5545 (no new dependency): one `VEVENT` with `UID` (event cuid plus the instance host), `DTSTAMP`, `DTSTART` and `DTEND` in UTC with `Z` (no `VTIMEZONE` for single events), `SUMMARY`, `DESCRIPTION` (300 characters of plain text, the page address, and the meeting address only if this reader may see it), `URL`, `LOCATION` (same rule), `SEQUENCE` (`events.sequence`), `STATUS` (`CONFIRMED` or `CANCELLED`), lines folded at 75 bytes and escaped.
4. No `VALARM`: the member's calendar decides alerts. The file name is the title slug plus the date. The date and time are also in the page and the email body (notification design 9.10, point 8).
5. On a change, the email of slice 4 attaches a file with the same `UID` and a higher `SEQUENCE`. No `METHOD:REQUEST` invitation: the answer stays in Knowii. A visitor gets no file in the first version.

### 6.6 A private calendar feed per member: decision

1. It is one secret address per member that a calendar app subscribes to, showing the events the member said Yes to, kept up to date without mail.
2. For: a calendar app reads a URL, not an API token (section 9.3).
3. Against: a new credential outside Sanctum (ADR 0022) that works without a login, an access check on every fetch (a member who left must vanish), a reset, rate limits, and a new data item for the policy.
4. Recommendation: later, with its own short ADR (slice E7). One `.ics` per event answers #1388. Alternative: build it early.

### 6.7 The next event on the home and in the "Catch up" card

1. The community home gets "Next events": up to 3 upcoming events of the events spaces the member can see, with date in the member's zone. It follows the "Recent resources" code in `app/Http/Controllers/Inertia/CommunityController.php` (lines 48 to 66). No event, no block.
2. The "Catch up" card (notification design 5.5) ends with the next event, from `GET /communities/{community}/events?scope=upcoming&limit=1`. For a Yes it says "You said Yes". Nothing counts missed events.
3. The public community page (#1485, `app/Support/PublicCommunity.php`) lists upcoming events of public events spaces (title and time) if the owner makes one public (decision 6). To check: the visibility of the three event spaces today.

### 6.8 My events across communities

1. Page "My events" (`/events`, in the user menu): upcoming events with my answer Yes or Replay only in all my communities, with the community name when I am in several (notification design 2.6, point 4), and "I host".
2. API `GET /user/events` with `answer` (`yes`, `replay`, `host`) and `scope`. Ability `events:read`.

## 7. Rules

### 7.1 Who may see

1. A published or cancelled event follows `SpacePolicy::view` of its space (visibility, access groups of the space and its space group, public spaces). A draft: the host, the owner, the admins.
2. `SpacePolicy::view` stays the one place of the reading rule (backend.md, "Access groups"). The event policy never tests groups itself.
3. A moderator who cannot read a `groups` space has no right on its events (#1325).

### 7.2 Who may create, edit, cancel, delete

| Ability                                         | Policy                                            | Owner | Admin | Moderator                 | Member              | Outsider     | Visitor      |
| ----------------------------------------------- | ------------------------------------------------- | ----- | ----- | ------------------------- | ------------------- | ------------ | ------------ |
| See a published event                           | `EventPolicy::view`                               | yes   | yes   | yes                       | yes                 | public space | public space |
| See a draft                                     | `EventPolicy::view`                               | yes   | yes   | host                      | host                | no           | no           |
| Create                                          | `SpacePolicy::contribute`                         | yes   | yes   | yes                       | if policy `members` | no           | no           |
| Edit, publish, back to draft, replay, duplicate | `EventPolicy::update`                             | yes   | yes   | host                      | host                | no           | no           |
| Cancel, delete                                  | `EventPolicy::update`, `::delete`                 | yes   | yes   | host, or `delete_content` | host                | no           | no           |
| Answer                                          | `EventPolicy::answer` (view, published, not over) | yes   | yes   | yes                       | yes                 | no           | no           |
| Read the answers                                | `EventPolicy::viewAnswers`                        | yes   | yes   | host                      | host                | no           | no           |
| Comment                                         | `EventPolicy::comment` (view)                     | yes   | yes   | yes                       | yes                 | no           | no           |

1. "Host": the member named as host, while still a member (roles-and-permissions.md, rule 2). A creator in a `members` space becomes the host and may name another member.
2. Like posts: a moderator with `delete_content` cancels and deletes the events of others and does not edit them (#1444, note 2). Owner and admins do everything. No new moderator right (ADR 0017). Moderators do not read answers: they are personal data that moderation does not need.
3. A published future event that has a Yes or Replay only answer cannot be deleted: "Cancel it first, so members are told." Drafts, past and cancelled events can be deleted, with answers, comments and rows cascading.
4. `tests/Feature/PolicyTest.php` and `documentation/architecture/roles-and-permissions.md` get a row per ability.
5. Events do not move between spaces in the first version (2.2), so #1444 point 4 waits.

### 7.3 The meeting address

1. `location_visibility` (3.7). The server cuts the fields from the resource for readers who may not see them, like `capture` in `CommunityResourceApiController.php`: cut on the server, never hidden by the client (ADR 0044, point 8).
2. A test scans props and API answers for the address as a visitor, an outsider and a non-matching member, like `tests/Feature/PublicSpacesTest.php`.

### 7.4 What a visitor sees

1. Title, cover, description, notes, date and time, host as name, username and avatar, comments as for posts.
2. Never: the address, the place, the replay link, answers, counts, names of access groups, the answer buttons.
3. Reads for a visitor, with the `public-reads` limiter and the ability test: the list, one event, its comments. They join the list of ADR 0044, point 9 (an amendment).

### 7.5 Comments on an event

1. Reuse the comments of posts. `comments` already holds resources: `community_resource_id` beside `post_id` and a check `num_nonnulls(post_id, community_resource_id) = 1` (`database/migrations/2026_11_09_220000_add_resource_comments.php`, ADR 0014). An event adds `event_id` (cascade); the check covers three columns.
2. That gives replies, mentions, reactions on comments, moderation and markers (ADR 0043) with no second system.
3. A reply to my comment and a mention work as for resource comments: the row points at the comment (`app/Support/NotificationItem.php` already maps a comment on a resource). A first-level comment on an event tells the host (kind 1). No follows for events in the first version, as for resources (ADR 0042, point 30).
4. No reactions on the event itself: a like is a count of people (principle 2).

### 7.6 Capacity and "full"

1. `capacity` counts Yes answers only.
2. `AnswerEvent` locks the event row, counts Yes, and refuses a new Yes at the limit with 422 "This event is full." The page keeps the old answer and shows the text next to the group (`role="alert"`), and offers "Replay only" when a replay is expected, else "No".
3. A member who moves from Yes to Replay only or No frees a place. Nobody is promoted: no waitlist (#1413).
4. With a limit the page says "28 places left", at the limit "Full". No "only a few left", no urgency colour. Without a limit, no count.
5. A host may lower the limit under the number of Yes answers. Nobody is removed; the form says how many Yes exist. Managers cannot add a member above the limit by hand.

### 7.7 A member who leaves or is blocked

1. `Community::removeUser` (`app/Models/Community.php`, line 364) deletes the member's answers in that community, with the group rows, course progress and quiz results. A returning member starts with none.
2. A deleted account deletes its answers (cascade); events it hosted stay, `host_id` becomes null ("Deleted member"). A member who left keeps `host_id` but loses host rights (7.2, point 1).
3. A member who blocked the host (ADR 0028) gets no kind 25 line for that host's events. Kinds 26 to 29 stay: they concern the member's own answer and never name the host. The blocked person is never told (notification design 6.9). A block does not hide the event. To approve with decision 4.

### 7.8 Other rules

1. A limiter `event-answers` per member. A deleted space or community deletes its events and files (cascade, `ContentFiles`). The option `delete_content` of ADR 0043 does not delete hosted events.

## 8. Notifications

This maps each moment to rows 25 to 29 and the levels of the notification design (2.1, 3, 6.4, 6.5, 6.7). It builds no second system. Slice 8 of that design is #1482 and needs #1388 and #1393.

### 8.1 Kind 25: event created

1. Level 2, no email, to members of the events space who can see it and did not mute it. The text is built at read time (ADR 0042, point 9): "Tue 18 Nov, 17:00 Central European Time: <title>" in the reader's zone.
2. Written at the first publication, not at the draft. `published_notified_at` makes it run once. It uses `FanOutNotifications` (chunks of 500) with the 5-minute wait and `notify_now` for managers. A past event, an import (`Notify::silenced()`) and the host write nothing.
3. A member at "Only what is for me" gets no line: the event is in the space and in the summary "Coming up" block.

### 8.2 Kind 26: moved or cancelled

1. Level 1, to Yes and Replay only, at once, when `UpdateEvent` changes `starts_at`, `ends_at`, `meeting_url` or `place`, or `SetEventState` cancels or restores. The text is live: "Moved: <title>. New time: ..." or "Cancelled: <title>." A restore gives "Back on: <title>".
2. The notification design says a change of time is the only edit that notifies (6.11, point 2). A changed address matters as much: a Yes holder would go to a dead room. Decision 12 asks for the addition.
3. One open row per member and event (6.6): a second fix updates the row and clears "seen".
4. Email (slice 4): level 1, counted, always sent for a Yes (6.5, point 3), with the new `.ics` file; quiet hours do not hold it when the start is within 12 hours. The email job waits 5 minutes and reads the event again, so a typo fix does not send two mails. The line is at once. This settle time is new and small (decision 12).

### 8.3 Kinds 27 and 28: reminders at 24 hours and 1 hour

1. Level 1, for Yes only. Never for Replay only, No or no answer.
2. A command `events:remind` runs every minute (`routes/console.php`, `withoutOverlapping`, `onOneServer`). It selects published events with a start in the window and a null guard, and queues rows. A partial unique index on `(user_id, kind, event_id)` for these kinds makes a retry harmless (as for lessons, ADR 0042, point 39).
3. Skipped: the member answered after the reminder time, saw the event since the last change, or the event is cancelled or draft. A line that is seen sends no email (notification design 6.2, point 5).
4. When the start moves, both guards reset. A reminder whose time already passed is skipped; the member has the move line. An event created less than 24 h before the start has no 24-hour reminder.
5. Email (slice 4): "Tomorrow 17:00 Central European Time: <title>" with the `.ics` and "I cannot come, send me the replay"; at 1 hour "In 1 hour: <title>. Join", which passes quiet hours (6.7, point 2).
6. Budget: 7 emails in 7 days, one for the summary (6.5). A member with Yes on 3 events uses 6. Proposal: when the budget is full, the 24-hour email drops first (the line stays); the 1-hour and move mails always go and count (decision 12).

### 8.4 What the events feature gives `Notify`

1. New cases in `KnowiiNotificationKind`: `event_new` (2), `event_changed` (1), `event_reminder_24h` (1), `event_reminder_1h` (1), `event_replay` (1, Replay only), `event_replay_yes` (2). The notification design gives kind 29 the level "1 or 2", but `level()` is a function of the kind in `KnowiiNotificationKind.php`, so two cases are needed.
2. A column `notifications.event_id` (cascade) and a check "exactly one of five items" (ADR 0042, points 7 and 39). `NotificationItem.php` maps an event to its space. New `KnowiiNotificationReason` cases.
3. Recipients come from `event_answers`. The `Notify::deliver()` rules stay: not the actor, access checked, blocks (7.7), mutes.
4. `PruneUnviewableNotifications` and `NotificationAccess` also drop rows of an event that went back to draft or that the member cannot view.
5. The guard columns and `events:remind` (8.3).
6. A query for the summary "Coming up" block (notification design 5.7, point 3 and 5.8, point 1): published events the member can view, no answer, starting within 14 days. In the short form it gives "the next event" (5.8, point 4).
7. A query for the "Catch up" card (6.7). The member export gets the answers (10.2).

### 8.5 Kind 29: replay ready

1. Written once, when `replay_url` goes from empty to a value (`replay_notified_at`). A later edit writes nothing.
2. Replay only: level 1, with an email when published (notification design 6.4). Yes: level 2, a line. No and no answer: nothing. Same text: "The replay of <title> is ready."

### 8.6 Summary

| Moment                              | Kind                                    | Who              | Level | Email                 | Slice            |
| ----------------------------------- | --------------------------------------- | ---------------- | ----- | --------------------- | ---------------- |
| Published                           | `event_new` (25)                        | space members    | 2     | none                  | E4               |
| Moved, cancelled, back on           | `event_changed` (26)                    | Yes, Replay only | 1     | at once (after 5 min) | E4 line, E5 mail |
| 24 h                                | `event_reminder_24h` (27)               | Yes              | 1     | T-24 h                | E4, E5           |
| 1 h                                 | `event_reminder_1h` (28)                | Yes              | 1     | T-1 h                 | E4, E5           |
| Replay added                        | `event_replay`, `event_replay_yes` (29) | Replay only, Yes | 1, 2  | level 1 only          | E4, E5           |
| Comment on my event, reply, mention | existing kinds                          | host, authors    | 1     | rhythm                | E3               |

## 9. API parity

Routes under `/api/v1` (`app/ApiRoutes.php`; ADR 0011 for naming, methods, status codes, pagination `limit` and `offset`, errors). Constants go in `ApiRoutes.php` and `libs/common/src/lib/constants.ts`.

### 9.1 Routes

| Method and path                                              | What                                                            | Ability              | Visitor            |
| ------------------------------------------------------------ | --------------------------------------------------------------- | -------------------- | ------------------ |
| `GET communities/{community}/spaces/{space}/events`          | list, `scope` `upcoming` or `past`                              | `events:read`        | yes (public space) |
| `POST` same                                                  | create (draft or published)                                     | `events:write`       | no                 |
| `GET`, `PATCH`, `DELETE .../events/{event}`                  | one (large fields), change (also `replay_url`, `notes`), delete | read / write         | `GET` yes, cut     |
| `PUT .../events/{event}/state`                               | `draft`, `published`, `cancelled`                               | `events:write`       | no                 |
| `POST .../events/{event}/duplicate`                          | a draft copy                                                    | `events:write`       | no                 |
| `GET`, `PUT`, `DELETE .../events/{event}/answer`             | my answer                                                       | read / write         | no                 |
| `GET .../events/{event}/answers`                             | names and counts, host and managers                             | `event-answers:read` | no                 |
| `GET .../events/{event}/calendar-file`                       | `.ics`                                                          | `events:read`        | no                 |
| `GET`, `POST`, `PATCH`, `DELETE .../events/{event}/comments` | as resource comments                                            | read / write         | `GET` yes          |
| `GET communities/{community}/events`                         | upcoming events of all events spaces I can see                  | `events:read`        | public page only   |
| `GET user/events`                                            | my events across communities                                    | `events:read`        | no                 |

1. Drafts never reach a reader who may not see drafts. The cut fields (address, place, replay link) are left out for readers who may not see them (7.3), tested on every route.
2. Limiters: `public-reads` for visitors, `event-answers` for answers, `comments` for comments.

### 9.2 Token abilities (ADR 0022)

1. `events:read` (in the default set, like `pages:read`) and `events:write` (not). `event-answers:read` is not default: it reads other members' personal data, and the policy still limits it to the host and managers. A member's own answer is read with `events:read` and set with `events:write`.
2. Listed in `JetstreamServiceProvider`, `API_TOKEN_PERMISSIONS` and the API README. `ApiTokenAbilitiesTest` fails when a route has none. Every route has a Bruno request, OpenAPI text and Pest tests.

### 9.3 What an outside calendar tool can read

1. A calendar app sends no bearer token. A script with `events:read` can read events and each `.ics` file: that serves an automation, not a subscription.
2. A subscription needs the private feed (6.6), later.

### 9.4 Real time

1. `community.event.created`, `.updated`, `.deleted` on the space channel, identifiers only, queued on `broadcasts` (ADR 0009, 0015 point 8).
2. `community.event.answers.changed` with the event cuid only: the answer list and "places left" reload. No name, no count.

## 10. Privacy and data

### 10.1 What is stored, for how long

Each row needs an entry in `config/retention.php`, a `retention:prune` step with a test, an update of `RetentionScheduleTest`, a policy placeholder and a line in the register of processing and, where needed, the export (rules.md, Data rules 8 and 10; notification design 8.5).

| Data                                                                        | Why                                   | Kept (proposal)                                                                                                                                                                   |
| --------------------------------------------------------------------------- | ------------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Events (text, times, zone, address, place, cover, replay link, notes, host) | Community content                     | With the community; in the community export; deleted with the space or community                                                                                                  |
| Answers (`event_answers`)                                                   | Reminders, places, showing the answer | Until 90 days after the event ends (new `retention.event_answers_days`), or until the member leaves or the account goes. Shown only to the member, the host, the owner and admins |
| Guards, `sequence`, `*_notified_at`                                         | Send once                             | With the event                                                                                                                                                                    |
| Notification rows of event kinds                                            | The list                              | ADR 0042: 60 days after read, 90 never read                                                                                                                                       |
| Delivery log of event mail (slice 4)                                        | No second send                        | 30 days                                                                                                                                                                           |

1. Not stored: who joined, for how long, who opened the replay or a calendar file, the zone of a visitor.
2. Answers are intentions. 90 days lets the host follow up and stops the data living for years. Alternatives: delete at the end of the event, or keep with the event (more data than needed).
3. Before answering, the member reads: "The host of this event sees your answer (Yes or Replay only). Other members do not."

### 10.2 Export and deletion

1. The member export (`app/Actions/Users/ExportUser.php`, checklist in ADR 0024) lists answers: event cuid, title, answer, dates; no names of others. Hosted events appear by cuid and title.
2. The community export holds the events, not the answers (they belong to members, like notifications, ADR 0042 point 28).
3. Account deletion deletes answers and notification rows and nulls `host_id` (ADR 0024). Leaving deletes answers (7.7).

### 10.3 Third parties

None new. The meeting service is the host's choice, outside the platform; no personal data goes there. The replay player loads after a click from the allow-list in `VideoEmbed.php`. Email uses the existing provider (slice 4).

### 10.4 What the policy must say

The words change only after the owner approves them (rules.md, Data rule 10: `resources/markdown/policy.md` section 2). Proposed, not final:

1. "Your answers to events (yes, replay only or no), until 90 days after the event or until you leave the community."
2. "The host and the team of an event see who answered yes or replay only. They do not see when you open the meeting."
3. "We do not record who attends a meeting."
4. For the replay field: "Before you add a recording, make sure the people in it agreed."

## 11. Accessibility

### 11.1 Date and time fields

1. Four labelled fields in one `fieldset` with the legend "When": "Start date", "Start time", "End time", "Time zone". Use native `date` and `time` inputs first (keyboard and screen reader friendly, they follow the locale). To check with NVDA and VoiceOver in a real browser (rules.md, Front-end rule 11: jsdom hides focus problems).
2. The zone field is a labelled select with the host's zone first. 400 zones need search: a combobox, tested by keyboard. To check: `Select.tsx` may not offer typing; a native `select` is the fallback.
3. Errors are `role="alert"` next to the control, focus stays (notification design 9.6, point 5). The live sentence of 4.2 is a status region that updates on blur, not on each key. Labels never depend on a placeholder.

### 11.2 Times read by a screen reader

1. A `time` element with a UTC `datetime`; the visible text has weekday, date in words, range and zone in words. No tooltip carries the full date (notification design 9.9). `RelativeTime` puts it in `title` only: events do not use it for the start (to check).
2. The short list form has the long form as visually hidden text. "Starts in 3 hours" is extra, never the only time.
3. "Cancelled", "Past", "Full" and "Draft" are text, never colour only.

### 11.3 Lists and calendar views

1. A real list: each card is a list item with a heading link and plain text. "Upcoming" and "Past" are links with `aria-current="page"`.
2. "Load older" puts focus on the first new item and says "20 more loaded" (notification design 9.1, point 5).

### 11.4 The answer buttons

1. A `radiogroup` (`SegmentedControl`) in a `fieldset` with the legend "Your answer": real radios with checked state, arrow keys, one tab stop. It saves at once; "Saved: Yes" in a status region; an error is `role="alert"` and the old choice returns.
2. "Yes" on a full event: `aria-disabled="true"` with the reason as text, not a tooltip (notification design 9.6, point 7). Past or cancelled: the group is replaced by the text of the old answer. No `disabled` or `loading`: `aria-disabled` and `aria-busy` with a guard (9.1, point 4).

### 11.5 Other

1. 360 px: one column, cover 16:9 above the title, targets 44 px, no horizontal scroll, usable at 400% zoom. Both themes, forced colours, brand tokens only.
2. "Join the meeting" says "Opens in a new tab" (ADR 0026, point 4). Emails follow notification design 9.10. No sound.

### 11.6 Release test (each slice)

As notification design 9.11: axe in the Vitest tests of the list, page and form; a written keyboard script (create, publish, answer, add to calendar, cancel); NVDA with a desktop browser and VoiceOver on a phone; 360 and 1920 px, 400% zoom, both themes, forced colours. Pass: no axe violation, every step works without a pointer.

## 12. The move from the old tool

Known (#1388 comments of 2026-11-15, the project note, #1396):

1. 17 events in 3 event spaces. The header link "Events" is off. Every event runs in a built-in live room or stream with a recording.
2. #1396 says events have no export; the project note says the support team can export posts, comments and events as CSV on request. They disagree (to check).
3. The data of the old tool is deleted about 90 days after its plan is cancelled (project note).
4. #1431 (import of posts, comments, messages) does not name events. No issue about importing events exists (to check).
5. The old descriptions do not render Markdown (stars show as text). The platform renders Markdown, so nothing converts; line breaks must survive.

Recommendations:

1. Future events: create them by hand, published, before the invitation goes out, so the invitation can name the next event once (notification design 4.I). There are few.
2. Answers: do not import. A person answers again after the move. An imported Yes for someone who may never join would send a reminder nobody asked for (principle 4). Alternative: import answers only for members who accept the invitation (not worth it for a few events).
3. Past events with recordings: import them as past events with a replay link, only if the recording stays reachable after the old tool is deleted. Its links die with it. The owner must first put the recordings somewhere durable (to check: can they be downloaded?), then import. This is the largest risk of the move.
4. How: a script that calls the API with the owner's token (`events:write`). The owner and admins may enter past dates (4.2, point 6), with no notification. 17 events do not justify an importer; a small issue "Import past events with replay links" or an extension of #1431 if the export is larger.

## 13. Decisions for the owner

Each has my recommendation and the main alternative.

1. The model. Recommend: a space type `events` with its own table (3.2). Alternative: an event as a post with fields.
2. The answer words. Recommend: "Yes", "Replay only", "No" (notification design 4.F); "Replay only" shows only when the host says a replay will exist. Alternative: "Yes", "Maybe", "No" (no clear reminder rule for "Maybe").
3. Order with the time zone. Recommend: build `users.time_zone` first (part of #1476 and #1423), so email, calendar file and reminders can use it. Alternative: events first with the browser zone.
4. Who creates and manages. Recommend: the posting policy of the space decides who creates (default `moderators`, `members` allowed); the host edits their event; moderators with `delete_content` cancel and delete; owner and admins do all; a block of the host removes only the "new event" line (7.2, 7.7). This absorbs most of #1444. Alternative: only the team creates at first.
5. Hosts. Recommend: one host per event. Alternative: several (the issue says "one or more").
6. Visitors and the address. Recommend: in a public events space a visitor sees title, cover, description, time and host, never the address, place or replay; the owner chooses which events spaces are public; the address can be limited to Yes. Alternative: let a host show the address to visitors (needs #1413).
7. Capacity. Recommend: an optional limit, "N places left" and "Full", no waitlist, no urgency text. Alternative: no limit at first.
8. Who sees answers. Recommend: a member sees only their own; the host, owner and admins see names of Yes and Replay only and a count of No. This differs from the tool of today ("N is going" for everyone), on purpose (notification design 8.2, point 4). Alternative: show the Yes count to everyone.
9. Recurring events. Recommend: not now; "Duplicate"; series later (#1443). Alternative: a weekly repeat now (a second model).
10. The calendar. Recommend: one `.ics` per event now; a private feed later with its own ADR (6.6). Alternative: the feed early.
11. Comments. Recommend: reuse post comments through `comments.event_id`, no reactions on the event, no follows. Alternative: no comments at first.
12. Notification additions. Recommend: a changed address or place also notifies Yes and Replay only; the move email waits 5 minutes; when the weekly budget is full the 24-hour email drops first and the 1-hour and move mails always go (8.2, 8.3). Alternative: only a time change notifies, budget rule left open.
13. Policy and form words. Recommend: the four sentences of 10.4 as a base, approved before data is stored. Alternative: none is possible, since answers are new personal data.
14. Retention of answers. Recommend: 90 days after the event ends. Alternative: delete when the event ends, or keep with the event.
15. Recordings for the move. Recommend: re-host the old recordings first, then import past events with replay links by script (section 12). Alternative: import no past event.
16. Old answers. Recommend: do not import. Alternative: import for members who accept the invitation.
17. Delete versus cancel. Recommend: a published future event with answers must be cancelled first, so members are told (7.2, point 3). Alternative: allow delete with a confirmation and tell nobody.
18. Later features. Recommend: waitlist, public landing page and paid events stay in #1413, series and a grid in #1443. Alternative: none; they are already outside #1388.

## 14. Build slices

Each slice gives something a member can use and fits one or two pull requests. The release test of 11.6 belongs to each. "E" slices are of this design; "slice 8" is the notification design's.

1. E0, "It knows my time zone": `users.time_zone`, its validation, browser fill, setting, and the question when the browser differs (#1476 part, #1423). Policy words of the notification design (decision 7, approved). Skip if decision 3 takes the alternative.
2. E1, "A host publishes, members read". ADR (amends 0012, 0042 point 34, 0044), the case `events`, migration, model, policy with its matrix tests, actions (create, update, state, delete, duplicate), resource, API without answers and comments, abilities `events:read` and `events:write`, list, event page, host form, cover and its upload usage, "Next events", live updates, the default level and the line of kind 25 in the app. Needs decisions 1, 4, 5, 6. Two pull requests: server and API, then screens. No policy words yet.
3. E2, "I answer and add it to my calendar": `event_answers`, `AnswerEvent` with places, the answer group, "places left", the host's list, `IcsFile` and its route, "My events", leave, block and deletion rules, the retention step and test, the member export, `event-answers:read`. Needs decisions 2, 7, 8, 13, 14 and the register of processing.
4. E3, "Talk about it, and after the event": comments (`comments.event_id`, the check, routes, page), replay link, notes, `replay_expected`, delete and cancel rules. Needs decisions 11, 17 and the recording words.
5. E4, "I am told in the app" (slice 8 part A, #1482): the kinds, `notifications.event_id`, reminders command and guards, pruning, fan-out and access tests. Needs E2.
6. E5, "I am told by email" (slice 8 part B): emails of 8.2, 8.3, 8.5 with the `.ics`, zones from `users.time_zone`, the budget rule. Needs #1393 and decision 12.
7. E6, "Coming up and Catch up": the summary block and send rule (5.8), the event line of the Catch up card. Needs #191 and #1476.
8. E7, "My calendar feed": the feed (6.6), its ADR, limits, reset, policy line. Only if approved.

On approval: #1388 becomes the parent of E1 to E3; #1482 is E4 and E5; #1444 is mostly absorbed by E1; #1443, #1413, #1412 stay.

## 15. Rejected ideas and ideas for later

Rejected:

1. A live room, stream or recording (#1412).
2. Events as posts with fields; events with no space (3.1).
3. A stored state "past" (3.4). A fourth answer "Maybe" (3.6).
4. An automatic Yes for every member, "N is going", "few places left", a countdown for everyone: counts as pressure (notification design 8.2).
5. Attendance tracking: it needs a live room and unapproved personal data.
6. Reminders at will (any number, any text, any audience) as the tool of today offers: a mail editor for hosts that breaks the budget (notification design 6.5). A follow-up question after an event (already removed there).
7. Reminders for members who did not answer or said No.
8. A calendar invitation with `METHOD:REQUEST` (the answer stays in Knowii).
9. Reactions on the event itself. Zone detection by IP address. Free text for the zone. A map in the page (a third party, a new CSP origin, ADR 0007).
10. Writing the name of the old tool anywhere in the platform, issues or policy.

Later:

1. Series with local hour kept over summer time and answers per date (#1443). A month grid (list alternative, arrow-key control, announced month change) and an events page across spaces.
2. Waitlist with promotion, a public landing page with sign-up, paid events through entitlements (#1413).
3. A private calendar feed (6.6). A calendar file for a visitor, without the address.
4. Several hosts, moving an event between spaces, pin to space.
5. A CSV of answers with a log line (#1444, note 1).
6. A phone alert for the 1-hour reminder (slice 10, #115). Topics (#1404) and `#` links to events (#1439).
7. Importing Yes answers for members who accepted the invitation. Outgoing webhooks for answers (#1426), with minimal personal data.

## 16. Facts to check before the build

1. "Replay only" versus "Maybe" (decision 2).
2. Whether Inertia server rendering is on (4.3, point 4).
3. How `app/Support/UploadReferences.php` counts usages (3.9).
4. The file-route naming rule in ADR 0011 (6.5, point 2).
5. How PHP and ICU zone data are updated in the image (4.1, point 3).
6. The visibility and posting setting of the three event spaces today (6.7, point 3; decision 6).
7. Whether the old tool can export events and recordings, and how long they stay (section 12).
8. Whether members rely on a calendar feed in the old tool (decision 10).
9. Whether a searchable select exists in `apps/knowii/src/Components/ui` (11.1, point 2) and whether `RelativeTime` needs an accessible full date (11.2, point 1).
10. The next ADR number (0048 at the time of writing).
