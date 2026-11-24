# API requests (Bruno)

This folder is a [Bruno](https://www.usebruno.com/) collection. It holds an example request for every endpoint of the REST API (`/api/v1`), including the main error cases. It documents the API by example and lets you exercise it by hand against a running instance.

The reference description of the API is the OpenAPI document that Scramble generates from the code: `/docs/api` (interactive) and `/docs/api.json`.

## Use it

1. Open this folder as a collection in Bruno.
2. Copy `.env.example` to `.env` in this folder and set the email and password of a test user. The `.env` file is not committed (see the Bruno [dotenv secrets](https://docs.usebruno.com/secrets-management/dotenv-file) documentation).
3. Select the `Local` environment (`http://localhost:4200`).
4. Run `Get CSRF cookie`, then `Auth - Login`. Bruno keeps the session cookie for the next requests. For token authentication, run `Auth - Login (include token)` and send the token as `Authorization: Bearer <token>`.
5. Send `Accept: application/json` (the requests of this collection do): every error is then the JSON of [ADR 0011](../adr/0011-rest-api-design-guide.md), in every environment, except the plain-text 503 of an instance that is not configured and an `HttpResponseException` that carries its own response.

Requests that target an existing item (a community, a space) contain a cuid from the seed data. Replace it with a cuid from your database.

## Tokens and abilities

A personal API token (created on the "API tokens" page of the user menu) has abilities ([ADR 0022](../adr/0022-api-token-abilities.md)). Each route of `routes/api.php` names the abilities that it accepts (`ability:` middleware); a token without one of them gets a 403. The web session and the token of `Auth - Login (include token)` have every ability. A personal token expires after the lifetime chosen when it was created (30 days, 90 days by default, 1 year, or never); an expired token gets a 401. The login token lasts 12 hours. A token works on the API only: the pages of the application accept the session only, and answer a request with a token with a 403. The routes of the sign-in forms never read a token: they answer it as a visitor.

| Ability             | What it allows                                                        |
| ------------------- | --------------------------------------------------------------------- |
| `user:read`         | Read the current user, their blocks and their frequently used emoji   |
| `user:write`        | Change the avatar, the online status, the choice to be in the member lists and the time zone; send the link of a change of the email address again, or cancel the change |
| `account:delete`    | Delete your own account for good, with your password (and your two-factor code), `DELETE /v1/user`. Never in the default set of a new token |
| `communities:read`  | Read communities, space groups, spaces, members and invitations       |
| `communities:write` | Create, change and delete communities, spaces, members, invitations; join an open community, leave a community, let a removed member join again, change who can join, let search engines index the public spaces, transfer its ownership |
| `posts:read`        | Read posts, comments and reactions, preview Markdown                  |
| `posts:write`       | Write posts and comments, pin, react, upload images                   |
| `chat:read`         | Read the messages and threads of chat spaces, mark them as read       |
| `chat:write`        | Write, edit and delete messages, react to them                        |
| `messages:read`     | Read your direct messages, mark them as read (not in the default set) |
| `messages:write`    | Start conversations, write, edit and delete direct messages, react    |
| `notifications:read`  | Read your notifications and their count, the state of a thread that you follow, your level for a space, the "Catch up" data, your email status (not in the default set) |
| `notifications:write` | Mark your notifications as seen or read, undo read, "I am caught up", follow, stop following and mute a thread, choose your level for a space, visit a space, the pop-up switch, "Try this address again" (not in the default set) |
| `pages:read`        | Read the pages of page spaces, mark lessons as done, read the progress, read and download course materials, read and answer lesson quizzes |
| `pages:write`       | Create, change, reorder and delete pages and course materials, write and delete lesson quizzes (owner, admins, moderators) |
| `resources:read`    | Read resources, their comments and reactions                          |
| `resources:write`   | Add resources, comment and react on them                              |
| `resources:create`  | Add resources, nothing else (the browser extension, ADR 0018)         |
| `events:read`       | Read the events of events spaces, the upcoming events of a community, and your own answer to an event (ADR 0053) |
| `events:write`      | Create, change, publish, cancel, duplicate and delete events, set and clear your own answer to an event (not in the default set) |
| `event-answers:read` | Read who answered the events that you host or manage: the names of Yes and of Replay only, the counts, and the limit of places (`capacity`) of these events (not in the default set). Your own answer needs `events:read` |
| `groups:read`       | Read access groups and their members                                  |
| `groups:write`      | Manage access groups and their members (a sync from another system)   |
| `member-tags:read`  | Read the member tags of a community and the members who have them (not in the default set). The member list holds the tags of each member with `communities:read` |
| `member-tags:write` | Create, rename, recolor and delete member tags, put them on members and take them off (not in the default set) |
| `invitations:read`  | Read the imports of invitations and their rows, with the email addresses (not in the default set)              |
| `invitations:write` | Invite many people from a file: upload, send, send again, delete an import (not in the default set). A single invitation keeps `communities:write` |
| `invite-links:read`  | Read the invite links of a community and who joined through them (not in the default set) |
| `invite-links:write` | Create, change and delete the invite links of a community (not in the default set) |
| `identities:read`   | Read the provider accounts that you connected to sign in: the issuer, a label and the dates, never the identifier at the provider (not in the default set) |
| `identities:write`  | Disconnect a provider account (not in the default set)                |
| `entitlements:read` | Read the entitlement keys that the sign-in provider reported for you and the date of the last check (not in the default set) |

## Member tags

A member tag is a label that the managers of a community put on its members, for example "Founder" ([ADR 0060](../adr/0060-member-tags-labels-on-members.md), #1389). Its visibility is `members` (the members of the community see it on the members who have it) or `managers` (the default: only the owner, the admins and the moderators with the manage members right). A tag of the managers does not exist for another caller: it is in no list, and each route below answers 404 for it, with the body of a cuid that does not exist, also for a token without the abilities of the tags. The refusals of a write come in this order: 404, 403, 400 for a wrong field, then the limit (422).

| Method and path | What it does |
|---|---|
| `GET /communities/{community}/member-tags` | The tags, by name. A manager gets every tag with `members_count`; another member gets the tags of the members with `members_count` null. Ability `member-tags:read` |
| `POST /communities/{community}/member-tags` | Create a tag: `name` (2 to 32 characters, one line, no invisible format character, unique without the case), `color` (`gray` by default, `red`, `orange`, `yellow`, `green`, `teal`, `blue`, `purple`), `visibility` (`managers` by default, or `members`). 100 tags per community at most. Owner and admins. Ability `member-tags:write` |
| `PATCH /communities/{community}/member-tags/{tag}` | Change the fields of the request. Owner and admins. Ability `member-tags:write` |
| `DELETE /communities/{community}/member-tags/{tag}` | Delete the tag: every member who has it loses it. 204. Owner and admins. Ability `member-tags:write` |
| `GET /communities/{community}/member-tags/{tag}/members` | The members who have the tag, by name, as authors, with `limit` and `offset`. Managers only, for both visibilities. Ability `member-tags:read` |
| `POST /communities/{community}/member-tags/{tag}/members` | Put the tag on a member: exactly one of `member` (user cuid) or `username`, never `email`. Twice changes nothing (200). 20 tags per member and community at most. Managers. Ability `member-tags:write` |
| `DELETE /communities/{community}/member-tags/{tag}/members/{member}` | Take the tag off: 204, or 404 for a member without the tag. Managers. Ability `member-tags:write` |

Each entry of `GET /communities/{community}/members` holds `tags`: the tags of the member that the caller may see, by name. Nothing tells a member that a tag was put on them or taken off. A member reads all their own tags, of both visibilities, in the export of their data.

## The member list

A member chooses to be in the member list of their communities (#1466, [ADR 0063](../adr/0063-a-member-chooses-to-be-in-the-member-list-of-their-communities.md)): one choice for the account, shown by default.

| Method and path | What it does |
|---|---|
| `PATCH /user/member-list` | Body `{"show_in_member_list": false}`. Answers 200 with the current user, who holds `show_in_member_list`; 400 for a missing value or a value that is not a boolean. Ability `user:write` |

`GET /communities/{community}/members` (with or without `query`, with each `sort`) leaves out the members who chose to hide, except the owner, the admins, the moderators and the caller, and its `totalCount` does not count them. The owner, the admins and the moderators with the manage members right get every member. Each entry holds `in_member_list`: whether the other members find the person in the list. It is `false` only on the entry of a hidden member, which only these managers and the hidden member themself get. The member page, a typed mention, the online members and the member count of a community do not change.

## Entitlements of the sign-in provider

When the instance reads the entitlements of its sign-in provider ([ADR 0047](../adr/0047-entitlement-rules-feed-access-groups.md), #1386, `OIDC_ENTITLEMENTS_URL`), an owner or an admin of a community that the operator lists can give an access group by entitlement keys. Without the feature, and for a community that is not listed, these routes answer 404.

| Method and path | What it does |
|---|---|
| `GET /communities/{community}/access-groups/{group}/rules` | The keys that give the group (`entitlement_keys`, sorted). Owner and admins. Ability `groups:read` |
| `PUT /communities/{community}/access-groups/{group}/rules` | Replace the keys: body `{"entitlement_keys": ["example.paid"]}`. A key holds letters, digits, dots, dashes and underscores, 100 characters at most, 20 keys at most, no duplicate. An empty list removes every rule. Owner and admins. Ability `groups:write` |
| `GET /user/entitlements` | Your own keys (`entitlements`) and the date of the last check (`checked_at`). Ability `entitlements:read`, not in the default set |

A rule that is added, changed or removed runs the sync for the members of the community in the background. The list of access groups carries `entitlement_keys` for those who manage the groups, and `GET /communities/{community}/access-groups/{group}/members` returns `sources` for each member: `manual` (added by hand or by the API), `by_rule` (an entitlement rule gives the group), or both. `DELETE .../members/{member}` clears `manual` only: a member whom a rule gives the group stays in it (the answer is 204 either way, and the member then has `sources` `["by_rule"]`).

The change call of the provider, `POST /webhooks/oidc/entitlements`, is not part of the API: it is public, takes no token and no session, accepts only `Content-Type: application/jwt` and is proven by the signature of the provider. It answers 202 (also for a repeated call and for a person that this instance does not know), 401 with an empty body for any invalid call, 404 without the feature and 429 over the limit.

## Public spaces

A public space (`visibility` public) can be read by anyone on the internet, without an account ([ADR 0044](../adr/0044-public-spaces-for-visitors.md)). The `GET` routes below answer a request with no session and no token, each with the gate of its space, and are limited per IP address (60 requests a minute; a signed-in request is not limited). A chat space is never readable without an account, also when it is public. In any other space they answer 401 without an account, and 403 to a signed-in person who may not read it.

| Route                                                                  | What it returns                              |
| ---------------------------------------------------------------------- | -------------------------------------------- |
| `GET /v1/communities/{community}`                                      | The community, in its public shape (see below) |
| `GET /v1/communities/{community}/space-groups`                         | The navigation: the public spaces, without chat |
| `GET /v1/communities/{community}/newest-posts`                         | The 10 newest posts of the public discussion spaces |
| `GET /v1/communities/{community}/spaces/{space}`                       | The space                                    |
| `GET /v1/communities/{community}/spaces/{space}/posts`                 | The posts of a discussion space              |
| `GET /v1/communities/{community}/spaces/{space}/posts/{post}`          | One post                                     |
| `GET /v1/communities/{community}/spaces/{space}/posts/{post}/comments` | The comments of a post                       |
| `GET /v1/communities/{community}/spaces/{space}/pages`                 | The titles of the pages or lessons           |
| `GET /v1/communities/{community}/spaces/{space}/pages/{page}`          | One page or lesson                           |
| `GET /v1/communities/{community}/spaces/{space}/materials`             | The list of the materials of a course        |
| `GET /v1/communities/{community}/spaces/{space}/resources`             | The resources of a resources space           |
| `GET /v1/communities/{community}/spaces/{space}/resources/{resource}`  | One resource                                 |
| `GET /v1/communities/{community}/spaces/{space}/resources/{resource}/comments` | The comments of a resource           |
| `GET /v1/communities/{community}/events`                               | The upcoming events of the public events spaces (#1513) |
| `GET /v1/communities/{community}/spaces/{space}/events`                | The events of an events space, never a draft |
| `GET /v1/communities/{community}/spaces/{space}/events/{event}`        | One event, never a draft                     |
| `GET /v1/communities/{community}/spaces/{space}/events/{event}/comments` | The comments of an event, never of a draft (#1516) |

The first three routes are the public page of a community (#1485). They answer a visitor and a signed-in person who is not a member when the community has at least one public space that is not a chat space. Otherwise a visitor gets 404 (the same answer as for a community that does not exist) and a signed-in person who is not a member gets 403. The community has the public shape only: `cuid`, `name`, `slug`, `description`, `visibility`, `join_policy` (`invitation` or `open`, #1486) and `is_member` (false). The navigation holds the public spaces without any chat space. `newest-posts` has, for each post: `cuid`, `title`, `slug`, `excerpt`, `author` (`cuid`, `name`, `username`, `avatar_url`, or null for a deleted account), `space` (`cuid`, `slug`, `name`, `emoji`) and `created_at`. It never lists a deleted post, a post of a space that is not public, or one of a chat space. A member gets their full community and their own navigation, and the same newest posts.

Without an account, these answers hold the name, the username and the avatar of the authors, and the counts of the reactions. They never hold: a member list, the team, the online members, profile fields, the member count, who reacted, the names of access groups, the moderator rights, the direct message setting, the captured article of a resource, the progress in a course, the results of a quiz, or the meeting address and the place of an event (`meeting_url` and `place` are null, `can_view_location` is false). Everything else needs an account: who reacted, chat messages, online members, member search, quizzes, the download of a course material (the list is public, the download is not), the navigation of the community beyond its public spaces, and every write.

A request with a token still needs the ability of the route (`posts:read`, `pages:read`, `resources:read`, `events:read`, `communities:read`). A token that is not accepted (unknown, revoked or expired) gets a 401: it is never treated as no token.

## Events

An events space (`type` events, [ADR 0053](../adr/0053-events-a-space-type-with-its-own-table.md), #1513) holds dated events. The posting policy of the space decides who creates one; the owner, the admins and the host change it; the moderators of the space also cancel and delete it.

| Method and path | What it does |
|---|---|
| `GET .../spaces/{space}/events` | The list: `scope` `upcoming` (default: the events that end now or later, the drafts first, then the next start first) or `past` (the newest start first), `limit`, `offset`. A draft only for its host, the owner and the admins |
| `POST .../spaces/{space}/events` | Create (201): a draft by default, or `state` `published` |
| `GET .../spaces/{space}/events/{event}` | One event with `body` and `body_html` |
| `PATCH .../spaces/{space}/events/{event}` | Change any field but the state; after the event, `replay_url` and `notes` (#1516) |
| `PUT .../spaces/{space}/events/{event}/state` | `state` (`draft`, `published`, `cancelled`) and `notify_now` |
| `POST .../spaces/{space}/events/{event}/duplicate` | A draft copy 7 days later at the same wall-clock time in the zone of the event (201) |
| `DELETE .../spaces/{space}/events/{event}` | Delete (204), with its answers and comments; 422 "Cancel it first, so members are told." for a published event that is not over and that members answered Yes or Replay only (#1516) |
| `GET /communities/{community}/events` | The upcoming events of every events space that you can read, never a draft |
| `GET .../spaces/{space}/events/{event}/answer` | Your answer: `answer` (`yes`, `replay`, `no`, or null) and `updated_at` (#1515) |
| `PUT .../spaces/{space}/events/{event}/answer` | Set your answer: `answer`. It replaces the one that you gave before |
| `DELETE .../spaces/{space}/events/{event}/answer` | Clear your answer (204, also when you did not answer) |
| `GET .../spaces/{space}/events/{event}/answers` | The members who answered, for the host, the owner and the admins: `answer` (`yes`, the default, or `replay`), `limit`, `offset`; `metadata.counts` holds the numbers of Yes, Replay only and No |
| `GET .../spaces/{space}/events/{event}/calendar-file` | The calendar file of the event (`text/calendar`, an attachment named `<slug>-<date>.ics`), for a member (#1515) |
| `GET .../spaces/{space}/events/{event}/comments` | The comments of the event, oldest first, each with its replies (#1516). A public read in a public space |
| `POST .../spaces/{space}/events/{event}/comments` | Comment the event, or reply (`parent_cuid`): the members who can see it, never on a draft (201) |
| `PATCH`, `DELETE .../spaces/{space}/events/{event}/comments/{comment}` | Edit (the author) or delete (the author or a moderator, `delete_replies` for a moderator) a comment of the event |
| `GET`, `POST .../spaces/{space}/events/{event}/comments/{comment}/reactions` | Who reacted to a comment of the event, and add or remove your reaction. The event itself takes no reaction |
| `GET /user/events` | "My events": the events that you answered Yes or Replay only and the events that you host, in all your communities: `scope` (`upcoming`, the default, or `past`), `answer` (`yes`, `replay` or `host`; default: all of them), `limit`, `offset` |

Times: `starts_at` and `ends_at` are sent in ISO 8601 with the offset of `time_zone` at that time (`2026-11-18T17:00:00+01:00`) and come back as UTC instants (`2026-11-18T16:00:00.000000Z`) with `time_zone` as stored. A value without an offset, a wrong offset and a time that the zone skips (the hour when the clocks go forward) are field errors (400); a time that exists twice is accepted with either real offset. The end is after the start and at most 24 hours later. A start in the past is for the owner and the admins only. `is_past` is true when the end is before now; it is never stored.

The meeting address and the place: `location_visibility` `members` gives them to every member who can see the event, `going` to the members who answered Yes, the host, the owner and the admins. A visitor and a person who is not a member never get them. The server cuts them; the client never hides them.

The first publication of an event with a start in the future tells the members of the space at "Everything" (the built-in default of an events space) with one `event_new` line each, after 5 minutes (`notify_now: true` skips the wait, for the moderators of the space). Going back to draft deletes these lines; a second publication writes none. Live updates on the space channel: `space.event.created` (at the first publication), `space.event.updated` (a change of a published or cancelled event) and `space.event.deleted` (a delete, or a return to draft), with `event_cuid` and `author_cuid` only. A draft sends nothing. A signed-in person who may not see an event (a draft of another host, or an event of a space that the person cannot read) gets 404 from every route of the event, the read included, with the body of an event that does not exist. A visitor gets 401. Create and duplicate are limited per member (the `events` limiter, 5 a minute).

Answers (#1515): a member of the community who can see an event answers it with `yes`, `no`, or `replay` ("Replay only", accepted only when the event has `replay_expected` true), while the event is published and not over. Only the member writes their own answer, and nobody is told. The refusals come in this order: 404 for an event that the person may not see, 403 for a person who is not a member, 422 for a cancelled event ("This event is cancelled. It takes no answers."), for an event that ended ("This event is over. It takes no answers.") and for a draft, then 400 for a wrong field. A cancelled event and an event that ended keep their answers as they are: no change and no clear. `capacity` (1 to 5 000, or null) limits the Yes answers: a new Yes on a full event answers 422, "This event is full.", the same Yes again answers 200, and a member who leaves a Yes frees a place (there is no waiting list). An answer counts only while its member can read the space of the event: the answer of a member who lost the access stays, takes no place and is in no count, and counts again when the access is back. The event may then be over its limit: nobody is removed, and `places_left` is 0. A published event that a member who can read the space answered does not go back to draft (422, "Members answered this event. Cancel it instead.").

What an event tells about the answers: `my_answer` (your own answer, or null), `can_answer`, `replay_expected`, `places_left` (the places that are left, for a member, when the event has a limit) and `capacity` (the limit, for the host, the owner and the admins only; with a token it also needs the ability `event-answers:read`, and a token without it reads `places_left` as a member does and `capacity` null). No route gives a member the answer of another member, a name or a count. The list of `GET .../answers` names the members who answered Yes or Replay only, by name then by username, never the members who answered No, and holds only the people who are members today and can read the space. Answers and clears are limited per member (the `event-answers` limiter, 30 a minute). The live update `space.event.answers.changed` carries `event_cuid` only and is sent only when the number of Yes answers of an event with a limit changed.

Comments (#1516, [ADR 0053](../adr/0053-events-a-space-type-with-its-own-table.md), "Comments and after the event"): the comments of an event are the comments of posts and resources, with replies on one level, mentions, reactions on the comments and the same delete rules. The members of the community who can see the event comment it, also when it is cancelled or over; a draft takes none ("This event is a draft. It takes no comments.", 422), and a published event with a comment does not go back to draft (422, "Members commented on this event. Cancel it instead."). The refusals come in this order: 401 without an account on a write, 404 for an event that you may not see (also for your own comment, when you lost the access to the space), 403 for a person who is not a member, 422 for a draft. Editing or deleting a comment needs the access to its space. A first-level comment tells the host (the notification `event_comment`), a reply tells the author of the comment, and a mention the mentioned member. Abilities `events:read` and `events:write`; the limiters `comments` and `reactions`.

After the event (#1516): the owner, the admins and the host add `replay_url` (an absolute http or https address, up to 2048 characters) and `notes` (Markdown, up to 20 000 characters) with `PATCH .../events/{event}`, only on a published event whose end passed: else a field error (400), "Add the replay once the event is published and over." or "Add the notes once the event is published and over.". Null or an empty text clears them at any time. A published event that did not start yet answers 422 to an end in the past: "This event did not happen yet. Cancel it instead." An event that is over and holds a replay link or notes, in any state, answers 422 to a move that makes it not over: "Remove the replay link and the notes before you move this event to a later time." (the same request may remove them). A create and a duplicate ignore them, and they do not change `sequence` or the calendar file. Every event carries `can_view_replay`, `replay_url` and `replay` (the click-to-load player of a lesson, `{provider, id, url, embed_url}`, for a link of one of its two video hosts; null for any other address): only the members of the community who can see the event get them, whatever `location_visibility` says, never a visitor or a person who is not a member (false, null and null). `notes` and `notes_html` come with the description (`GET .../events/{event}` and the event page), for every reader of the event. The server never opens the replay link.

The calendar file (#1515, [ADR 0053](../adr/0053-events-a-space-type-with-its-own-table.md), "The calendar file and My events"): one iCalendar file (RFC 5545) per event, built at each request, with one `VEVENT` in UTC (`UID` the cuid of the event, `SEQUENCE` higher after a move, `STATUS:CANCELLED` and "Cancelled: " before the title for a cancelled event). The meeting address and the place are in it only when you may see them, as in `GET .../events/{event}`; the file names no member, and nothing records who downloads it. The refusals: 401 without an account, 404 for an event that you may not see, 403 for a person who is not a member, 422 for a draft ("This event is a draft. It has no calendar file."), 429 after 30 files a minute (the `calendar-files` limiter); each is the JSON error of the API, also when a browser asks. Ability `events:read`. A browser gets the file only through the link "Add to calendar" of a page (a click, also "open in a new tab"): that request sends the address of the page as the `Referer`, and the session of the browser counts on the API only with it. A typed address, a pasted address or a bookmark gets 401, also when you are signed in. A script with `events:read` reads the file of each event with a token, but a calendar application cannot subscribe to it: the file needs your session or a token, one event at a time.

"My events" (`GET /user/events`): only the events of the spaces that you can read today as a member, never an event of a community that you left. A draft only when you host it. Each event carries its `space` and its `community` (`cuid`, `slug`, `name`) and the fields of `GET .../events/{event}` without the description. Ability `events:read`.

## Member list

`GET /v1/communities/{community}/members` gives the members of a community with their role, to its members ([ADR 0056](../adr/0056-the-member-directory-every-member-reads-the-member-list.md), #148). It needs an account and the ability `communities:read`. The member directory of a community (`/community/{slug}/members`), the member management of the settings and the member fields read it.

| Parameter | What it does |
| --------- | ------------ |
| `sort` | The order. `role` (the default, also for any other value): the owner, the admins, the moderators, the members, each by username. `name`: the name from A to Z without the case, then the username. `newest`: the date of joining, the newest first, then the username; the owner joined at the date of the community |
| `query` | Keeps the members whose username, or a word of their name, starts with the text. It reads the username and the name only. The server trims it and keeps its first 100 characters. With `role`, the usernames that start with the text come first |
| `limit`, `offset` | Offset pagination: 10 by default, 100 at most |

An entry holds `cuid`, `name`, `username`, `avatar_url`, `headline`, `role` and `member_since`. The headline is the only profile field: empty unless the member wrote one and shows it ([ADR 0020](../adr/0020-members-choose-visible-profile-fields.md)). An entry never holds an email address. The list holds the owner and the people with a membership, and nobody else. It is limited to 60 requests a minute for each account, together with the page of the directory: the next request answers 429.

## Invitations

`GET /v1/communities/{community}/invitations` gives the pending invitations, newest first, with `limit` (10 by default, 1000 at most) and `offset`, and `metadata.pagination` (#1390). `POST` invites an email address with a role. Both need an account, the abilities `communities:read` and `communities:write`, and the owner, an admin or a moderator with `manage_members`; the role is lower than the role of the caller. A personal community invites nobody (422).

An account, a community and the instance send at most 1000 invitation mails a day (`MAIL_INVITATIONS_PER_ACCOUNT_PER_DAY`, `MAIL_INVITATIONS_PER_COMMUNITY_PER_DAY` and `MAIL_INVITATIONS_PER_INSTANCE_PER_DAY` on a self-hosted instance): every invitation mail counts, a single invitation, a row of an import and a row of a send again, over the last 24 hours, the cap of an account covers all its communities (the account of the caller), and the cap of the instance covers all its communities and accounts. The message of a refusal of the cap of the instance shows no number of the instance. A single invitation past a cap is a 422 that says from when it works again; nothing is created. While the instance holds its mail (a hold of the mail budget), a single invitation is a 422 with the end of the hold: a single invitation waits for the end of a hold of the mail budget.

An invitation can carry access groups and member tags that the person gets when they accept it ([ADR 0061](../adr/0061-invitations-carry-access-groups-and-tags-and-come-from-a-file.md)): the optional fields `access_groups` and `member_tags` of the create, lists of cuids of the same community (50 and 20 at most, a duplicate counts once; a list as it comes in holds at most 100 and 40 items). A cuid of another community or of nothing is a 400 on its field. A group or a tag deleted while the invitation is made is left out. Groups need the right to manage the members of access groups, and `groups:write` for a token; tags need the right to assign member tags, and `member-tags:write` for a token (403 "You cannot give access groups", "You cannot give member tags"). An empty or missing list needs nothing. The refusals come in this order: `email` and `role` (400), the role (403), a member (400), the groups and the tags, then the invitation that the new one replaces: a new invitation of the same address replaces the old one only when the caller may give the role of the old one (403 "This address has an invitation with a role that you cannot give"); an invitation that expired is replaced by any manager. Each invitation of the list holds `access_groups` (`cuid`, `name`) and `member_tags` (the tag), by name: an empty list for a caller who may not see them. A membership that comes from an invitation is by hand (`manual`). The mail names no group and no tag. The signed link of the mail has no page: its `GET` joins the community with the account of the invited address and redirects to the community, and its banner names no group and no tag.

## Invitations from a file

An owner, an admin or a moderator with `manage_members` invites many people with a CSV file ([ADR 0061](../adr/0061-invitations-carry-access-groups-and-tags-and-come-from-a-file.md), #1390). The file is UTF-8 with a header row: `email` (required), `name` (read and not stored), `role`, `access_groups`, `tags` (names separated by `|`); a comma or a semicolon delimits the cells. At most 1000 rows, 1 MB, 20 columns, 1000 characters per cell. The server reads the file one time and does not keep it.

| Route | What it does |
| ----- | ------------ |
| `POST /v1/communities/{community}/invitation-imports` | Multipart: `file`, `default_role`, `access_groups[]`, `member_tags[]` (the defaults of every row). 201 with the import in `preview`. A new upload deletes the older preview of the caller. An error of the file is a 400 on `file` |
| `GET /v1/communities/{community}/invitation-imports` | The imports, newest first, with `limit` and `offset` |
| `GET /v1/communities/{community}/invitation-imports/{import}` | One import: `state`, the defaults, `ignored_columns`, `counts` (`rows`, `valid`, `refused`, `queued`, `sent`, `held`, `failed`, `skipped`, `still_invited`, `not_sent`: the rows that `not_sent` of the send again takes), `started_by`, the dates, `can_resend_not_joined_from`, `kept_until` |
| `GET /v1/communities/{community}/invitation-imports/{import}/rows` | The rows by number: `email`, `role`, the names, `outcome`, `reason`, `reason_text`, `detail`, `still_invited`, `sent_at`. `outcome` filters (comma separated); `limit` 10 by default, 1000 at most |
| `POST /v1/communities/{community}/invitation-imports/{import}/confirm` | Send a preview: the uploader only (403). 202. 422 when it is sent already, when the instance holds its mail, cannot send so many mails this month, or when the rows pass a daily cap of the invitation mails (of the community or of the caller, with how many can go now; of the instance, with no number): the whole import is refused and nothing changes |
| `POST /v1/communities/{community}/invitation-imports/{import}/resend` | `rows`: `not_sent` (held and failed, and queued for longer than 2 hours 16 minutes, at any time) or `not_joined` (sent, one time, 3 days after the send at the earliest). Needs the role of every row. 202, or 422 ("No row to send", the dates, the checks of the mail and the daily caps of an account, a community and the instance) |
| `DELETE /v1/communities/{community}/invitation-imports/{import}` | A preview: the uploader, the owner, the admins. A sent import: the owner and the admins; its invitations stay; 422 while a row waits for its mail and until 24 hours after the last row went to the queue (the text gives the date). 204 |

The abilities are `invitations:read` and `invitations:write`. A quote opens a quoted cell only as the first character of a cell, and a quote that is still open at the end of the file refuses the file. The upload, the confirm and the send again are limited to 5 a minute per person. A preview older than 24 hours answers 404. A row is refused for one reason, the first of: `extra_cells`, `cell_too_long`, `invalid_email`, `duplicate_in_file` (`detail`: the earlier row), `unknown_role`, `role_not_allowed`, `access_groups_not_allowed`, `unknown_access_group` and `ambiguous_access_group` (`detail`: the name), `tags_not_allowed`, `unknown_tag` (`detail`: the name), `too_many_tags`, `already_member`, `removed_by_manager`, `pending_invitation` (`detail`: the end of the invitation). The confirm checks every valid row again. The mails go out on the mail queue at the pace of the provider; a row whose mail the budget holds is `held`, and "send again" with `not_sent` sends it later. The record of an import is deleted 37 days after its confirm (as an invitation), a preview 24 hours after the upload.

## Invite links

An invite link is an address that the owner and the admins of a community share ([ADR 0062](../adr/0062-invite-links-a-shared-link-to-join-a-community-with-set-access-groups-and-tags.md), #1391). Any signed-in person with a verified address who opens it can join the community as a member, with the access groups and the member tags of the link, in a community by invitation as in an open one. A link has a `name` (2 to 64 characters, for the managers only), `active`, an optional `expires_at` and `max_uses` (1 to 10000), and `status`: `off`, else `ended`, else `full`, else `active`. Only `active` lets a person join. `url` is the address to share, with its token; only the managers get it. A community has at most 50 links. The groups and the tags follow the rules of an invitation (cuids of the community, 50 and 20 at most; `groups:write` and `member-tags:write` for a token), and a group that a rule on an entitlement feeds is refused. The refusals of the routes of the managers come in this order: 404 (a link of another community), 403, 400 for a field, then the limit (422).

| Route | What it does |
| ----- | ------------ |
| `GET /v1/communities/{community}/invite-links` | The links, newest first, no pagination. Owner and admins. Ability `invite-links:read` |
| `POST /v1/communities/{community}/invite-links` | Create a link: `name`, `active`, `expires_at` (an ISO 8601 time with its zone, in the future), `max_uses`, `access_groups`, `member_tags`. 201 with the link and its `url`. Ability `invite-links:write` |
| `GET /v1/communities/{community}/invite-links/{inviteLink}` | One link. Ability `invite-links:read` |
| `PATCH /v1/communities/{community}/invite-links/{inviteLink}` | Change the fields of the request; the lists replace the old ones, `null` clears the end and the maximum. A maximum at or below the count makes the link full. Ability `invite-links:write` |
| `DELETE /v1/communities/{community}/invite-links/{inviteLink}` | Delete the link: it stops at once, the list of who joined goes, the members stay with their groups and tags. 204. Ability `invite-links:write` |
| `GET /v1/communities/{community}/invite-links/{inviteLink}/uses` | Who joined through the link, newest first: `member` (an author) and `joined_at`, with `limit` and `offset`. Ability `invite-links:read` |
| `GET /v1/invite-links/{token}` | For the person who has the link: `community` (its public shape, or the shape of a member for a member) and `state` (`join`, `member` or `blocked`). 20 reads a minute per person. Ability `communities:read` |
| `POST /v1/invite-links/{token}/membership` | Join through the link. 201 with the community as a member sees it. 422 for a member ("You are already a member of this community") and for a person whom a manager removed (the text of the block): nothing changes for them. Limited with the joins of open communities. Ability `communities:write` |

A link that does not work (an unknown token, a link that is off, ended, full or deleted, a link of a personal community) gives the same 404 on both routes of the person: "This invite link does not work. Ask the person who gave it to you for a new one." The answers of the person never hold the name of the link, its groups, its tags or its counts. A join is recorded (the link, the member, the date) for the managers, and the record goes when the member leaves, with the link, with the account, or 365 days after the join (`RETENTION_INVITE_LINK_USES_DAYS`).

## Who can join

A shared community is by invitation (`join_policy` `invitation`, the default) or open (`open`) ([ADR 0044](../adr/0044-public-spaces-for-visitors.md), #1486). Read `join_policy` on `GET /v1/communities/{community}`, for a member and for someone who is not one.

| Route | What it does |
| ----- | ------------ |
| `PATCH /v1/communities/{community}/join-policy` | Change who can join (owner and admins). Body: `join_policy`. Never for a personal community (400) |
| `POST /v1/communities/{community}/membership` | Join an open community by yourself, as a member. 201 with the community as a member sees it. 422 with a message when the community is by invitation, you are a member, or a manager removed you ("You cannot join this community by yourself. Ask its managers for an invitation."). Needs a verified address (403 without). Limited to 10 a minute per person |
| `GET /v1/communities/{community}/join-blocks` | The people whom a manager removed (owner, admins, moderators with `manage_members`) |
| `DELETE /v1/communities/{community}/join-blocks/{member}` | "May join again": the person can join by themselves again (same managers). 204, or 404 without a block |

All four need an account and the ability `communities:write` (`communities:read` for the list).

## Indexing

A shared community lets search engines index its public spaces, or not ([ADR 0044](../adr/0044-public-spaces-for-visitors.md), "Indexing", #1487). It is off by default. Members read `indexing_enabled` on `GET /v1/communities/{community}`; it is not part of the public shape.

| Route | What it does |
| ----- | ------------ |
| `PATCH /v1/communities/{community}/indexing` | Let search engines index the public spaces, or not (owner and admins). Body: `indexing_enabled` (a boolean). Never for a personal community (400). Needs an account and the ability `communities:write` |

The setting only matters on an instance that allows indexing (`ROBOTS_ALLOW_INDEXING`). The decision for each page is in its `X-Robots-Tag` header and its meta tag. The API itself is never indexed.

## Notifications, follows and levels

The notifications of the current member, in every community ([ADR 0042](../adr/0042-notifications.md), #1474, #1475). Only a member of the community who can view a space has follows and levels there: a visitor and a signed-in person who is not a member get 403. A notification of another member gives 404. None of these abilities is in the default set of a token.

| Route | What it does |
| ----- | ------------ |
| `GET /v1/notifications` | The list. `level=1` (default) is "Needs you", `level=2` is "Following" (the last 14 days, no `older`), `state` (open, read, all), `community`, `older`. Each notification has `reason` (`wrote_post`, `wrote_comment`, `mentioned`, `follows_thread`, `space_everything`, `started_course`), `follow_reason` (`wrote`, `replied`, `manual`) and `items_count`. Needs `notifications:read` |
| `GET /v1/notifications/count` | The number of the bell (`data.count`). It never counts "Following". `data.messages` is the number of conversations with new messages, in every community, for the title of the browser tab |
| `POST /v1/notifications/seen`, `PUT /v1/notifications/{notification}/read`, `POST /v1/notifications/{notification}/unread` | Seen, read, undo read. Need `notifications:write` |
| `POST /v1/notifications/caught-up` | "I am caught up": every open "Following" notification is read, in every community or in the one that the optional `community` (cuid) names. It never touches "Needs you". With a session, it also forgets the date of the "Catch up" card. Answers `data.marked` |
| `GET`, `PUT`, `DELETE /v1/communities/{community}/spaces/{space}/posts/{post}/follow` | The state of the thread for the member (`state`: `following`, `muted`, `stopped` or null; `reason`: `wrote`, `replied`, `manual`), "Follow", "Stop following" (the state becomes `stopped`: writing again does not follow the thread again) |
| `PUT`, `DELETE /v1/communities/{community}/spaces/{space}/posts/{post}/mute` | "Mute this thread": nothing reaches the member from the thread, a mention included, and their open notifications about it are deleted. `DELETE` takes the mute back (#1509): the thread gets back the state and the reason that it had before the first mute (no row at all for a thread that had none: `state` and `reason` are null). The notifications that the mute deleted do not come back. A thread that is not muted does not change: the answer is its current state |
| `GET`, `PUT`, `DELETE /v1/communities/{community}/spaces/{space}/messages/{message}/follow`, `PUT`, `DELETE .../messages/{message}/mute` | The same for a chat thread (a first-level message; a reply has no thread: 422) |
| `GET /v1/communities/{community}/notification-levels` | The levels of the member for every space of the community that they can view, in one call, for a settings screen |
| `GET`, `PUT`, `DELETE /v1/communities/{community}/spaces/{space}/notification-level` | The level for one space (`level`: `all` "Everything", `mine` "Only what is for me", `none` "Muted"; `chosen`; `default`), choose it (`level`), go back to the default. Muted gives nothing from the space, a mention included |
| `GET /v1/communities/{community}/catch-up?since=YYYY-MM-DD` | The data of the "Catch up" card ([ADR 0048](../adr/0048-where-i-am.md), #1476): at most 5 lines (up to 2 `needs_you`, up to 2 `announcement`, 1 `thread`), each with `kind`, `title`, `excerpt`, `space`, `author`, `date` and `target`, and `has_more`. `since` is required, not in the future, at most 366 days back. Members only. Needs `notifications:read` |
| `PUT /v1/communities/{community}/spaces/{space}/visit` | The member visited the space: its dot in the navigation goes. Members who can view the space only (403 otherwise). Needs `notifications:write` |
| `DELETE /v1/user` | Delete your own account (#1492, [ADR 0043](../adr/0043-delete-content-with-the-account.md)): body `password` (required when the account has one), `delete_content` (boolean, also deletes your posts, comments and messages) and `code` (the code of the authenticator app or a recovery code, required with two-factor authentication; a recovery code is consumed). An account without a password ([ADR 0052](../adr/0052-account-deletion-after-a-new-sign-in.md)) sends no password: it first confirms with a new sign-in at the provider in a browser ("Confirm with ..." in the deletion section of the profile settings), then sends this request within the time that the page shows (5 minutes by default); the confirmation is used once. 204 and every token of the account is gone. 400 for a wrong or missing `password` or `code`, 422 for an account without a password and without a valid confirmation (the message and `metadata.sign_in_url` give the address of the page), or without a connected sign-in account (write to the operator), 429 after 3 requests a minute of one token, and after 5 wrong passwords in 15 minutes of the tokens of the account together (`Retry-After`, [ADR 0067](../adr/0067-the-tries-of-the-current-password-count-for-what-signed-the-request-in.md)). Needs `account:delete`, not in the default set |
| `PUT /v1/user/time-zone` | Set the time zone of the account (`time_zone`, an IANA name; an unknown name is a validation error). Only the member sets and reads it. Needs `user:write` |
| `POST /v1/user/email-change/resend`, `DELETE /v1/user/email-change` | A change of the email address applies after the new address is confirmed (#1706, [ADR 0058](../adr/0058-a-change-of-the-email-address-applies-after-its-confirmation.md)): the profile form asks for it, the account keeps its address, and the link of a mail to the new address applies it. `GET /v1/user` shows `pending_email` and `pending_email_requested_at` (null when no change waits). `POST .../resend` sends the link again (no password; a validation error when no change waits), `DELETE` cancels the change. Both return the current user. 429 after 6 calls a minute. Needs `user:write` |
| `PUT /v1/user/notification-settings` | The switch "Show pop-ups" (`popups`, boolean). Needs `notifications:write` |
| `GET /v1/notifications/email-status` | The email status of the member (#1478, [ADR 0054](../adr/0054-email-delivery-the-mail-queue-the-budget-bounces-and-the-one-click-stop.md)): `suppressed`, `reason` (`hard_bounce`, `complaint`, `soft_bounces`, `provider_list`, or null), `since`, `address_hint` (the address of the account) and `email_choices` (the five notification email groups, each with `state` `default` or `off`). 1 or 2 soft bounces show as not suppressed. Own state only. Needs `notifications:read` |
| `DELETE /v1/notifications/email-suppression` | "Try this address again": the notification emails to the address go again (a complaint too), and the mail provider is asked, best effort, to take the address off its list. Once an hour per account (429). Answers with the email status. Needs `notifications:write` |

The posts and the first-level chat messages carry `follow_state` (`following`, `muted`, `stopped`, or null). The space carries `default_notification_level` (`all`, `mine` or null) for the people who manage the spaces only; they set it with `PATCH /v1/communities/{community}/spaces/{space}` (`all` for a discussion space only, never a chat space; `none` is not allowed). `POST .../posts` takes `notify_now` (a boolean) to skip the 5 minutes before the members at "Everything" are told, for the people who can moderate the space (403 for the others).

## Delete a post or a comment

A delete by the author keeps what other people wrote (#1565, [ADR 0050](../adr/0050-a-delete-keeps-the-replies-of-other-people.md)). "Other people" are the authors other than the person who deletes; a comment of a deleted account counts, a marker does not.

| Route | What it does |
| ----- | ------------ |
| `DELETE /v1/communities/{community}/spaces/{space}/posts/{post}/comments/{comment}` | The author or a moderator deletes a comment. With replies of other people, the comment stays as a marker (no text, no author) with every reply: 200 and a message. Otherwise it goes with its replies: 204. Body `delete_replies` (a boolean, optional): true deletes the comment and every reply under it, a marker too, for the moderators of the space only (403 for the others). A marker is not deleted again without it (403). 400 for a value that is not a boolean. Needs `posts:write` |
| `DELETE /v1/communities/{community}/spaces/{space}/resources/{resource}/comments/{comment}` | The same for a comment of a resource. Needs `resources:write` |
| `DELETE /v1/communities/{community}/spaces/{space}/events/{event}/comments/{comment}` | The same for a comment of an event (#1516). Needs `events:write` |
| `DELETE /v1/communities/{community}/spaces/{space}/posts/{post}` | The author or a moderator deletes a post. With comments of other people, the post stays as a marker (no title, no text, no author, the slug `deleted-{cuid}`, not pinned) with every comment: 200 and a message. The lists skip it; `GET .../posts/{post}` returns it with `deleted_at`. Otherwise it goes with its comments: 204. Body `delete_comments` (a boolean, optional): true deletes the post and its whole discussion, for the moderators of the space only (403 for the others). A marker is not deleted again without it (403). 400 for a value that is not a boolean. Needs `posts:write` |

A marker whose last reply goes stays, like a deleted chat message.

## Keep it current

Each pull request that adds or changes an endpoint adds or updates the requests here, in the same pull request:

1. File name: `<Area> - <Operation>.bru`, and `<Area> - <Operation> - <Case>.bru` for an error case. Example: `Spaces - Create.bru`, `Spaces - Create - Invalid type.bru`.
2. One request for the success case, and one for each main failure (validation, permission) when it shows something the success case does not.
3. Bodies use the field names of the API and realistic values. The fields are snake_case. A few older request fields are camelCase (`turnstileToken`, `includeToken`), see [ADR 0011](../adr/0011-rest-api-design-guide.md).
4. Never commit a secret: credentials come from `.env` through `{{process.env.*}}`.

`tests/Feature/BrunoCollectionTest.php` fails when an `/api/v1` route has no request here, or when a request targets a route that does not exist.
