# Events: the release test

The release test of the [events design](design.md), section 11.6. It belongs to each slice of events: a slice is done when the automated part is green, the keyboard script works without a pointer, and a person did the manual part. This file covers slice E1 (the server part #1513 and the screens #1514, [ADR 0053](../../adr/0053-events-a-space-type-with-its-own-table.md)). The later slices add their steps here (the answers and "Add to calendar" in E2, the comments and the replay in E3).

Pass: no automated violation, and every step of the keyboard script works without a pointer.

## 1. Automated: axe in the Vitest specs

`npm run test:all` runs them. Each spec runs `axe-core` on the rendered screen and expects no violation. Two rules are off, by name: `color-contrast` (jsdom computes no colors: the contrast is checked in the browser) and `region` (a spec renders a part of a page, without the landmarks of the layout).

| Screen                | Spec                                                       | States that the check covers                                                                                              |
| --------------------- | ---------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------- |
| The list              | `apps/knowii/src/Components/Events/EventList.spec.tsx`     | A member and a visitor with a draft, a cancelled event, a cover and "Load more"; an empty list                            |
| The event page        | `apps/knowii/src/Pages/Events/Show.spec.tsx`               | The host of a draft, a member of a cancelled event, a visitor, an outsider of a past event; the menu "Event actions" open |
| The form              | `apps/knowii/src/Components/Events/EventForm.spec.tsx`     | A new event for a moderator; the errors and the question about the clocks; a published event with a cover                 |
| "Next events"         | `apps/knowii/src/Components/Events/NextEvents.spec.tsx`    | A cancelled event and a published event, with the name of the space                                                       |
| The toolkit blocks    | `apps/knowii/src/Components/ui/stories.spec.tsx`           | Every story of `Badge`, `LinkTabs`, `CoverImage` and `Combobox`, with its keyboard path                                   |
| The props of a reader | `tests/Feature/EventPagesTest.php`, `PublicSpacesTest.php` | No meeting address and no place on any screen for a visitor, an outsider and a member of a `going` event                  |
| The times             | `libs/common/src/lib/utils/time-zone.fn.spec.ts`           | The same instant in three zones, a skipped time, a time that exists twice, an end on the next day, the weekly office hour |

## 2. The keyboard script

Do it with the keyboard only, in a desktop browser, signed in as a person who may create an event in an events space (the owner, an admin, or a moderator when only the team creates). Start on the page of the events space. After each step, the focus must be visible, and the page must say what happened.

A native date field takes its parts in the order of the language of the browser (day, month, year, or month, day, year): type the digits in the order that the field shows.

| Step | Keys                                                                                                                                | What must happen                                                                                                                                                                                                             |
| ---- | ----------------------------------------------------------------------------------------------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| 1    | Tab to "New event", Enter                                                                                                           | The form opens, with the heading "New event"                                                                                                                                                                                 |
| 2    | Tab to "Title", type a title. Tab to "Start date", type a date. Tab to "Start time", type 17:00. Tab to "End time", type 18:00. Tab | When the focus leaves "End time", a status under the fields says the date in words, "17:00 to 18:00", the zone in words with its offset, and "In your zone: the same." It does not change while you type                     |
| 3    | In "Time zone", type "new york", Down arrow, Enter. Then type "brussels", Down arrow, Enter                                         | The list shows the zones that match, and a status says the number of results. After the choice the focus stays in the field, and the status of "When" gives the time in the zone of the event and "In your zone:" with yours |
| 4    | Tab to "Meeting address", type an address that starts with https://. Tab to "Save as draft", Enter                                  | The page of the event opens with "Draft. Only the host, the owner and the admins see it." A toast says "Draft saved"                                                                                                         |
| 5    | Tab to "Publish", Enter                                                                                                             | The draft notice goes. The focus is on the button "Event actions"                                                                                                                                                            |
| 6    | Shift+Tab to "Edit", Enter. Tab to "Title", change it. Tab to "Save changes", Enter                                                 | The page of the event opens with the new title. A toast says "Changes saved"                                                                                                                                                 |
| 7    | Tab to "Event actions", Enter. Down arrow to "Cancel event", Enter. In the dialog: Escape                                           | The dialog "Cancel this event?" opens with the focus on "Keep the event". Escape closes it, nothing changes, and the focus is on "Event actions" again                                                                       |
| 8    | Enter, Down arrow to "Cancel event", Enter. In the dialog: Tab to "Cancel event", Enter                                             | The banner "Cancelled" shows. "Join the meeting" stays. The focus is on "Event actions"                                                                                                                                      |
| 9    | Enter on "Event actions", Down arrow to "Publish again", Enter                                                                      | The banner goes. The focus is on "Event actions"                                                                                                                                                                             |
| 10   | Enter on "Event actions", Down arrow to "Delete", Enter. In the dialog "Delete this event?": Tab to "Delete", Enter                 | The page of the space opens. The event is not in the list. A toast says "Event deleted"                                                                                                                                      |
| 11   | In a space with more than 20 past events: Tab to "Past", Enter                                                                      | "Past" is the open list (`aria-current="page"`, a line under it). The focus stays on "Past": it does not go back to the top of the page. A status says the list and its count, for example "Past events: 23"                 |
| 12   | Tab to "Load older", Enter                                                                                                          | After the load the focus is on the title of the first new event, and a status says how many loaded, for example "2 more loaded"                                                                                              |
| 13   | Shift+Tab to "Upcoming", Enter                                                                                                      | "Upcoming" is the open list and keeps the focus. A status says "Upcoming events:" with the count, or "No upcoming events"                                                                                                    |

More checks of the form, with the keyboard:

1. A time that the zone skips. Zone Brussels, start 28 March 2027 at 02:30: an alert under "Start time" says "This time does not exist in this zone. Choose 01:59 or earlier, or 03:00 or later." "Save as draft" sends nothing and says so above the buttons.
2. A time that exists twice. Zone Brussels, start 31 October 2027 at 02:30: a group of two radios asks "02:30 in summer time (UTC+2) or in winter time (UTC+1)?", and the status of "When" says "This time exists twice on this day. Choose summer time or winter time below." The arrow keys choose, and the status of "When" then names the zone of the choice.
3. The clocks go forward at midnight. Zone Azores, start 28 March 2027 at 00:30: the alert says "Choose 23:59 the day before or earlier, or 01:00 or later."
4. The host. Tab to "Remove" with the name of the host, Enter: the focus is in the field "Host", which takes text again. Type a name, Down arrow, Enter.

## 3. What a person still does

A script cannot do these. Do them before the release of a slice, and write the result in the pull request.

1. NVDA with a desktop browser. The list: each event is announced as a list item with a heading, the time is read in its long form ("Wednesday 18 November, 11:00 to 12:00 Eastern Standard Time", not the short form), "Draft", "Cancelled" and "Happening now" are read, and "20 more loaded" is read after "Load older". The event page: one heading of level 1, the state, the time of the host when it differs, "Join the meeting, opens in a new tab". The form: each field with its label, the group "When", the status after leaving a time field, an error read when it comes, the question about the clocks as a group of radios, the number of results of the list of zones.
2. VoiceOver on a phone (Safari). The same three screens. The native date and time fields open the wheels of the system and give back a value that the status repeats. The list of zones opens, filters and closes. A swipe reaches every control in the order of the page.
3. Forced colors (the contrast themes of Windows). The line under "Upcoming" or "Past" shows, the labels "Draft" and "Cancelled" keep a border, the banner "Cancelled" keeps a border, the focus ring shows on every control, the placeholder of a cover keeps its box, the active entry of the list of zones has an outline.
4. 400% zoom at a width of 1280 px (the same as 320 px): no sideways scroll, no text cut, the buttons of the form wrap.
5. The contrast of the two themes in the accessibility panel of Storybook for `Badge`, `LinkTabs`, `CoverImage` and `Combobox`.

## 4. Slice E1 (#1514): what was run on 2026-11-19

1. The automated part: green (`npm run test:all` and the Pest suite).
2. The keyboard script: the steps that are now 1 to 12 run by a script that sends only keys, in Chromium at 1920 px, as the owner of a test community. Every step gave the result of the table.
3. The three screens, "Next events" on the home and the public page: loaded in Chromium at 1920 px and at 360 px, in the light and the dark theme, as an owner, a moderator, a member and a visitor. No sideways scroll, one heading of level 1, no error in the console, no meeting address and no place for a visitor, and no violation of `axe-core` (the WCAG 2.2 A and AA rules, contrast included) in the content of the screens. One finding is outside this slice, in the header of every page: the user menu of an account without a picture (`label-content-name-mismatch`, at 1920 px).
4. Not done: section 3 (NVDA, VoiceOver on a phone, forced colors, 400% zoom, the Storybook panel). They wait for a person.

## 5. Slice E1 (#1514), after its review: what was run on 2026-11-20

1. The automated part: green (`npm run test:all` and the Pest suite).
2. In Chromium at 1920 px, with keys only, as the owner: steps 11 and 13 (the focus stays on "Past" and on "Upcoming", and the status says "Past events: 22" and "Upcoming events: 4"), and the host removed with Enter on its "Remove" button (the focus is in the field "Host", which takes text). At 360 px the "Remove" button is 44 px wide and high.
3. Live, with an owner, an admin and a member on the list: after "Back to draft", the owner and the admin keep the card with "Draft" and the member loses it; after the publication, the page of the draft that the admin has open loads again without the draft notice; after a delete, the card goes for the three.
4. The live regions of the form and the alert of the event page: in the accessibility tree of Chromium while they are empty (`display: block`, 1 px), and a first message shows at its normal size.
5. Not done: section 3. It still waits for a person.
