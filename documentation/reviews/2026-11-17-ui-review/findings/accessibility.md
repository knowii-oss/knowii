# Findings: accessibility

## Scope covered

Reference: WCAG 2.2, level A and AA. No real screen reader ran. The "names, roles, states" checks are Playwright accessibility tree snapshots (`ariaSnapshot`) and DOM queries, read as a screen reader user would hear them. No test ran in Safari or Firefox: everything ran in the Chromium of Playwright 1.63.

Tools: the scripts `tools/screen-atlas/a11y-*.mjs`. Their output folder `a11y-out/` is not in the repository; the scripts make it again (`axe.json`, `tree/*.yml`, screenshots `forced-*.png`, `forced2-*.png`, `chat-edit-*.png`, `reflow-*.png`). Every request to a host other than localhost was refused.

1. Automated pass: axe-core 4.10 with the tags `wcag2a wcag2aa wcag21a wcag21aa wcag22aa`, on 55 screen states (15 visitor, 30 member, 10 owner; pages, open dialogs, menus, the emoji picker, the image viewer, the chat thread, error states), each in light and dark, at 1920 and 360 px: 220 runs. I checked the contrast results by hand on the colour values (see A1, A2). Results grouped by cause below.
2. Keyboard only, at 1920 and 360 px: Tab order and focus ring on the home page, sign-in, sign-up, community home, post page, chat, thread panel, gallery and image viewer, new post dialog, user menu, main menu drawer, "Spaces" drawer, bell panel, space notification level, emoji picker, collapse controls, comment write and delete, move buttons of the space tree, "Mark as done".
3. Tree snapshots of 27 screens (visitor, member, owner) at 1920 px: headings, landmarks, names, error states.
4. Reflow at 320 x 640 on 27 screens; text spacing (1.4.12 values, applied with a constructed style sheet because the content security policy blocks inline styles) and root font size 200 % at 1280 px on 18 member screens.
5. Forced colours (`forcedColors: 'active'`, 1280 px) on 12 screens; reduced motion by code reading.
6. Target size on 20 screens at 1920 and 360 px (every control under 24 x 24 px, with the spacing exception).
7. Code reading for 1.3.5, 2.5.7, 3.2.6, 3.3.7, 3.3.8, 4.1.3.

Changes I made in the running application (all normal member actions, all put back):

- As Marc Member, I wrote one comment ("Accessibility review test comment, deleted right after.") on "My tomatoes have yellow leaves" and deleted it. The count is 9 comments again.
- As Olivia Owner, I moved "Off topic" down in the space group "Together" in the settings, then up again. The order is the same as before.
- As Marc Member, I marked the lesson "Watering" done, then not done.
- My visits as member and owner changed read states (notifications, chat, "New content" dots), as any visit does. The seed of the atlas puts them back. I did not run the seed.

Not looked at: the roles new-member, moderator and admin; direct message conversations in depth; the dialogs of the community settings with the keyboard (only axe on "Add a space" and the role menu); the page editor, the quiz editor and course materials editing; the video player; two-factor screens; API tokens (500, known). Screen magnifier use, voice control and real screen readers: not tested.

## What works

