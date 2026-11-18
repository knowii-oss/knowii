# 0053. Events: a space type with its own table

- Status: Accepted
- Date: 2026-11-18
- Issue: #1513 (slice E1a of #1388)
- Pull request: #1667
- Amends: [0012](0012-space-groups-and-typed-spaces.md) (a new space type), [0042](0042-notifications.md) (point 34: "Everything" for events spaces, the kind `event_new`, `notifications.event_id`), [0044](0044-public-spaces-for-visitors.md) (point 9: three more public reads)

## Context

A community plans events: office hours, workshops, live sessions. The old tool keeps them in event spaces of their own. Knowii needs a dated item that members read in their own time zone, with a meeting address that only members see, and that tells the members once when it is published. The design of #1388 (2026-11-16, approved with its 18 decisions) compared three models:

1. A space type `events` with a table `events`. A space already carries visibility, access groups, posting policy, navigation, API and live updates; resources ([ADR 0015](0015-resources-space-type.md)) and links ([ADR 0026](0026-link-spaces.md)) joined the same way.
2. An event as a post with start and end columns in a discussion space. A post has no draft, no start, no answers, and its list is ordered by creation; Upcoming and Past would need a flag that is a space type under another name.
3. Events without a space. It rebuilds visibility, access groups, navigation and permissions: the duplicate that ADR 0015 removed.

## Decision

1. The model (design 3.1, option 1). A new case `events` of `KnowiiSpaceType`, and a table `events` (`App\Models\Event`; the folder `app/Events/` keeps the broadcast events). The type of a space never changes. The space form starts a new events space with the posting policy `moderators`. `SpacePolicy::contribute` (the posting policy) decides who creates an event; `CreateEvent` refuses another space type.
2. Times. `starts_at` and `ends_at` are UTC instants (`timestamptz`): the truth. `time_zone` is the IANA zone in which the host entered the time; it never changes the moment. The API takes ISO 8601 with an explicit offset and the zone, and checks that the offset is the real offset of the zone at that instant. A value without an offset, a wrong offset and a local time that the zone skips are field errors (400); the error of a skipped time names the last minute before and the first minute after the gap, from the zone data. A local time that exists twice is accepted with either real offset. The end is after the start and at most 24 hours later. A start in the past is for the owner and the admins only (to enter past events). PHP date classes and Carbon only. "Duplicate" keeps the wall-clock time of the zone, 7 days later, not 168 hours.
3. Past is derived. An event is past when `ends_at` is before now (`is_past` in the resource). No stored state, no job: a stored state needs a job to flip it, and to flip it back when a host moves the event.
4. States: `draft` (the host, the owner and the admins only), `published`, `cancelled` (still readable, so that a member with a link finds the news). Allowed: draft to published, published to draft (not once the event started: cancel it), published to cancelled, cancelled to published. `sequence` goes up with each change of state and each change of the start, the end, the address or the place.
5. One host (`host_id`, `nullOnDelete`). "Host" means the member named, while still a member and able to read the space. A host who left keeps `host_id` and loses every right; a deleted account leaves the event without a host. The owner, the admins and the host change an event; a moderator with `delete_content` who can read the space also cancels and deletes it when they can see it (never a draft). No new moderator right. "Duplicate" also needs the right to create an event in the space, goes through the time rules of a create (a skipped hour, a length that a change of the clocks would change, a start in the past), and gives the copy to the person who duplicates when the host may not host any more. The option of ADR 0043 that deletes the content of a member keeps the events they host.
6. `location_visibility`: `members` (every member who can see the event) or `going` (the host, the owner and the admins; the members who answer Yes join in a later slice). A visitor and a person who is not a member never get the meeting address or the place. The server cuts them in `EventResource` (`can_view_location`); the client never hides them. The server never opens the address.
7. Two token abilities ([ADR 0022](0022-api-token-abilities.md)): `events:read` (in the default set of a new token) and `events:write` (not).
8. Public reads (amends ADR 0044, point 9): `GET communities/{community}/events` (the upcoming events of the events spaces that the reader can read, with the guards of the public page), `GET .../spaces/{space}/events` and `GET .../spaces/{space}/events/{event}` accept a visitor, with `public-reads` and `events:read`. A draft is never in them for a reader who may not view it.
9. Live updates: `space.event.created` (at the publication from a draft), `space.event.updated` (a change of a published or cancelled event) and `space.event.deleted` (a delete, or a return to draft) on the space channel, through `SpaceContentChanged`, with `event_cuid` and `author_cuid` only. A draft sends nothing: only its host, the owner and the admins may know that it exists, and a person who may not see an event gets the answer of an event that does not exist (404). The design named them `community.event.*`: the code sends the content of a space on its space channel.
10. Notifications (amends ADR 0042, point 34). An events space may be at "Everything", and "Everything" is its built-in default; a manager may set `all` or `mine` as its default. A new kind `event_new` (level 2, reason `space_everything`) is written once, at the first publication of an event whose start is in the future (`published_notified_at`), never for a draft, never inside `Notify::silenced()` (which still sets `published_notified_at`, so a later publication tells nobody); the age guard of the other items does not apply. The fan-out waits 5 minutes (`notify_now` skips it, for `SpacePolicy::moderate`) and reads the event again. Recipients: the members at "Everything" who can view the space and did not mute it, never the host, never a member who blocked the host. One row per member and event (`dedupe_key` `event_new:event:{id}`): these rows do not bundle. The item is a new column `notifications.event_id` (ADR 0014), with a check that exactly one of five items is set, and a partial unique index on `(user_id, kind, event_id)`. Back to draft deletes the rows of the event. The client writes the line in the zone of the reader: "Tue 18 Nov, 17:00 Central European Standard Time: title".
11. Uploads: `upload_usages.event_id` keeps the cover and the images of the description alive for `uploads:prune`; the check of `upload_usages` covers five items.

What waits for the later slices: the screens, the event page and the host form (E1b, #1514); the answers, the places, the calendar file, "My events" and the Yes part of `going` (E2, #1515); comments, the replay link, the notes and "cancel it first" before a delete (E3, #1516); the kinds 26 to 29, the reminders and emails (E4 and E5, #1482); the summary block and the "Catch up" line (E6); the calendar feed (E7).

## Consequences

1. Events get one navigation, one permission model, one settings screen and the same live updates as the other content of a community.
2. Every switch on the space type gets a case: the policies, the notification levels, the space form and the empty state of the space page.
3. A list of events across spaces reads the events spaces that the reader can read (`SpaceTree`, or the public events spaces), never a table of its own.
4. A time in the API always carries its offset, and the server is the last check of the zone data of its PHP. A government that changes a zone later does not move an event: the instant stays.
5. The privacy policy names events in the content of a member (section 2, item 4, approved on 2026-11-17).

## Alternatives considered

1. An event as a post with fields: no draft, no start, the wrong order, and a type under another name.
2. Events without a space: a second permission model and a second navigation.
3. A stored state "past": a job that flips it, and flips it back when an event moves.
4. A time without an offset, read in the zone of the event: an hour that the zone skips or repeats would be guessed by the server, where the host must decide.
5. Several hosts: not needed for the first version (decision 5 of the design).
