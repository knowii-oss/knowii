# Findings: design

## Scope covered

1. Pictures (atlas of commit `382ded2d`), opened whole or cropped:
   - Dashboard: `dashboard/home--full--member` (1920 dark, 1920 light), `dashboard/home--no-match--member` (1920 dark), `dashboard/main-menu--open--member` and `dashboard/user-menu--open--member` (360 dark), `account/time-zone-question--open--member` (1920 light).
   - Community: `community/home--full--member` (1920 dark, 1920 light, 360 light), `community/home-catch-up--loading--member` and `--error--` (1920 dark, 360 light), `community/navigation--drawer--member` (360 dark), `community/about--full--member` (1920 dark), `community/member-profile--other--member` (1920 light).
   - Discussion: `discussion/space--full--member` (1920 dark, 1920 light, 360 dark), `discussion/space-level--menu--member` (1920 dark), `discussion/post--full--member` (1920 dark, 360 dark), `discussion/post--delete-dialog--member` (1920 light), `discussion/composer--open--member` (360 dark), empty states of every space type (1920 dark).
   - Chat and messages: `chat/space--full--member` (1920 dark, 360 dark), `chat/thread--loading--member` and `--error--` (360 light), `messages/conversation--direct--member` (1920 light), `messages/index--empty--member` (1920 dark).
   - Course, pages, resources: `course/outline--full--member` (1920 dark), `course/lesson--not-done--member` (1920 light), `course/quiz--after--member` (1920 dark), `pages/space--full--owner` (1920 light), `resources/space--full--member` (1920 dark), `resources/resource--full--member` (1920 light).
   - Notifications: `notifications/page--full--member` (1920 dark, 360 light), `--loading--`, `--error--`, `notifications/bell-panel--*` (360 light), `notifications/page--empty--new-member` (1920 dark), `notifications/settings--full--member` (1920 dark).
   - Settings: `settings/community--full--owner` (1920 dark whole page, 360 light part), `settings/space-form-discussion--new--owner` (360 light), `account/profile--full--member` (1920 light whole page).
   - Public and sign-in: `public/community--full--visitor` (1920 light, 360 dark), `auth/login--empty--visitor` (1920 dark).
2. Code: `styles.css`, all of `Components/ui`, the shared components (`PageHeader`, `PageTitle`, `SectionTitle`, `FormSection`, `ActionSection`, `InputError`, `InputLabel`, `CardGroup`, `Footer`, `PageContentWrapper`), `Layouts/AppLayout.tsx`, the feature components and pages named in the findings. Counts of classes are measured with grep over `*.tsx` without the tests.
3. Not looked at: the error pages in pictures (the local environment shows the framework page; I judged `Pages/Error.tsx` from code only), the API tokens screen (known 500; not read in depth), the home page and the legal pages, the two-factor screens, the image viewer, the emoji picker, the quiz editor. Hover and focus looks are judged from code only (no browser).
4. Seen but not judged (for the other reviewers, not verified): in the 1920 dark pictures of the community home (`full`, `loading`, `error`) the "Catch up" card is absent; in 1920 light and 360 light it shows. It can be the order of the atlas run.

## What works

1. The token set is closed: the default Tailwind palette is removed (`styles.css:27`), so no off-brand color is in the code. Each color token has its contrast written next to it.
2. The dark theme (the default) is as finished as the light one on every screen I opened: no light-only images, the video embed and the galleries sit well on dark, the logo is right in both (`dashboard/home--full--member--1920--dark.png`, `--light.png`).
3. Dialog buttons are in the same order in every dialog I read (about 25): Cancel (secondary) left, the action (primary or danger) right (small exceptions: a lone primary "Close" in `AccessGroupsManager.tsx:286`, a ghost "Reset" before Cancel in `CreateResourceDialog.tsx:102`); on 360 px the dialog is a bottom sheet with full-width buttons, action on top (`discussion/composer--open--member--360--dark.png`, `Components/ui/Dialog.tsx:82`).
4. Every destructive confirmation uses the `danger` variant and puts the first focus on Cancel (`Posts/Show.tsx:201`, `ChatRoom.tsx:442`, and 10 more).
5. `Button` has one busy look (spinner and disabled) and `LinkButton` keeps links as links; most pages use them.
6. The empty states of the six space types use one component with the same parts: emoji, title, one sentence (empty-state pictures of `discussion`, `chat`, `course`, `pages`, `resources`).
7. Settings cards share `Card` + `CardHeader` with the short magenta bar; the space tree editor is clear at 1920 px and still works at 360 px (`settings/community--full--owner--1920--dark.png`).
8. The notifications panel has a skeleton while it loads and a message with "Retry" when the request fails (`notifications/bell-panel--loading--member--360--light.png`, `--error--`).
9. The course outline shows progress clearly: numbered rows, a check per done lesson, one primary "Continue" (`course/outline--full--member--1920--dark.png`).
10. The quiz review marks "Correct" and "Not correct" with icon and word, not color only (`course/quiz--after--member--1920--dark.png`).
11. One frame (`page-container`) lines up the header, the title bar, the content and the footer at every width (`styles.css:198`).
12. The breadcrumb folds the middle items into "..." at 360 px (`discussion/post--full--member--360--dark.png`).
13. The sign-in pages share one card, one width and the compact footer (`auth/login--empty--visitor--1920--dark.png`).

## Findings

### D1 Small text in the accent pink, below its own contrast rule