1. axe-core finds no violation in the light theme on 55 screen states at 1920 and 360 px, except the list markup of the chat log (A16). Names of buttons and links, labels of fields, `lang="en"`, dialog names, image alternatives: all present.
2. Reflow: 26 of 27 screens have no horizontal scroll at 320 px (exception: A17). Text spacing and 200 % text: no cut or covered text on 18 member screens.
3. Target size: no control under 24 x 24 px without enough spacing, on 20 screens at 1920 and 360 px. Reaction pills are 47 x 28, carets 32 x 32, viewer buttons 44 x 44.
4. Focus ring: every Tab stop I reached shows a 2 px outline in the text colour with an offset (`styles.css:170`), in both themes. No sticky header, so nothing covers the focus at the top of the page.
5. Dialog, drawer ("Menu", "Spaces"), user menu, bell panel, chat thread panel, image viewer, emoji picker (when the focus is inside): Escape closes, and the focus goes back to the opener (`Components/ui/use-return-focus.ts`). Seen in the browser.
6. Icon-only buttons have names with context: "Add a reaction to the message by Olivia Owner", "Close the viewer", "Open the main menu", "Switch to the dark theme".
7. Navigation dots and counts (`SpaceNavigation.tsx:96`): the dot has a border in the text colour (keeps its shape in forced colours) and the link name gets ", New content"; the counts say "unread". Not colour only (1.4.1).
8. Status messages without a focus move: the chat log is `role="log"` and polite; "Lesson done", "Spaces reordered", "Comment deleted", "Saved" (notification settings) are in live regions; toasts sit in a live region that exists before the first toast (`Toaster.tsx`).
9. Toasts and the notification pop-up stop their timer while the pointer or the focus is on them (2.2.1).
10. Reduced motion: every transition of the building blocks has `motion-reduce:transition-none`; skeletons pulse only with `motion-safe`; smooth scrolling stops; videos play only after a click. No motion that cannot be stopped (2.2.2).
11. Ordered lists use Move up and Move down buttons; there is no drag-only action (2.5.7). `PageList.tsx:150` and `CourseMaterials.tsx` keep the focus on the button with `aria-disabled`.
12. Sign-in (3.3.8): correct `autocomplete` values, paste is allowed, "Show password", a sign-in link by email as an alternative, `one-time-code` on the two-factor field.
13. The emoji picker grid works with the arrow keys, Enter picks, Escape closes and gives the focus back, and the search field announces the active emoji through `aria-activedescendant`.
14. The image viewer: arrow keys, labelled buttons, "Image 3 of 4" text, focus back on the tile.
15. "Mark as done" moves the focus to the new "Mark as not done" button and announces "Lesson done".
16. Community pages have one h1, named landmarks ("Breadcrumb", "Atlas Garden spaces", "Lessons of the course"), and labelled lists.
17. Newer dialogs link the error to the field (`aria-invalid` and `aria-describedby`), and the focus goes to the field in error (seen in "Add a resource").

## Findings

### A1 Dark theme: pink accent text is below 4.5:1 (links, mentions, "Pinned", "N replies", "New messages")

- Weight: must
- Screen: Discussion space (`/community/atlas-garden/space/garden-talk`), post pages, chat (`/space/lounge`), legal pages, home page; member and visitor; 1920 and 360; dark
- What is wrong: the token `--color-secondary-text` (#ff1493) is used for normal-size text in the dark theme. Measured: links and mentions in member content 2.51:1 on the surface (#3f4957), 2.88:1 on the background, 3.55:1 in the chat well (#2b323c); the "Pinned" label (12 px bold) 2.5:1; the chat button "3 replies" (14 px bold) 3.05 to 3.55:1; the "New messages" separator in the chat; links of the legal pages 2.5:1; the "ii" in the home page sentence (20 px bold) 2.88:1. The gradient word of the home headline (`text-gradient-brand`, 60 px bold) goes from 2.65:1 (#a855f7) to 2.89:1 (#ff1493) on the dark background, under the 3:1 for large text. The token comment itself says "large or decorative text only (2.89:1)". The light theme passes (#c20066, 6:1).
- Why it matters: 1.4.3 Contrast (Minimum), AA. Links and mentions are on every post and chat message.
- Proposed fix: give the dark theme a text-safe value for `--color-secondary-text` (4.5:1 or more on background, surface, surface-elevated and well; check it on #475363, the darkest case), or use `text-primary` with the underline for links in `MarkdownContent.tsx:116` and keep the pink for icons. Places: `styles.css:47`, `Components/ui/MarkdownContent.tsx:116`, `Components/Posts/PostCard.tsx:23`, `Pages/Posts/Show.tsx:114`, `Components/Chat/ChatMessageItem.tsx:121`, `Components/Chat/ChatRoom.tsx:383`, `Pages/Welcome.tsx:49`, `styles.css:221`.
- Evidence: `a11y-out/axe.json` (rule color-contrast, keys `*/dark`); hand check: #ff1493 has luminance 0.239, #3f4957 has 0.065, ratio (0.289 / 0.115) = 2.51.
- Seen in: browser, code
- Confidence: high

### A2 Dark theme: muted text on raised surfaces is 4.32:1 (bell panel, emoji picker, menus)

- Weight: must
- Screen: Bell panel (`/dashboard`, bell open), emoji picker on a post, user menu; member; 1920 and 360; dark
- What is wrong: `text-muted` (white at 64 %) on `bg-surface-elevated` (#475363) gives #bdc1c7 on #475363 = 4.32:1. In the bell panel this is the text of every notification line, the space name and the time; in the emoji picker the section names; in the user menu the email line (same pair, by code, `DropdownMenu.tsx:40`). The token comment gives 4.85:1 on `surface`, but the raised surface is lighter.
- Why it matters: 1.4.3 Contrast (Minimum), AA, on the main path "read notifications".
- Proposed fix: raise the dark `--color-muted` to white at 70 % (about 4.8:1 on #475363, more on the other surfaces). One token: `styles.css:42`.
- Evidence: axe `member/bell/1920/dark` (21 nodes), `member/post-emoji/*/dark` (11 nodes); hand check: luminance 0.531 against 0.084, ratio 4.32.
- Seen in: browser, code
- Confidence: high

### A3 Emoji picker stays open when the focus leaves it, and covers the controls that get the focus

- Weight: must
- Screen: Post (`/community/atlas-garden/space/garden-talk/post/my-tomatoes-have-yellow-leaves`), and every reaction bar (chat, comments); member; 360 and 1920; both themes
- What is wrong: after Tab leaves the picker, it stays open. Escape then does nothing, because only the search field listens for Escape. Only a pointer click outside closes it. At 360 px the picker is a sheet fixed to the bottom of the screen: the next four Tab stops ("See who reacted", "Collapse all", "Collapse the thread of Tomas Silva", "👍: 1 reaction") are fully under the sheet. At 1920 px the open picker covered the Bold and Italic buttons of the reply form when the focus reached them later.
- Why it matters: 2.4.11 Focus Not Obscured (Minimum), AA; a keyboard user cannot close the layer without going back into it.
- Proposed fix: in `Components/Reactions/EmojiPicker.tsx`, close on `focusout` when the new focus is outside the container (next to the pointer listener at line 102), and handle Escape on the container, not only on the input (line 213). Or make the sheet modal at small widths, with a focus trap.
- Evidence: steps: focus "Add a reaction to this post", Enter, Tab x 6 (`node a11y-keys.mjs mobile --width 360`, `node a11y-keys.mjs emoji`); output "sheet still open 1" and "COVERED:DIV" on each stop.
- Seen in: browser, code
- Confidence: high

### A4 A button with `loading` becomes `disabled`, and the keyboard focus falls to the page body

- Weight: must
- Screen: Post page (send a comment), Sign-in (`/login`, failed sign-in), Community settings (`/community/atlas-garden/settings`, move buttons), and every form that uses `Button loading`; all roles; all widths
- What is wrong: `Components/ui/Button.tsx:86` sets `disabled={disabled || loading}`. The browser removes the focus from a disabled button. Seen: after Enter on the comment submit button, the focus is on `BODY` during and after the request; after a failed sign-in, the focus is on `BODY`; in the space tree, `SpaceTreeEditor.tsx:120,130,183,193` disables every move button while `busy`, so after "Move Off topic down" the focus is on `BODY`. The next Tab starts again from the top of the page.
- Why it matters: 2.4.3 Focus Order, A. Keyboard and screen reader users lose their place after each save on the main paths (comment, sign-in, settings).
- Proposed fix: in `Button`, use `aria-disabled` while loading and ignore clicks, keep the element focusable. The team already does this in `PageList.tsx:150` and `CourseMaterials.tsx` (comment "a disabled button loses the focus"). Same change in `SpaceTreeEditor.tsx`.
- Evidence: `node a11y-keys.mjs comment`, `node a11y-move.mjs`; output `during {"tag":"BODY"}`, `after {"tag":"BODY"}`.
- Seen in: browser, code
- Confidence: high

### A5 Focus is lost after "Collapse all", "Expand all" and after deleting a comment

- Weight: should
- Screen: Post (`.../post/my-tomatoes-have-yellow-leaves`); member; 1920; light
- What is wrong: "Collapse all" disables itself when every thread is collapsed, and "Expand all" does the same (`Components/Comments/CommentSection.tsx:340-352`): the focus goes to `BODY`. After "Delete" in the comment dialog, the dialog closes, the button that opened it is gone with the comment, and `use-return-focus.ts` has nothing to return to: the focus goes to `BODY`.
- Why it matters: 2.4.3 Focus Order, A. The user must find the comments again from the top of the page.
- Proposed fix: after Collapse all, move the focus to "Expand all" (and the reverse), or keep both buttons enabled with `aria-disabled`. After a delete, put the focus on the next comment, the previous one, or the heading "N comments" (`#comments-title`, give it `tabIndex={-1}`).
- Evidence: `node a11y-keys.mjs collapse` (after Collapse all: BODY; after Expand all: BODY); `node a11y-keys.mjs comment` (after delete: BODY).
- Seen in: browser, code
- Confidence: high

### A6 No status message after a comment is posted

- Weight: should
- Screen: Post page; member; 1920; light
- What is wrong: after the comment is sent, no live region gets text (the three polite regions of the page are empty), and the focus is on the page body (A4). A screen reader user hears nothing and does not know whether the comment was posted. The delete gives "Comment deleted"; the post gives nothing.
- Why it matters: 4.1.3 Status Messages, AA.
- Proposed fix: announce "Comment posted" in the toaster or a polite region, or move the focus to the new comment (the comment items already take focus, `CommentSection.tsx:255`). Not verified for a new post or a resource: same check needed.
- Evidence: `node a11y-keys.mjs comment`, line "live regions [ '[polite] ', 'status[polite] ', 'status[polite] ' ]" after the send.
- Seen in: browser
- Confidence: high

### A7 Errors of the sign-up, sign-in and account forms are not linked to their fields

- Weight: should
- Screen: Sign-up (`/register`), Sign-in (`/login`), Forgot password, Reset password, Confirm password, Profile (`/user/profile`: profile information, password, browser sessions, delete account); visitor and member; all widths
- What is wrong: after a failed submit, the fields have no `aria-invalid` and no `aria-describedby`. The error text is a `role="alert"` paragraph under the field, so it is spoken once, but when the user goes back to the field, the field does not say that it is invalid or what the error is. The newer dialogs do it right (`CreateResourceDialog.tsx`, `SpaceDialog.tsx`). The input border turns red only through `aria-invalid` (`form-field.ts`), so on these pages there is no visual mark on the field either.
- Why it matters: 3.3.1 Error Identification (met by the text), 1.3.1 Info and Relationships and 4.1.2 Name, Role, Value (the relation and the state are missing). Sign-up is a main path.
- Proposed fix: pass `invalid` and `aria-describedby` with an id on `InputError`, as the dialogs do. Better: one building block that does it (see "Missing building blocks"). Files: `Pages/Auth/Register.tsx:75-140`, `Pages/Auth/Login.tsx:99-129`, `Pages/Auth/ForgotPassword.tsx`, `Pages/Auth/ResetPassword.tsx`, `Pages/Auth/ConfirmPassword.tsx`, `Pages/Profile/Partials/UpdateProfileInformationForm.tsx`, `Pages/Profile/Partials/UpdatePasswordForm.tsx`, `Components/SpacePages/LessonQuiz.tsx`.
- Evidence: `node a11y-errors.mjs`: `/register` four errors shown, every field `invalid=null describedby=null`; `/login` the same; `/forgot-password` the same.
- Seen in: browser, code
- Confidence: high

### A8 Sign-in, sign-up, dashboard, profile and community settings have no h1

- Weight: should
- Screen: `/login`, `/register`, `/forgot-password` (no heading at all), `/dashboard`, `/user/profile`, `/community/atlas-garden/settings` (h2 only); visitor, member, owner; all widths
- What is wrong: a screen reader user who jumps to the first heading or lists headings finds no page heading. On sign-in and sign-up the page has only the logo, fields and buttons: nothing says "Sign in" or "Create your account" as a heading. `PageTitle.tsx` renders the h1 only when a page gives `pageTitle`; these pages give only breadcrumb items. Also: the resource list goes from h1 to h3 (`ResourceCard.tsx:29`).
- Why it matters: 1.3.1 Info and Relationships and 2.4.6 Headings and Labels, AA.
- Proposed fix: give an h1 to `AuthenticationCard` (it can be visually the card title) and a `pageTitle` (or a visually hidden h1) to Dashboard, Profile, Settings. Use h2 for the resource cards or add an h2 above the list.
- Evidence: `a11y-out/tree.log` ("h1":0 for these pages); `a11y-out/tree/visitor-register-1920.yml`.
- Seen in: browser, code
- Confidence: high

### A9 The post list holds the full body of each post, with its headings at the level of the post titles

- Weight: should
- Screen: Discussion space (`/community/atlas-garden/space/garden-talk`); member and visitor; all widths
- What is wrong: `PostCard.tsx:39` renders the full `body_html` and only clips it visually with `line-clamp-4`. A screen reader reads the whole body of every post in the list, and the headings inside a post are h2, the same level as the post titles: the list "A full guide to the first season on a balcony" adds the h2 headings "1. Light", "2. Pots" ... "7. The harvest" next to the real post titles.
- Why it matters: 1.3.1 Info and Relationships; the heading navigation of the space page is wrong, and the list is very long to hear. Any link inside the clipped part stays in the Tab order while it is invisible (none in the seed: not verified in the browser).
- Proposed fix: render a plain-text excerpt for the card (server side or by stripping blocks), or mark the clipped body `aria-hidden` and `inert` with a short accessible summary.
- Evidence: `a11y-out/tree.log`, entry `member/discussion`.
- Seen in: browser, code
- Confidence: high

### A10 No skip link: 18 Tab stops before the content of a community page

- Weight: should
- Screen: Community home, every space and post page (`/community/atlas-garden/...`); member; 1920; both themes
- What is wrong: there is no "Skip to content" link. On the community home and in the chat, the first content control is Tab stop 19: logo, theme, bell, main menu, user menu, two breadcrumb links, then "Messages" and ten space links of the sidebar. At 360 px the sidebar is in the drawer, so it is shorter.
- Why it matters: 2.4.1 Bypass Blocks, A. The landmarks (`main`, `nav`) meet it for screen reader users, but a sighted keyboard user has no landmark keys.
- Proposed fix: one skip link as the first element of `Layouts/AppLayout.tsx` (visible on focus), to an `id` on the `main` of `PageContentWrapper.tsx`, or to the start of the space content in `CommunityShell.tsx`.
- Evidence: `node a11y-keys.mjs member-community` and `chat`: tab19 is the first content control.
- Seen in: browser, code
- Confidence: high

### A11 Forced colours: checked checkboxes and radio buttons look unchecked

- Weight: should
- Screen: Quiz (`/space/balcony-course/page/quiz-water-and-compost`), Sign-up (age and terms box), Notification settings ("Show pop-ups"); member and visitor; 1280; forced colours
- What is wrong: `Checkbox.tsx` and `Radio.tsx` draw the control with `appearance-none`; the checked state is a magenta fill, a white check icon and a magenta dot. Forced colours remove the fill and the dot, and the white check goes with the background: in the screenshots the checked "In the morning", "Dry leaves" and the checked sign-up box look the same as the unchecked ones. Filled buttons (Register, Send) show no border either and look like plain text.
- Why it matters: users of high-contrast modes cannot see their own answers and choices. Related to 1.4.11 Non-text Contrast (the state indicator disappears); WCAG does not test forced colours directly.
- Proposed fix: add `forced-colors:appearance-auto` on the two inputs (the system then draws them), or `forced-colors:checked:bg-[CanvasText]` for the box and the dot. Give `Button` a transparent border so it shows in forced colours (`border border-transparent`).
- Evidence: `a11y-out/forced2-quiz.png`, `a11y-out/forced2-register-checked.png` (Playwright `check()` confirmed the checked state).
- Seen in: browser, code
- Confidence: high

### A12 Chat at 360 px and under zoom: the edit box is shown through a small window of the message log (known finding, verified)

- Weight: should
- Screen: Chat space (`/community/atlas-garden/space/lounge`), state "edit"; member; 360 and 320; light
- What is wrong: what I see in the browser: the chat is a box of fixed height (`ChatRoom.tsx:344`, `h-[70vh] min-h-96`). The composer below the log takes most of it, so at 360 x 800 the log is 233 px high, and the inline edit form (tabs, toolbar of two rows, field, help text, Save and Cancel) is 515 px high. The log scrolls so that the field shows: the tabs and the toolbar are cut off at the top, the help text and the buttons are below the bottom. The edit field is 56 px high while its text needs 76 px, so the third line of the message ("over in the wind") is cut. At 320 x 640 (the size of a 400 % zoom) the log is only 71 px high. Nothing is lost: the log and the field scroll. So the picture `chat/message--edit--member--360--light.png` shows the log window, not a broken edit box.
- Why it matters: 1.4.10 Reflow asks for no loss of content or function at 320 px; vertical scrolling in a region passes the letter, but at zoom the user edits through a 71 px slit. Low-vision users on the main path "chat".
- Proposed fix: while a message is edited, hide or collapse the main composer, show a compact edit form (no help text, one toolbar row), let the edit field grow with its text, and scroll the whole edit item into view. At small heights, drop the fixed `70vh` and let the page scroll.
- Evidence: `node a11y-chat360.mjs`: `360 {"log":[429,233],"editItem":[378,515],"textarea":[528,56,"scrollH",76]}`, `320 {"log":[478,71]...}`; `a11y-out/chat-edit-320.png`.
- Seen in: browser, picture, code
- Confidence: high

### A13 Profile fields: wrong or missing input purpose

- Weight: should
- Screen: Profile (`/user/profile`); member; all widths
- What is wrong: the Knowii user name field has `autocomplete="username"` (`UpdateProfileInformationForm.tsx:300`), the same value as the email field above it (line 258). A password manager can write the email address into the user name field. The phone field has no `autocomplete="tel"` (line 343-353); the social link fields (website and others) have no `autocomplete="url"`.
- Why it matters: 1.3.5 Identify Input Purpose, AA, on "change account settings"; and a filled-in wrong user name is saved without the user seeing it.
- Proposed fix: `autocomplete="email"` or keep `username` on the email field only; `autocomplete="off"` (or `nickname`) on the Knowii user name; `tel` on phone; `url` on the website field.
- Evidence: `Pages/Profile/Partials/UpdateProfileInformationForm.tsx:241,258,300,343-353`.
- Seen in: code
- Confidence: high

### A14 Code blocks and tables that scroll sideways cannot get the keyboard focus

- Weight: later
- Screen: Discussion space and posts with code (360 px), legal pages with tables (360 px); member and visitor
- What is wrong: `pre` blocks and the `table` of the legal pages scroll sideways at 360 px but have no `tabindex`. Current Chromium and Firefox make such scrollers focusable by themselves; Safari does not.
- Why it matters: 2.1.1 Keyboard, A, for Safari keyboard users.
- Proposed fix: in `MarkdownContent.tsx`, give `tabindex="0"`, `role="region"` and a name ("Code", "Table") to `pre` and wide tables when they overflow.
- Evidence: axe rule `scrollable-region-focusable` on `visitor/legal/360/*` and `member/discussion/360/*`.
- Seen in: browser
- Confidence: medium

### A15 Two pages have the same title "Notifications - Knowii"

- Weight: later
- Screen: Notifications (`/notifications`) and Notification settings (`/user/notifications`); member
- What is wrong: both tabs and history entries read "(1) Notifications - Knowii".
- Why it matters: 2.4.2 Page Titled, A (titles should describe the page).
- Proposed fix: `browserPageTitle="Notification settings"` in `Pages/Profile/Notifications.tsx:54`.
- Evidence: `a11y-out/tree.log` (title of `member/notifications` and `member/notif-settings`).
- Seen in: browser, code
- Confidence: high

### A16 The chat log puts `role="log"` on the list, so the items are no longer list items

- Weight: later
- Screen: Chat space and thread panel; member, owner; all widths and themes
- What is wrong: `<ol role="log">` replaces the list role, and its `<li>` children are invalid (axe `listitem`, 13 nodes in the room, 5 in the thread).
- Why it matters: 1.3.1 Info and Relationships, A; screen readers can drop the item count.
- Proposed fix: put `role="log"` on a wrapping `div` and keep the `ol` inside (`ChatRoom.tsx:365`, `ThreadPanel.tsx:76`).
- Evidence: axe `member/chat/*`, `member/chat-thread/*`.
- Seen in: browser, code
- Confidence: high

### A17 Breadcrumb of a resource page is 6 px too wide at 320 px

- Weight: later
- Screen: Resource (`/community/atlas-garden/space/reading-list/resource/balcony-vegetables-guide`); member; 320
- What is wrong: the last breadcrumb item has no maximum width below `sm` and pushes the page to 326 px: a small horizontal scroll.
- Why it matters: 1.4.10 Reflow, AA.
- Proposed fix: `min-w-0` on the last `li` and a base `max-w-full` on its label in `Components/ui/Breadcrumb.tsx:51`.
- Evidence: `node a11y-reflow.mjs reflow member`, line `member/resource {"scrollW":326,...}`.
- Seen in: browser
- Confidence: high

### A18 The emoji search field has almost no visible border

- Weight: later
- Screen: Emoji picker; member; all widths; both themes
- What is wrong: the field uses `border-border bg-background` (`EmojiPicker.tsx:252`): a 10 % border (about 1.3:1) on the raised panel, unlike the other fields (`border-border-strong`, 3.2:1 or more).
- Why it matters: 1.4.11 Non-text Contrast, AA. The placeholder and the autofocus soften it.
- Proposed fix: use `formControlClasses` from `form-field.ts`.
- Evidence: `Components/Reactions/EmojiPicker.tsx:252`; tokens `styles.css:54,110` (border at 10 %). Ratio computed from the tokens, not measured on a picture.
- Seen in: code
- Confidence: medium

### A19 The toast region is a landmark named "Notifications" on every page

- Weight: later
- Screen: every page; all roles
- What is wrong: `Toaster.tsx:81` renders `<section aria-label="Notifications">`, an empty region in the landmark list of every page, with the same name as the bell, the bell panel and the notifications page.
- Why it matters: 2.4.6 Headings and Labels; screen reader users who go to "Notifications" by landmark land in an empty region.
- Proposed fix: name it "Messages of the application" or "Alerts", or drop the `section` (a plain `div` with the live list is enough).
- Evidence: `a11y-out/tree/*.yml`, first line `region "Notifications"`.
- Seen in: browser, code
- Confidence: high

## Missing building blocks

1. A form field block (label, control, hint, error) that sets `id`, `aria-invalid` and `aria-describedby` itself. Today each page wires it by hand, and the older pages forget it: `Pages/Auth/Register.tsx`, `Login.tsx`, `ForgotPassword.tsx`, `ResetPassword.tsx`, `ConfirmPassword.tsx`, `Pages/Profile/Partials/UpdateProfileInformationForm.tsx`, `UpdatePasswordForm.tsx`, while `CreateResourceDialog.tsx` and `SpaceDialog.tsx` do it right. Belongs in `Components/ui` next to `TextInput` (A7).
2. A "focus after removal" helper: when an item with the focus is removed (comment delete, collapse), move the focus to the next item, the previous one or the list heading. Needed in `CommentSection.tsx`, chat message delete in `ChatRoom.tsx`, notification "Done" in `NotificationsPanel.tsx` (not tested), resource delete (A5).
3. An "announce" call (one polite region) for results that do not show a toast: comment posted, post published, message sent in a thread (A6). `PageList.tsx`, `CourseMaterials.tsx` and `SpaceTreeEditor.tsx` each have their own status region today.
4. A scrollable region wrapper for wide content in `MarkdownContent` (A14).

## Proposals for the owner

1. Dark theme accent text. The pink #ff1493 is the brand colour, but as text on the dark slate surfaces it reaches 2.5 to 3.5:1 (A1). The fix needs one decision on the look: either a lighter pink for text in the dark theme only (fills and buttons keep #e5007d), or links in the text colour with the underline, and pink only for icons and fills. I recommend the first: it keeps the brand and it is one token.
2. A skip link in the shell (A10). It is part of the layout, so I name it here too: visible only when it has the focus, it does not change the look.