- Weight: must
- Screen: Discussion space (`/community/atlas-garden/space/garden-talk`), Post, Chat space; member; 1920 and 360; dark
- What is wrong: `--color-secondary-text` (#ff1493) is documented as "large or decorative text only (2.89:1 on the background)" (`styles.css:47`). It is used for small, meaningful text: links and @mentions in every post, comment and message (`MarkdownContent.tsx:116`), the "Pinned" label at 12 px (`PostCard.tsx:23`, `Posts/Show.tsx:114`), "Reply in thread" at 14 px (`ChatMessageItem.tsx:121`), the "New messages" divider at 12 px (`ChatRoom.tsx:383`). I compute about 2.5:1 on `surface` and 3.6:1 on `well` in dark.
- Why it matters: WCAG 1.4.3 failure on the main paths (read a post, chat). The light theme passes (6:1), so the defect is only in the default theme.
- Proposed fix: keep the pink for decoration and large text only. For small text, use `text-primary` with a pink underline (links, mentions), or add a token `--color-accent-text` for dark that reaches 4.5:1 on `surface` and `well` (a lighter pink). Apply it in the five places above.
- Evidence: `discussion/space--full--member--1920--dark.png` (mentions in "Who wants to share an order of seeds?", "Pinned"), `chat/space--full--member--1920--dark.png` ("Reply in thread", the mention); `styles.css:47`
- Seen in: picture and code
- Confidence: high (the ratio is computed from the tokens, not measured in the browser)

### D2 The same checkbox saves at once in one card and needs "Save" in the next

- Weight: must
- Screen: Community settings (`/community/atlas-garden/settings`), owner, 1920 and 360, both themes; Account settings (`/user/profile`)
- What is wrong: "Members can write private messages", "Search engines may index" and "Show the other members when I am online" save on click (`DirectMessagesSetting.tsx:39`, `IndexingSetting.tsx:42`, `OnlineStatusForm.tsx:41`). "Moderator rights" checkboxes (`ModeratorPermissionsForm.tsx:64`) and the "Visible to the members" checkboxes of the profile need a "Save" button. All use the same `Checkbox`. `PopupsForm.tsx:44` gives the checkbox `role="switch"` but it still looks like a checkbox.
- Why it matters: the owner ticks a moderator right, sees the cards above save at once, and leaves without "Save". The look misleads about what the action does.
- Proposed fix: a `Switch` building block for every setting that saves at once (see "Missing building blocks"), `Checkbox` only inside a form with a Save button.
- Evidence: `settings/community--full--owner--1920--dark.png` ("Direct messages" card, then "Moderator rights" with "Save"), `account/profile--full--member--1920--light.png`
- Seen in: picture and code
- Confidence: high

### D3 The fill color `danger` is used as a text color

- Weight: must
- Screen: Notifications page and bell panel (an action that fails), Account settings (connected accounts), sign-in with a provider
- What is wrong: `text-danger` (#dc2626, a fill token) is used for error text in `NotificationsPanel.tsx:656`, `OpenIdConnectButton.tsx:22`, `ConnectedAccountsForm.tsx:58`. On the dark `surface` this is about 1.9:1. Everywhere else the error text uses `text-danger-text` (21 places, for example `InputError.tsx:18`).
- Why it matters: the error message of "Done" or "Mute" on the notifications page cannot be read in the default theme.
- Proposed fix: replace by `text-danger-text` (and `border-danger-text` for the box). A lint rule that forbids `text-danger` and `text-secondary` would stop it.
- Evidence: `NotificationsPanel.tsx:656`, `styles.css:60-62`
- Seen in: code (the error state of an action is not in the atlas)
- Confidence: high

### D4 Two layouts for settings, on the same page

- Weight: should
- Screen: Community settings (`/community/atlas-garden/settings`), owner, 1920, both themes
- What is wrong: the first ten sections are one column of full-width cards with `CardHeader`. "Transfer the ownership" and "Delete Community" switch to the two-column `ActionSection` (title and description left, card right). Account settings and notification settings are all two-column (`FormSection` / `ActionSection`). Field widths follow: full card width in community settings, about two thirds of the card in account settings.
- Why it matters: the eye loses its column at the bottom of the longest page; the two settings areas look like two products.
- Proposed fix: choose one pattern for every settings page. The one-column card with `CardHeader` fits narrow screens and the space tree; move `DeleteCommunityForm`, `TransferOwnershipForm` and the `Profile/Partials` forms to it, or the reverse. Then remove `FormSection`/`ActionSection` or `CardHeader` from settings.
- Evidence: `settings/community--full--owner--1920--dark.png` (bottom), `account/profile--full--member--1920--light.png`; `DeleteCommunityForm.tsx:79`, `FormSection.tsx:16`
- Seen in: picture and code
- Confidence: high

### D5 Four ways to save a form

- Weight: should
- Screen: Account settings, Community settings, Resource, Notification settings; 1920; both themes
- What is wrong: (a) a grey footer strip with "Reset" and "Save", Save always enabled (`FormSection.tsx:27`, `UpdateProfileInformationForm.tsx:214`); (b) "Save" at the bottom right inside the card, disabled until a change (`UpdateCommunityForm.tsx:155`, `ModeratorPermissionsForm.tsx:84`); (c) a small "Save" at the bottom left of a well (`ResourceChangesForm.tsx:70`); (d) no button, saved at once (notification settings, the community toggles).
- Why it matters: a member meets (a) and (d) in the same user menu every week; the place of the button and its enabled state change from card to card.
- Proposed fix: one `FormActions` block (right aligned, Save primary, optional Reset ghost, disabled until dirty) for forms; `Switch` and `SaveStatus` for instant settings. See "Missing building blocks".
- Evidence: `account/profile--full--member--1920--light.png`, `settings/community--full--owner--1920--dark.png`, `resources/resource--full--member--1920--light.png`
- Seen in: picture and code
- Confidence: high

### D6 Three kinds of feedback after an instant save

- Weight: should
- Screen: Community settings, Account settings, Notification settings, Discussion space header
- What is wrong: community toggles and "Online status" show a toast (`DirectMessagesSetting.tsx`, `IndexingSetting.tsx:30`, `OnlineStatusForm.tsx:34`); notification settings show a sticky "Saved" bar at the top of the page (`Profile/Notifications.tsx:57-60`); the level control of a space shows an inline "Saved" text (`SpaceLevelControl.tsx:105`). Errors are an inline "Not saved. Retry." in some and a toast in others.
- Why it matters: the same act gets three answers in three places; a member learns none of them.
- Proposed fix: one `SaveStatus` next to the control (inline "Saved" / "Not saved. Retry."), used by every instant setting; toasts only for actions that leave the screen.
- Evidence: code lines above; `notifications/settings--full--member--1920--dark.png`
- Seen in: code
- Confidence: high

### D7 One choice, two controls

- Weight: should
- Screen: Discussion space header (`/space/garden-talk`) and Notification settings (`/user/notifications`), member, 1920, dark
- What is wrong: the notification level of a space is a radio group with one sentence per level in the space, and a bare `Select` with only the level names in the settings page (14 selects in a row, each labelled "Notifications for <space>", without the emoji of the space).
- Why it matters: the settings page hides the meaning of "Only what is for me" and "Muted" exactly where the member compares all spaces.
- Proposed fix: one `NotificationLevelPicker` used in both places; in the settings page a compact row per space (emoji, name, segmented "Everything / For me / Muted") with the explanation once at the top of the group.
- Evidence: `discussion/space-level--menu--member--1920--dark.png`, `notifications/settings--full--member--1920--dark.png`; `SpaceLevelControl.tsx:68`, `SpaceLevelsForm.tsx`
- Seen in: picture and code
- Confidence: high

### D8 The full footer under every signed-in screen, also under a chat

- Weight: should
- Screen: every page in `AppLayout`, 1920 and 360, both themes
- What is wrong: the footer is a 420 px block at 1920 px and about 900 px at 360 px, with 21 links and a 56 px "Knowii" wordmark. That wordmark is the largest text on every signed-in page (the page titles are 24 to 30 px). Under a chat, the page scrolls past the chat into the footer.
- Why it matters: it pulls the eye down and away from the work; on a phone it doubles the page length. Other community tools keep the signed-in frame without a marketing footer.
- Proposed fix: use the existing `Footer variant="compact"` (already used by the sign-in and error pages) in `AppLayout.tsx:236`; keep the full footer for the public pages.
- Evidence: `chat/space--full--member--360--dark.png`, `dashboard/home--full--member--1920--dark.png`; `Footer.tsx:57`, `AppLayout.tsx:236`
- Seen in: picture and code
- Confidence: high

### D9 The chat shows one and a half messages on a phone

- Weight: should
- Screen: Chat space (`/space/lounge`), member, 360, both themes
- What is wrong: the chat box is `h-[70vh]` including the composer (`ChatRoom.tsx:344`); the composer (tabs, 2-line toolbar, field, Markdown hint, keyboard hint, Send) takes more than half. At 360 x 800 the message list is about 230 px high. The "1 here" pill sits over a cut message.
- Why it matters: chat is a main path; the reader scrolls inside a small box and the page scrolls around it.
- Proposed fix: on small screens give the chat the height of the viewport under the header (`h-[calc(100dvh-…)]`), fold the composer toolbar behind one button, drop the Markdown hint line under the field (it repeats the toolbar), and remove the footer (D8).
- Evidence: `chat/space--full--member--360--dark.png`, `chat/space--full--member--1920--dark.png`
- Seen in: picture and code
- Confidence: high

### D10 Three content widths with no clear rule; half the screen empty at 1920 px

- Weight: should
- Screen: community pages, 1920, both themes
- What is wrong: posts, chat, pages, courses, member profiles use `readable-width` (768 px, `CommunityShell.tsx:137`); at 1920 px 470 px on the right stay empty. Resources, Messages, About, community home use the full frame. Notifications use `max-w-6xl` (1152 px, `PageContentWrapper.tsx:12`), where an excerpt runs about 150 characters per line. The member profile is a 420 px card in a 768 px column.
- Why it matters: moving from a space to Messages shifts the whole layout; long lines on the notifications page are slow to read; the empty right side reads as unfinished.
- Proposed fix: one rule: reading content (posts, comments, notification lines, lessons) at `readable-width`; lists of cards and tools at the full frame. Apply `readable-width` to the notification lines. Using the empty right side is a layout change: see "Proposals for the owner".
- Evidence: `discussion/space--full--member--1920--dark.png`, `messages/conversation--direct--member--1920--light.png`, `notifications/page--full--member--1920--dark.png`, `community/member-profile--other--member--1920--light.png`
- Seen in: picture and code
- Confidence: high

### D11 The page title has five different places

- Weight: should
- Screen: all signed-in pages, 1920 and 360
- What is wrong: (1) breadcrumb only, `h1` hidden (`Communities/Show.tsx:75`, `Messages/Index.tsx:90`, `Profile/Notifications.tsx:55`); (2) `h1` in the content with emoji (spaces, `Spaces/Show.tsx:127`); (3) an `h2` as the visible title and no `h1` (Dashboard "My Communities", `Dashboard.tsx:99`); (4) a large centered `h1` in the title bar and no breadcrumb (only Notifications, `Notifications/Index.tsx:30`); (5) the global `h1` style at 36 to 60 px (`Contact.tsx:27`, `Error.tsx:48`, `styles.css:161`). At 360 px the breadcrumb is centered when the page has actions (community home) and left aligned when not (`PageTitle.tsx:19`).
- Why it matters: the member cannot rely on one place to see where they are; the community home has no visible name except in the breadcrumb.
- Proposed fix: breadcrumb in the title bar on every page (Notifications included), one visible `h1` at the top of the content, one style (`text-2xl font-extrabold sm:text-3xl`, the `PageTitle` one); remove the global `h1`/`h2` rules in `styles.css:161-167` or limit them to the home page. Left align the breadcrumb at all widths.
- Evidence: `notifications/page--full--member--1920--dark.png`, `community/home--full--member--1920--dark.png`, `community/home--full--member--360--light.png`, `dashboard/home--full--member--1920--light.png`
- Seen in: picture and code
- Confidence: high

### D12 The type scale: nine sizes and five weights in use, headings in 20 styles

- Weight: should
- Screen: all
- What is wrong: measured in the code: sizes `xs` (88), `sm` (244), `base` (12), `lg` (20), `xl` (6), `2xl` (13), `3xl` (3), `4xl` (2), plus `text-[10px]` (`HereNowButton.tsx:36`) and the global `h1` at 5xl/6xl. Weights: `bold` (132), `extrabold` (36), `medium` (10, for example `CommunityBox.tsx:53`, `Login.tsx:54`), `semibold` (1), `normal` (2). There are about 20 distinct class strings on `h1`/`h2`/`h3`. Section titles alone have four looks: `CardHeader` (18 px, bar, icon), `SectionTitle` (18-20 px, bar), plain `h2` 20 px (`SpaceTreeEditor.tsx:87`, "Spaces"), plain 16 px (`CatchUpCard.tsx:142`, "Catch up"; `NotificationsPanel.tsx:814`, "Needs you" at 14 px).
- Why it matters: the levels do not read as levels; the same rank of heading changes size from screen to screen.
- Proposed fix: name the styles once in `styles.css` as utilities (`text-title` 2xl/3xl extrabold, `text-heading` lg extrabold, `text-subheading` sm extrabold, `text-label` xs bold uppercase muted, `text-body`, `text-meta` xs muted) and use only those; drop `font-medium` and `font-semibold`; remove `text-[10px]`.
- Evidence: grep counts above; `settings/community--full--owner--1920--dark.png` ("Spaces" against the cards), `community/home--full--member--360--light.png` ("Catch up" against "Recent Resources")
- Seen in: code and picture
- Confidence: high

### D13 Cards on the same screen with and without the header bar and icon

- Weight: later
- Screen: Community home and About (`/community/atlas-garden`, `/about`), member, 1920, both themes
- What is wrong: on the community home "Catch up" has a plain title and a subtitle, "Recent Resources" and "Members" have icon and magenta bar. On About, "About", "Team", "What moderators can do" have an icon; "What you can do" and "Leave the community" do not.
- Why it matters: a small, daily inconsistency on the first screen of a community.
- Proposed fix: `CatchUpCard` uses `Card` + `CardHeader`; give every `CardHeader` an icon or none in a page.
- Evidence: `community/home--full--member--360--light.png`, `community/about--full--member--1920--dark.png`; `CatchUpCard.tsx:138-146`
- Seen in: picture
- Confidence: high

### D14 Seven icon sets, two of them for the same glyphs

- Weight: should
- Screen: Header, breadcrumb, community home, settings
- What is wrong: `react-icons/fa` (32 files) and `fa6` (61 files) give the same Font Awesome glyphs twice: `FaBars` from `fa` in the header (`AppLayout.tsx:18`) and from `fa6` in the "Spaces" button (`CommunityShell.tsx:6`); `FaSearch` (`Dashboard.tsx:10`) against `FaMagnifyingGlass` (`CommunityMembersManager.tsx:25`); `FaMapMarkerAlt` (profile) against `FaLocationDot` (member page); `FaSignOutAlt` against `FaRightFromBracket`; `FaRedo`/`FaSyncAlt` against `FaRotateRight`. Between fa and fa6 the difference is hardly visible. What is visible: three other families with an outline style among the solid ones: the community icon `GrGroup` (Grommet, `CommunityIcon.tsx:17`) in every breadcrumb and community card, `TiGroupOutline` (Typicons) on the "Members" card, `MdLayers` (Material) on "Recent Resources", plus the outline `FaRegNewspaper` on the public page. Brand icons come from `si` and `bs`. Emoji are icons too (spaces, empty states, resource levels).
- Why it matters: the breadcrumb shows a solid house next to an outline group; the card headers of one screen mix three drawing styles. Two copies of one set also cost bundle size.
- Proposed fix: `fa6` solid only (keep `si`/`bs` for brand marks); replace `GrGroup` by `FaUsers`, `TiGroupOutline` by `FaUserGroup`, `MdLayers` by `FaLayerGroup`; one icon map in `Components/ui/icons.ts` so pages import names, not sets.
- Evidence: `community/home--full--member--1920--dark.png` (breadcrumb, card headers), `public/community--full--visitor--1920--light.png`; files above
- Seen in: picture and code
- Confidence: high

### D15 Button heights and field heights do not line up

- Weight: should
- Screen: Dashboard filters, Community settings (invite row), Waitlist and newsletter forms
- What is wrong: `Button` is 32/40/48 px (`Button.tsx:28-32`); fields are 36/44/48 px (`form-field.ts:14-18`). Pages patch it by hand: `className="h-9"` on "Clear filters" (`Dashboard.tsx:120`), `className="h-11"` (`WaitlistForm.tsx:111`, `NewsletterForm.tsx:119`), and `sm:mt-7` to drop "Invite" next to the email field (`CommunityMembersManager.tsx:267`).
- Why it matters: every new inline form needs a patch; without it the button sits 4 px short of the field.
- Proposed fix: align the scales (button `sm` h-9, `md` h-11, or fields to the button scale) and remove the four patches.
- Evidence: `dashboard/home--full--member--1920--dark.png` (filter row), `settings/community--full--owner--1920--dark.png` (invite row); lines above
- Seen in: code and picture
- Confidence: high

### D16 Buttons and links styled by hand, outside `Button`

- Weight: should
- Screen: Chat, Post, Notifications, Dashboard, Account settings
- What is wrong: these elements are buttons built by hand with their own classes: "Reply in thread" as a pink text link (`ChatMessageItem.tsx:118`); the add-reaction chip (`ReactionBar.tsx:156`); "1 here" (`HereNowButton.tsx:22`); the round "..." trigger next to the rounded-lg "Done" (`NotificationLine.tsx:197`); "Undo" and "Show all" as underlined text (`NotificationsPanel.tsx:628`, `CatchUpCard.tsx:200`, `NotificationLine.tsx:153`); three disclosure toggles (`SpaceLevelControl.tsx:68`, `NotificationsPanel.tsx:813`, `CommentSection.tsx:388`); "New community" tile (`CommunityBox.tsx:22`); "Send the link again" (`UpdateProfileInformationForm.tsx:271`) and "Log out" (`VerifyEmail.tsx:49`) as `simple-link` buttons; the logout item of the user menu (`AppLayout.tsx:207`). The emoji search field is a hand-built input (`EmojiPicker.tsx:252`). `buttonClasses()` on links is fine and used in 15 places.
- Why it matters: each one has its own hover, focus, size and busy look (none has a busy state); the same action looks different by screen (D17).
- Proposed fix: `Button variant="ghost" size="sm"` for the text actions, `Button iconOnly` for "..." (let `DropdownMenu` take a `Button` as trigger), a `Disclosure` block for the toggles, `TextInput size="sm"` in the emoji picker, a `link` variant of `Button` for the underlined text actions.
- Evidence: `notifications/page--full--member--1920--dark.png`, `chat/space--full--member--1920--dark.png`; lines above
- Seen in: code and picture
- Confidence: high

### D17 The same action in different looks

- Weight: should
- Screen: Post, Chat, Direct messages, Account and Community settings
- What is wrong: "Reply" is a ghost button with an icon under a comment and a pink text link under a chat message. "Cancel" is `secondary` in `CommentForm.tsx:100` and `ghost` in `ChatComposer.tsx:144`. The delete trigger is `ghost` on a post (`Posts/Show.tsx:152`), `ghost` text under a comment, `danger sm` for "Leave this community", `danger md` for "Delete Account". "Edit" is `secondary` on a post and `ghost` on a comment. In direct messages "Edit" and "Delete" always show under the own message; in the chat they do not.
- Why it matters: the member re-reads every row to find the action.
- Proposed fix: one rule per action kind, written in the toolkit: row actions on content are `ghost sm` with icon and label; the destructive trigger is `ghost` with a danger-colored icon, the confirmation button is `danger`; Cancel is always `secondary`.
- Evidence: `discussion/post--full--member--1920--dark.png`, `chat/space--full--member--1920--dark.png`, `messages/conversation--direct--member--1920--light.png`, `community/about--full--member--1920--dark.png`
- Seen in: picture and code
- Confidence: high

### D18 Comments carry more controls than text

- Weight: should
- Screen: Post with comments (`/post/my-tomatoes-have-yellow-leaves`), member, 1920 and 360, dark
- What is wrong: each comment is a meta line, the text, a reaction row (an empty add-reaction chip even with no reaction) and an action row of two to four labelled buttons (Reply, Copy link, Edit, Delete). A one-line comment takes four lines; at 360 px the actions wrap. The post header has two rows of buttons in mixed variants before the text (Following / Stop following / Mute this thread, then Edit / Delete).
- Why it matters: reading the thread, the main path, is slowed by repeated controls; the text is not the strongest element.
- Proposed fix: keep "Reply" visible, move "Copy link", "Edit", "Delete" into a "..." menu per comment; show the add-reaction chip on hover and focus only when there is no reaction (always on touch); put follow and mute in one menu next to the post title.
- Evidence: `discussion/post--full--member--1920--dark.png`, `discussion/post--full--member--360--dark.png`; `CommentSection.tsx:277-300`, `Posts/Show.tsx:140-152`
- Seen in: picture
- Confidence: medium (the order of priority is a judgment)

### D19 A person and a post are shown in different ways

- Weight: should
- Screen: Discussion space, Post, Chat, Messages, Notifications, Public page
- What is wrong: authors have an avatar in chat, direct messages, notifications, the public page, settings and About; no avatar in the post list, on the post page, in comments and on a resource ("Added by"). A post is drawn four ways: `PostCard` (title, meta, body, reactions), the public list (title, body, then avatar with meta and space, `Public.tsx:65`), the "Catch up" line (bold headline, muted excerpt), the notification line. The meta order changes (author, time, comments / author @handle, space, time).
- Why it matters: the reader cannot scan who wrote what in the place where it matters most (comments).
- Proposed fix: one `MemberIdentity` block (avatar, name, optional handle, meta) and one `MetaLine`; add the small avatar to `PostCard`, the post header and each comment.
- Evidence: `discussion/post--full--member--1920--dark.png`, `public/community--full--visitor--1920--light.png`, `chat/space--full--member--1920--dark.png`; `PostCard.tsx`, `CommentSection.tsx`
- Seen in: picture and code
- Confidence: high

### D20 The post list does not clamp media

- Weight: should
- Screen: Discussion space (`/space/garden-talk`), member, 1920 and 360, dark
- What is wrong: `PostCard` clamps the body to four lines (`PostCard.tsx:39`), but a video player and a gallery of four images show in full in the list; one post with a picture is about 600 px high.
- Why it matters: the list loses its rhythm; the member scrolls past media to find titles.
- Proposed fix: in `PostCard`, show the first image as a fixed-ratio thumbnail (or "4 pictures", "1 video" with an icon) and strip embeds from the excerpt.
- Evidence: `discussion/space--full--member--1920--dark.png` (lower half)
- Seen in: picture and code
- Confidence: high

### D21 The accent color is spent on avatars and chrome

- Weight: should
- Screen: Notifications page, Chat, Settings members list, header
- What is wrong: the fallback avatar is a magenta disc for every member (`Avatar.tsx:41`). The accent also marks primary buttons (with a glow), counts, dots, breadcrumb chevrons (`Breadcrumb.tsx:74`), every card title bar, the email button of the footer (`Footer.tsx:86`, a primary with glow for a mail link).
- Why it matters: on the notifications page the column of magenta discs is the strongest element; the "Done" buttons and the text come second. The accent no longer points at the one action.
- Proposed fix: neutral fallback avatar (`bg-overlay/15 text-primary`, or a few muted hues taken from the name); chevrons in `text-muted`; the footer icons all `secondary`. Keep magenta for primary actions, counts and the current item.
- Evidence: `notifications/page--full--member--1920--dark.png`, `settings/community--full--owner--1920--dark.png` (members list)
- Seen in: picture and code
- Confidence: high

### D22 The "new content" dot changes meaning with the theme

- Weight: later
- Screen: Community navigation, member, 1920, both themes
- What is wrong: the dot is 10 px with a 2 px border in the text color (`SpaceNavigation.tsx:100`). In dark it reads as a white ring, in light as a black dot; the magenta center is 6 px. It sits next to a lock icon that nearly every space has (members only).
- Why it matters: the strongest daily signal of the navigation is weak and different per theme; the locks add noise next to it.
- Proposed fix: an 8 px solid accent dot with a 2 px ring in the color of the background (`ring-2 ring-background`, keeps the shape in forced colors through `forced-colors:` border); show the lock only on spaces with a narrower access than the community default, or only in the settings.
- Evidence: `discussion/space--full--member--1920--dark.png`, `discussion/space--full--member--1920--light.png` (left column)
- Seen in: picture and code
- Confidence: high

### D23 Four looks for the current item

- Weight: later
- Screen: Main menu, community navigation, user menu
- What is wrong: the main menu marks the current entry with a magenta left bar, a pink icon and large text (`MainMenu.tsx:45`); the space navigation with `bg-overlay/15` and bold; the user menu with `bg-overlay/10` and bold (`AppLayout.tsx:164`); the "Messages" link with `bg-overlay/15` (`CommunityShell.tsx:155`).
- Why it matters: small, but met on every screen.
- Proposed fix: one `NavItem` with one current style (fill plus bold, or bar plus bold) used by the three menus.
- Evidence: `dashboard/main-menu--open--member--360--dark.png`, `community/navigation--drawer--member--360--dark.png`, `dashboard/user-menu--open--member--360--dark.png`
- Seen in: picture and code
- Confidence: high

### D24 A menu button for one link

- Weight: should
- Screen: Header, every signed-in page, member, 1920 and 360
- What is wrong: the header has four square controls (theme, bell, menu, avatar). The menu button opens a drawer whose only entry is "Dashboard" (`AppLayout.tsx:137`). At 360 px the "Spaces" button of a community uses the same bars icon for a different drawer.
- Why it matters: two clicks for one link; two identical icons with different targets on one phone screen.
- Proposed fix: replace the menu button by a "Dashboard" link (house icon, or the logo, which already goes to the home page: decide one); keep the bars icon only for the community drawer.
- Evidence: `dashboard/main-menu--open--member--360--dark.png`, `community/home--full--member--360--light.png`
- Seen in: picture and code
- Confidence: high

### D25 Empty states: two patterns, missing in one place, without their action

- Weight: should
- Screen: Notifications page, Dashboard search, Course, Messages, Discussion
- What is wrong: the dashed `EmptyState` sits next to a plain muted sentence on the same screen ("Nothing needs you." boxed, "Nothing new in what you follow." plain, `NotificationsPanel.tsx:721` and `:790`); about 15 places use the plain sentence (for example `CommentSection.tsx:356`, `ConversationList.tsx:19`). A dashboard search without result shows no text at all, only the "New community" tile (`Dashboard.tsx:124`). The course shows "0 lessons" above "No lessons yet". The action ("New post", "Add a page") sits above the empty box, never in it; `EmptyState` has an `action` prop that no space uses.
- Why it matters: the empty screen should say what to do first; here the eye must find the button elsewhere.
- Proposed fix: `EmptyState` with a `size="inline"` variant for small lists; a "No community matches" state on the dashboard; pass the primary action into `action`; hide the count when it is 0.
- Evidence: `notifications/page--empty--new-member--1920--dark.png`, `dashboard/home--no-match--member--1920--dark.png`, `course/outline--empty--member--1920--dark.png`, `discussion/space--empty--owner--1920--dark.png`
- Seen in: picture and code
- Confidence: high

### D26 Loading and error: skeleton in one panel, nothing or a stuck spinner elsewhere

- Weight: should
- Screen: Community home "Catch up", Thread panel, Settings lists
- What is wrong: only the notifications panel has a skeleton (`NotificationsPanel.tsx:700`). The "Catch up" card renders nothing while it loads and nothing when its request fails, so the page jumps when it arrives (`CatchUpCard.tsx:136`). When a thread fails to load, the panel keeps the spinner forever and a toast says why, then goes away (`ChatRoom.tsx:313-320`, `ThreadPanel.tsx:60`): the error picture is the loading picture.
- Why it matters: the member waits on a spinner that will never stop (chat with a thread is a main path).
- Proposed fix: a `Skeleton` block used by every list that loads after the page (Catch up, members, access groups, thread); an inline error with "Retry" in the thread panel, like the notifications panel.
- Evidence: `chat/thread--error--member--360--light.png` against `chat/thread--loading--member--360--light.png`, `community/home-catch-up--loading--member--360--light.png`, `community/home-catch-up--error--member--360--light.png`
- Seen in: picture and code
- Confidence: high

### D27 Every confirmation dialog has an empty band

- Weight: later
- Screen: Delete a post, a comment, a message, a resource, a space; 1920 and 360; both themes
- What is wrong: `Dialog` always renders the body padding (`Dialog.tsx:79`), so a dialog with only a title and a description shows an empty 32 px strip between two lines.
- Why it matters: looks unfinished on the dialogs that ask for the most care.
- Proposed fix: render the body only when `children` is set.
- Evidence: `discussion/post--delete-dialog--member--1920--light.png`
- Seen in: picture and code
- Confidence: high

### D28 The composer writes in a monospace font

- Weight: later
- Screen: New post, comment, chat, direct message; all widths
- What is wrong: the textarea of `MarkdownEditor` is `font-mono text-sm` (`MarkdownEditor.tsx:632`); the placeholder "Message Lounge" and the text are in JetBrains Mono, the result is Noto Sans at 16 px. Under the field a Markdown cheat line repeats the toolbar, and the chat adds a second hint line. At 360 px the toolbar wraps and leaves the video icon alone on a line.
- Why it matters: writing a post feels like editing code; this is the main writing surface of a community product.
- Proposed fix: `font-sans text-base` in the field; move the Markdown hint behind a "?" in the toolbar; one hint line at most.
- Evidence: `chat/space--full--member--1920--dark.png`, `discussion/composer--open--member--360--dark.png`
- Seen in: picture and code
- Confidence: high

### D29 Member text in two sizes

- Weight: later
- Screen: Post, Chat, Direct messages
- What is wrong: a comment and a post card body are 14 px (`CommentSection.tsx:277`, `PostCard.tsx:39`); a chat message, a direct message and the post body are 16 px with prose line height (`ChatMessageItem.tsx:80`, `Posts/Show.tsx:163`).
- Why it matters: a reply in a thread reads smaller than a chat line; the comment, the main reading surface after the post, is the small one.
- Proposed fix: all member text at the body size; the list excerpt may stay smaller.
- Evidence: `discussion/post--full--member--1920--dark.png`, `chat/space--full--member--1920--dark.png`
- Seen in: picture and code
- Confidence: high

### D30 Labels and pills built six ways

- Weight: later
- Screen: Space header, Dashboard, Notifications, Quiz, Resource, Settings
- What is wrong: space meta chips `rounded-full border px-2 py-0.5` (`Spaces/Show.tsx:139-144`); community visibility badge `rounded-lg bg-overlay/10 text-xs font-medium` (`CommunityBox.tsx:53`); "NEW" `rounded border-primary uppercase` (`NotificationLine.tsx:95`); "Your answer" `rounded-full bg-overlay/10 text-xs font-bold` (`LessonQuiz.tsx:283`); resource chips (`ResourceDetails.tsx:23`); role badges `rounded-full bg-overlay/10 px-3 py-1 text-sm` (`About.tsx:164`, `CommunityMembersManager.tsx:314`). The community icon color also codes the visibility with status tokens (green public, pink private, yellow personal) without a legend (`community.schema.ts:29-31`).
- Why it matters: the same kind of fact (a type, a state, a role) has a different shape each time.
- Proposed fix: a `Badge` block (see below) and a fixed icon color for the community icon.
- Evidence: `dashboard/home--full--member--1920--dark.png`, `notifications/page--full--member--1920--dark.png`, `community/about--full--member--1920--dark.png`, `course/quiz--after--member--1920--dark.png`
- Seen in: picture and code
- Confidence: high

### D31 Resource rows open with two unexplained glyphs

- Weight: later
- Screen: Community home and Resources space, member, 1920 and 360
- What is wrong: each title starts with a level emoji (❓ 🌱 🌿 🌳 🏆) and a type icon (`ResourceCard.tsx:31-32`); the level has no label in the list, and "❓" (no level) reads as an error. At 360 px the two glyphs push the title to three lines. Each row also ends with "Link only" in 12 px.
- Why it matters: the scan line of the list is the title; here it starts with noise.
- Proposed fix: type icon only before the title; level and capture state as one `MetaLine` under the description ("Beginner · Article · Link only"); no glyph for "no level".
- Evidence: `community/home--full--member--360--light.png`, `resources/space--full--member--1920--dark.png`
- Seen in: picture and code
- Confidence: medium

### D32 Light theme: "raised" is the same as "flat"

- Weight: later
- Screen: menus, interactive cards, Catch up card; light
- What is wrong: in light `--color-surface-elevated` equals `--color-surface` (#ffffff, `styles.css:97-98`). The hover of an interactive `Card` (`Card.tsx:20`) changes no fill in light, and the "Catch up" card, which is "raised" in dark, looks like any card in light.
- Why it matters: small, but the light theme loses a level that dark has.
- Proposed fix: a light value one step off white for `surface-elevated` (or use the shadow alone, on purpose, and say so in the token comment).
- Evidence: `styles.css:97-98`; `community/home--full--member--360--light.png`
- Seen in: code and picture
- Confidence: medium

### D33 The error page does not follow the system

- Weight: later
- Screen: Error page (`Pages/Error.tsx`), 404/403/500/503
- What is wrong: prose `h1` and `h2` at the global size, emoji in the description ("😭", "⛔"), "Go back to the homepage" to the public home page also for a signed-in member, a header without the signed-in controls, and the compact footer inside `main`. It does not use `EmptyState`, `PageTitle` or `AppLayout`.
- Why it matters: the error page is where trust is lowest; it should look like the product.
- Proposed fix: render the error inside `AppLayout` when signed in, with `EmptyState` (icon, title, one sentence, one `LinkButton` to the dashboard or the home page).
- Evidence: `Error.tsx:24-62`
- Seen in: code (the atlas shows the framework page in the local environment)
- Confidence: high

## Missing building blocks

Each line: proposed name and props, then where it is built by hand now, then the variants seen.

1. `Switch` (`checked`, `onChange`, `label`, `description`, `busy`, `disabled`): instant settings built from `Checkbox` in `DirectMessagesSetting.tsx:39`, `IndexingSetting.tsx:42`, `OnlineStatusForm.tsx:41`, `PopupsForm.tsx:44` (with `role="switch"`). Variants: with and without a description line. Fixes D2.
2. `SaveStatus` (`state: 'idle' | 'saving' | 'saved' | 'failed'`, `savedText`, `onRetry`): "Saved" / "Not saved. Retry." in `SpaceLevelControl.tsx:105-110`, `ThreadFollowControl.tsx:135-140`, `TimeZoneAsk.tsx:115`, `PopupsForm.tsx:51`, `SpaceLevelsForm.tsx:202` and `:221`, `TimeZoneForm.tsx:69`, the sticky bar `Profile/Notifications.tsx:57-60`, `CatchUpCard.tsx:215`, `NotificationsPanel.tsx:626`. Variants: inline text, sticky bar, filled box, screen-reader only. Plus `StatusMessage` (`tone: 'success' | 'danger' | 'neutral'`) for the success lines `text-sm font-medium text-success` in `Login.tsx:54`, `ForgotPassword.tsx:41`, `VerifyEmail.tsx:35`, `UpdateProfileInformationForm.tsx:279`, `ConnectedAccountsForm.tsx:63`.
3. `CountBadge` (`count`, `max = 99`, `srLabel`) and `NewDot` (`srLabel`): counts in `SpaceNavigation.tsx:106`, `CommunityShell.tsx:161`, `ConversationList.tsx:60`, `NotificationBell.tsx:140` (with a ring, absolute); the dot in `SpaceNavigation.tsx:100`. Variants: inline pill, pill on an icon.
4. `Badge` (`variant: 'outline' | 'subtle' | 'accent' | 'success' | 'danger'`, `size: 'xs' | 'sm'`, `icon`): `Spaces/Show.tsx:139-144`, `CommunityBox.tsx:53`, `NotificationLine.tsx:95`, `LessonQuiz.tsx:283` and the "Correct" / "Not correct" labels, `ResourceDetails.tsx:23`, `About.tsx:164`, `CommunityMembersManager.tsx:314`, "Pinned" in `PostCard.tsx:23` and `Posts/Show.tsx:114`. Six shapes today (D30).
5. `Skeleton` (`lines`, `avatar`, `width`) and `SkeletonLine`: built twice in `NotificationsPanel.tsx:700-703` and `:770-773`; needed in `CatchUpCard`, `CommunityMembersManager`, `AccessGroupsManager`, `ThreadPanel` (D26).
6. `LoadMore` (`onLoad`, `loading`, `hasMore`, `label`): `PostList.tsx:181`, `ResourceList.tsx:130`, `CommunityMembersManager.tsx:333`, `AccessGroupsManager.tsx:341`. Variants: size `md` and `sm`, "Load more posts", "Load more resources", "Load more", "Show more", with and without `loading`.
7. `Callout` (`tone: 'neutral' | 'info' | 'warning' | 'danger' | 'success'`, `icon`, `title`, `action`): the pattern `rounded-lg border border-border bg-well p-3|p-4` in `ChatRoom.tsx:403`, `QuizEditor.tsx:209`, `SpaceDialog.tsx:360`, `WaitlistForm.tsx:84`, `NewsletterForm.tsx:84`, `ResourceDetails.tsx:100`; warning in `SpaceDialog.tsx:425`, `Legal/Show.tsx:46`; danger in `NotificationsPanel.tsx:656` (with the wrong token, D3).
8. `Banner` (`tone`, `children`, `actions`, `onDismiss`) for a question under the header: `TimeZoneAsk.tsx` placed by `AppLayout.tsx:218`. One use today; build it as a `Callout` variant `placement="page"` rather than a separate block.
9. `SectionHeader` (`title`, `as`, `description`, `count`, `actions`): "Spaces" + "Add a space group" (`SpaceTreeEditor.tsx:87`), "Conversations" + "New message" (`Messages/Index.tsx:94`), "9 comments" + Expand/Collapse (`CommentSection.tsx:336`), "Needs you" / "Following" + "I am caught up" (`NotificationsPanel.tsx`), "4 pages" / "7 resources" + add buttons (`PageList.tsx`, `ResourceList.tsx:102`), and `SectionTitle.tsx`. Four sizes today (D12).
10. `ConfirmDialog` (`open`, `title`, `description`, `confirmLabel`, `tone = 'danger'`, `busy`, `onConfirm`, `onCancel`): the same Dialog + Cancel (`autoFocus`) + danger button assembled 12 times: `AccessGroupsManager.tsx:675`, `CommunityMembersManager.tsx:421`, `LeaveCommunityButton.tsx:47`, `SpaceTreeEditor.tsx:256`, `CommentSection.tsx:450`, `CourseMaterials.tsx:507`, `PageList.tsx:303`, `ChatRoom.tsx:440`, `Resources/Show.tsx:166`, `Posts/Show.tsx:199`, `DeleteCommunityForm.tsx:94`, `DeleteUserForm.tsx:67`. I found no inline confirmation (in the row) in the code; the dialog is the only pattern, which is good.
11. `Disclosure` (`summary`, `open`, `onOpenChange`, `variant: 'inline' | 'boxed'`): hand-built in `SpaceLevelControl.tsx:68`, `NotificationsPanel.tsx:813`, `CommentSection.tsx:375-388`; `Collapsible` exists (boxed look) and is used once (`UpdateProfileInformationForm.tsx:360`).
12. `FormActions` (`align = 'end'`, `reset`, `submitLabel`, `dirty`, `busy`): the four save placements of D5 (`FormSection.tsx:27`, `UpdateCommunityForm.tsx:154`, `ModeratorPermissionsForm.tsx:83`, `ResourceChangesForm.tsx:70`, `CommentForm.tsx:98`, `ChatComposer.tsx:136`).
13. `MemberIdentity` (`member`, `size`, `showHandle`, `meta`, `href`): avatar + name + handle built in `CommunityMembersManager.tsx`, `About.tsx`, `NotificationLine.tsx:112`, `ConversationList.tsx`, `Public.tsx`, `ChatMessageItem.tsx:60`, `AccessGroupsManager.tsx`, `MemberPicker.tsx` (D19).
14. `MetaLine` (`items: ReactNode[]`, separator "·"): `PostCard.tsx`, `Posts/Show.tsx`, `Public.tsx`, `NotificationLine.tsx:135`, `CommentSection.tsx`, `ChatMessageItem.tsx:60`, `ResourceCard.tsx`. Variants: "·" with spaces, two spaces, comma.
15. `ContentActions` (`actions`, `overflow`): the Reply / Copy link / Edit / Delete row in `CommentSection.tsx`, `ChatMessageItem.tsx`, `ConversationView.tsx` (D17, D18).
16. `NavItem` (`href`, `icon`, `current`, `trailing`): `MainMenu.tsx`, `SpaceNavigation.tsx:84`, `CommunityShell.tsx:148`, the user menu links in `AppLayout.tsx:160-210` (D23).
17. `NotificationLevelPicker` (`value`, `onChange`, `layout: 'radios' | 'segmented'`): `SpaceLevelControl.tsx` and `SpaceLevelsForm.tsx` (D7).
18. `Tooltip` (`content`, `children`): only the native `title` today (`MarkdownEditor.tsx:565`); the icon-only buttons of the space tree, the course materials, the quiz editor and the resource rows have an `aria-label` and no visible hint. Build it for `Button iconOnly`.
19. Not needed yet: `Tabs`. The only tab set is Write / Preview inside `MarkdownEditor`; `SegmentedControl` covers the other case. Build it when a second tab set comes.

## Proposals for the owner

1. Use the right side of community pages at 1600 px and more. Today posts, chat, pages and profiles stop at 768 px and leave 470 px empty (D10). A right column with the context of the space (description, notification level, members here, pinned posts, the next lesson) would empty the header of each space (pills, notification disclosure) and give the reading column its full height. Cost: a new region in `CommunityShell`, a decision of what goes there per space type, and a fallback under 1600 px (today's layout). It changes the layout of the shell, so it is not in the findings.
2. Raise the step between `background` and `surface` in the dark theme. Today a card is #3f4957 on #37404c with a 10% white border, about 1.1:1; cards are found by their fill only, and at a low screen brightness they melt. A slightly darker background (or a stronger border token) would make the card structure read at once. Cost: one token change and a visual check of every screen in dark; it touches the brand colors, so it is the owner's call.
3. Write the system down where the toolkit (#657) will live: the named text styles of D12, the action rules of D17, the width rule of D10 and the one settings layout of D4. Without these four rules, Storybook will document the current inconsistencies as variants.
