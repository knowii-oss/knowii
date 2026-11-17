# UI, design and UX review of the Knowii Platform

The review looked at the whole Knowii Platform as one product: every page and state, for a visitor, a new member, a member, a moderator, an admin and an owner, at 1920 px and 360 px, in both themes. It ran on 2026-11-17, on the commit `382ded2d` of `knowii-oss/knowii` (issue #1523). The base was a screen atlas of 270 screens and 1 068 pictures. Five reviewers worked from it: a newcomer, an experienced member, an editor for the words, an accessibility reviewer with a headless browser, and a design critic. The review lead checked the strongest findings in the code and in a headless browser.

How to read the IDs: N (newcomer), M (experienced member), W (words), A (accessibility), D (design) are the IDs in the reviewer files. R-M, R-S and R-L are the merged findings of this report. In "What works", "N/4" is item 4 of the "What works" list of the newcomer.

## Limits of this review

1. No real screen reader ran. The accessibility reviewer read accessibility tree snapshots. All browser tests ran in Chromium only: no Safari, no Firefox. Screen magnifiers and voice control were not tested.
2. Four of the five reviewers worked from pictures and code, without a browser. What they say about the keyboard, phones and hover comes from the code.
3. Eight of 1 076 pictures are missing. The API tokens screen was not reviewable: the atlas build answered 500 there (fixed during the review, see below).
4. The moderator, admin and new-member roles were covered less than the member and the owner. The accessibility reviewer did not test the new-member, moderator and admin roles.
5. The review ran on local seed data, not on a real community with real load.
6. The atlas ran in the `local` environment. There a 403, a 404 and a 500 show the framework error page, not `Pages/Error.tsx`. Findings on the error page come from the code only.
7. The bot check of the sign-up page and the waitlist form comes from an outside service that the atlas tool blocked. Its place is empty in the pictures.
8. Some states are not in the atlas: a community "by invitation" with a public page (R-S9), a public space where members post (R-S20), the error state of a notification action (R-M10).
9. Possible defect of the atlas, not of the product: the "Catch up" card is missing in the 1920 px dark pictures of the community home. The seed makes the member "away for nine days", a visit clears that, and the dark runs came later. It is not reported as a finding.
10. What each reviewer did not look at:
    1. Newcomer: no browser, so no real sign-up flow (email, return to the community); courses, quizzes and resources pages; the composer at 360 px at full size; the keyboard and screen readers.
    2. Experienced member: the dark theme; the sign-in and account screens; API tokens; link spaces; the moderator and admin pictures one by one.
    3. Words: the 360 px and dark pictures; the legal pages (owner-approved texts); the seed data; the email templates (only their subjects); the API documentation. Texts that the server sends at run time are judged from the code.
    4. Accessibility: direct message conversations in depth; the dialogs of the community settings with the keyboard (only axe-core on "Add a space" and the role menu); the page editor, the quiz editor and course materials; the video player; the two-factor screens.
    5. Design: the error pages, the home page and the legal pages in pictures; the two-factor screens; the image viewer; the emoji picker; the quiz editor. Hover and focus looks come from the code only.
11. The accessibility reviewer made normal member actions in the running application and put them back (one comment written and deleted, a space moved down and up, a lesson marked done and not done). The visits changed read states; the seed of the atlas puts them back.

## What works

### Arrival and sign-in

1. The public page of a community shows the name, a one-line description, "Sign in" and "Sign up to join", and the newest posts with author, space and time (N/1, M/16).
2. The public discussion space says who reads and who writes: "News from the team. Everybody can read, the team writes." and the chips "Public" and "Only moderators can post" (N/2).
3. A page of a public pages space at 360 px shows "Page 1 of 4", the title and a large "Next" card (N/3).
4. Sign-in by link says "It works once, for 15 minutes", and the message after sending does not tell a stranger whether an account exists (N/4).
5. Sign-in has correct `autocomplete` values, allows paste, has "Show password", a sign-in link by email as an alternative, and `one-time-code` on the two-factor field (A/12).
6. The sign-up agreement is one checkbox: "I am 16 or older and I agree to the Terms of Service and Privacy Policy"; each error shows under its field (N/5).
7. The sign-in pages share one card, one width and the compact footer (D/13).

### Empty states

8. The empty discussion, resources and chat spaces say what to do next and put the action next to it: "No posts yet. Start the conversation: write the first post." (N/6, N/7, N/8).
9. The empty states of the six space types use one component with the same parts: emoji, title, one sentence (D/6).
10. The empty notifications page: "Nothing needs you. Everything is quiet. That is fine." (N/9).

### Reading and writing

11. The emoji picker has search, quick reactions, frequently used, a skin tone that stays, and arrow keys in the grid; Enter picks, Escape closes and gives the focus back (M/1, A/13).
12. A comment permalink ("Copy link") scrolls to the comment, focuses it and highlights it (M/2).
13. Threads collapse one by one, with "Expand all" / "Collapse all", and a click on the thread line (M/3).
14. New posts wait behind a "N new posts" button, so the list does not move under the reader (M/4).
15. The Markdown editor takes pasted and dropped images, has a preview, Ctrl+B/I/K/E, and makes a gallery of consecutive images (M/7).
16. The image viewer works with the arrow keys, has labelled buttons and "Image 3 of 4", and gives the focus back to the tile (A/14).
17. The image description prompt says why: "Screen readers read this text. Describe what matters in the image." (W/9).

### Chat

18. Enter sends, Up arrow edits the last message, a typing indicator shows, and a thread has a summary "5 replies · last about 1 hour ago" (M/5).
19. A deleted chat message leaves a marker (M/6).
20. The chat log is `role="log"` and polite (A/8).

### Courses

21. The course outline shows sections, a check per done lesson, "3 of 8 done" and one "Continue" (M/8, D/9).
22. The quiz review shows your answer, the correct answer and the explanation, with "Try again"; "Correct" and "Not correct" use an icon and a word, not color only (M/9, D/10).
23. "Next" on a lesson also marks it done (M/10). "Mark as done" moves the focus to "Mark as not done" and announces "Lesson done" (A/15).

### Notifications

24. A notification line has "Done" with an undo of 8 seconds, mute of the thread or space from its menu, and a modifier click opens a new tab (M/11).
25. Notification levels per space, with "Same for all spaces of this group" (M/12).
26. "Why did I get this?" answers in one sentence per reason (W/4).
27. The notification settings say "Times show in Central European Standard Time (Europe/Brussels)" and "Only you can see it and change it." (N/10).
28. The bell panel has a skeleton while it loads and a message with "Retry" when the request fails (D/8).

### Account and privacy

29. Bio, location and phone each have a box "Visible to the members of my communities"; the phone is off by default (N/11).
30. The profile of another member does not show the email address and offers "Send a message" and "Block" (N/13).
31. The texts of two-factor authentication, connected accounts, time zone, online status and quizzes are short and active (W/7).

### Community and settings

32. The About page answers "who can do what here": "Team", "What moderators can do", "What you can do" (N/12).
33. Moving an "Access groups only" space to another space group asks to confirm the change of readers (M/13).
34. The "Remove a member" dialog says what the person loses, what stays and how they can come back (M/14, W/2).
35. "Transfer the ownership" says what the new owner can do and what the old owner becomes (W/3).
36. The search engines setting says what becomes visible and what never does (W/6).
37. The space tree editor works at 1920 px and at 360 px (D/7). Ordered lists use "Move up" and "Move down" buttons: no drag-only action (A/11).

### Dialogs

38. The delete dialogs of a post, a comment, a resource, a space, a chat message, a lesson and a course material say what goes and that it cannot be undone; the button says "Delete" (W/1).
39. Every delete dialog puts the first focus on "Cancel" and uses the `danger` variant (M/15, D/4).
40. Dialog buttons have one order: "Cancel" left, the action right; at 360 px the dialog is a bottom sheet with full-width buttons (D/3).
41. Dialogs, drawers, the user menu, the bell panel, the thread panel and the image viewer close on Escape, and the focus goes back to the opener (A/5).

### Accessibility basics

42. axe-core finds no violation in the light theme on 55 screen states at 1920 and 360 px, except the chat log list (R-L23) (A/1).
43. Reflow: 26 of 27 screens have no horizontal scroll at 320 px; text spacing and 200 % text cut nothing (A/2, N/14).
44. No control is under 24 x 24 px without enough spacing (A/3).
45. Every Tab stop shows a 2 px focus ring in both themes; no sticky header covers it (A/4).
46. Icon-only buttons and repeated buttons have names with context: "Delete the comment by Lena Fischer", "Close the viewer" (A/6, W/5).
47. The "new content" dots and the counts are not color only: the link name gets ", New content" and counts say "unread" (A/7).
48. Status messages use live regions ("Lesson done", "Spaces reordered", "Comment deleted"); toasts and the notification pop-up stop their timer under the pointer or the focus (A/8, A/9).
49. Reduced motion stops every transition; videos play only after a click (A/10).
50. Community pages have one h1 (hidden on some, see R-S15), named landmarks and labelled lists (A/16).
51. The newer dialogs link errors to fields and move the focus to the field in error (A/17); the newer validation messages say what to do: "Write the question", "Enter a whole number" (W/8).
52. The error boundary says what happened and what to do (W/10).

### Visual system

53. The token set is closed (the default palette is removed), and each color token notes its contrast (D/1).
54. The dark theme (the default) is as complete as the light one on every screen the design reviewer opened (D/2).
55. One frame lines up the header, the title bar, the content and the footer at every width (D/11). The breadcrumb folds its middle items at 360 px (D/12, N/14).

Left out: design item D/5 ("`Button` has one busy look, spinner and disabled"). The accessibility reviewer showed in the browser that this disabled state drops the focus (R-M4).

## Fixed during the review

1. The production build shipped the spec files as pages, and `/user/api-tokens` answered 500 for every account. Issue #1552, pull request #1553, merged. The atlas pictures `account/api-tokens--*` show the error page of the old build.
2. A timer of the Markdown editor was never cleared and failed test runs now and then. Issue #1554, pull request #1556, merged.

## Findings

Every finding has its sources and how it was checked. "Checked by one reviewer" means that nobody else confirmed it. Where two reviewers gave different weights, the higher one is kept and the finding says so.

### Must fix before launch

#### R-M1 A shared link to a members-only space or a private community ends on a bare "403: Forbidden" page

- Screen: Space of a community, not a member (`/community/atlas-garden/space/garden-talk`), new-member, 1920 and 360, light; Community of other people (`/community/atlas-empty`), new-member, 360, light.
- What is wrong: a signed-in person who is not a member opens a link to a members-only space of an open community. Outside the `local` environment the answer is `Pages/Error.tsx`: "403: Forbidden", "Sorry, you are forbidden from accessing this page. ⛔" and one button "Go back to the homepage", which leads to the "Coming soon" page. The page has no "Join", no link to the public page of the community, and no word about an invitation.
- Why it matters: a link from a friend is the most common way in. The community is open, so the person is one click from joining, and the product shows an error page instead. Many people stop here.
- Proposed fix: for a space or a community that the person cannot read, render a "no access" page, not `Pages/Error.tsx`: the name of the community, the reason in one line ("This space is for the members of Atlas Garden"), and the next step: `JoinCommunity` when `join_policy` is `open`, else "This community is by invitation. Ask a member for an invitation." and a link to its public page. Keep the 403 status. Build it as the "no access panel" building block (see "Missing building blocks").
- Evidence: `Pages/Error.tsx:24-29,52`; `app/Http/Controllers/Inertia/SpaceController.php:40`; `bootstrap/app.php:307-308`; pictures `discussion/space--forbidden--new-member--1920--light.png`, `errors/forbidden--full--new-member--360--light.png` (the local environment shows the framework page).
- Sources: N1.
- Checked: by the lead in the code.
- Confidence: high.

#### R-M2 Dark theme: links, mentions and small labels in the accent pink are below 4.5:1

- Screen: Discussion space (`/community/atlas-garden/space/garden-talk`), Post with comments, replies and reactions, Chat space (`/community/atlas-garden/space/lounge`), legal pages, Home page (`/`); member and visitor; 1920 and 360; dark (the default theme).
- What is wrong: `styles.css:47` marks `--color-secondary-text` (#ff1493) as "large or decorative text only (2.89:1 on the background)", but it colors small text: links and @mentions in every post, comment and message (2.51:1 on the surface #3f4957, 2.88:1 on the background, 3.55:1 in the chat well), "Pinned" (12 px bold, 2.5:1), the chat buttons "3 replies" and "Reply in thread" (3.05 to 3.55:1), the "New messages" divider, the links of the legal pages (2.5:1). The gradient word of the home headline (60 px bold) goes from 2.65:1 to 2.89:1, under the 3:1 for large text. The light theme passes (#c20066, 6:1).
- Why it matters: links and mentions are on every post and chat message, in the default theme.
- Proposed fix: a decision of the owner (decision 2): a text-safe dark value for `--color-secondary-text` (4.5:1 or more on background, surface, surface-elevated and well; #475363 is the hardest case), or links and mentions in `text-primary` with an underline and the pink for icons and fills only. Places: `styles.css:47`, `styles.css:221`, `Components/ui/MarkdownContent.tsx:116`, `Components/Posts/PostCard.tsx:23`, `Pages/Posts/Show.tsx:114`, `Components/Chat/ChatMessageItem.tsx:121`, `Components/Chat/ChatRoom.tsx:383`, `Pages/Welcome.tsx:49`. Show before and after pictures to the owner.
- Evidence: axe-core rule `color-contrast` on the dark runs (`tools/a11y-out/axe.json`); hand check: #ff1493 has luminance 0.239, #3f4957 has 0.065, ratio 2.51; pictures `discussion/space--full--member--1920--dark.png` ("Pinned", mentions), `chat/space--full--member--1920--dark.png` ("Reply in thread").
- Sources: A1, D1. Two reviewers found it independently.
- Checked: by the lead in the code; measured by the accessibility reviewer in the browser.
- WCAG: 1.4.3 Contrast (Minimum), AA.
- Confidence: high.

#### R-M3 The post composer and the page and lesson editors throw text away without a question

- Screen: New post, composer open (`/community/atlas-garden/space/garden-talk`), member; Page in edit mode (`/community/atlas-garden/space/welcome/page/community-guidelines`), owner; Lesson in edit mode (`/community/atlas-garden/space/balcony-course/page/watering`), owner; all widths.
- What is wrong: posts, pages and lessons are written in a `Dialog`. Escape, a click outside the panel, or "Cancel" closes it with no question, and nothing is kept: the composer resets the form when it opens again. Escape also closes the mention list and the emoji list inside the editor, so a second Escape is a likely slip.
- Why it matters: a long post or a lesson is lost with one key or one click beside the dialog.
- Proposed fix: an option in `Components/ui/Dialog.tsx` that asks "Discard your text?" before it closes when the form has changes; use it in `PostComposer`, `PageEditor`, `SpaceDialog`, `CreateResourceDialog`, `QuizEditor`. Keep the text of an unsent post per space in local storage until it is published. Draft posts as a feature are #1405; this is only the guard against loss.
- Evidence: `Components/Posts/PostComposer.tsx:54-63,93-96`; `Components/SpacePages/PageEditor.tsx:101-110`; `Components/ui/Dialog.tsx:18-20,72`. Steps of the lead (member, space "Garden talk", 1280 px): "New post", type a title and about 190 characters, press Escape: the dialog closes with no question; "New post" again: the title and the text are empty. A click outside the dialog does the same.
- Sources: M2.
- Checked: by the lead in the browser.
- Confidence: high.

#### R-M4 A button in its busy state drops the keyboard focus to the page body

- Screen: Post with comments, replies and reactions (send a comment), member; Sign-in page, wrong email address or password (`/login`), visitor; Community settings, every section (`/community/atlas-garden/settings`, move buttons of the space tree), owner; every form that uses `Button loading`; all widths.
- What is wrong: `Components/ui/Button.tsx:86` sets `disabled={disabled || loading}`, and the browser takes the focus away from a disabled button. After Enter on the comment button, after a failed sign-in, and after "Move Off topic down" (the space tree disables every move button while it is busy, `SpaceTreeEditor.tsx:120,130,183,193`), the focus is on `BODY`. The next Tab starts at the top of the page.
- Why it matters: keyboard and screen reader users lose their place after each save on the main paths (comment, sign-in, settings).
- Proposed fix: in `Button`, use `aria-disabled` while loading and ignore clicks, so the element keeps the focus. `PageList.tsx:150` and `CourseMaterials.tsx` already do this ("a disabled button loses the focus"). Same change in `SpaceTreeEditor.tsx`.
- Evidence: steps: on a post, write a comment, Tab to the send button, press Enter, read `document.activeElement` (`a11y-keys.mjs comment`, `a11y-move.mjs`): `during {"tag":"BODY"}`, `after {"tag":"BODY"}`.
- Sources: A4.
- Checked: by the accessibility reviewer in the browser (not checked by the lead).
- WCAG: 2.4.3 Focus Order, A.
- Confidence: high (the reviewer's).

#### R-M5 Deleting your own comment deletes the replies of other members

- Screen: Delete a comment, dialog (`/community/atlas-garden/space/garden-talk/post/my-tomatoes-have-yellow-leaves`), member, all widths; Delete a post, dialog (same address), member.
- What is wrong: the author of a top-level comment can delete it, and the server deletes every reply under it, written by other people. The dialog says "Its replies are deleted too." A post author can do the same to a whole discussion. A deleted chat message and a comment of a deleted account leave a marker; an ordinary delete does not. ADR 0043 (point 33.2) left the ordinary delete unchanged, so this was a known choice.
- Why it matters: one member removes the words of others, without their consent and without undo. In a help space the answers are the value. Other community tools keep the replies and show a marker in place of the parent.
- Proposed fix: a decision of the owner before launch (decision 3). Recommended: when the author deletes a comment that has replies of others, keep a marker ("This comment was deleted.") and keep the replies; the code that renders a marker exists (`Components/Comments/CommentSection.tsx:249-258`). Keep the cascade for moderators only, or not at all. Same rule for a post with comments of others.
- Evidence: `app/Actions/Comments/DeleteComment.php:15-25`; `Components/Comments/CommentSection.tsx:446-447`; `Components/Chat/ChatRoom.tsx:439`; `Pages/Posts/Show.tsx:199`; picture `discussion/post--comment-delete-dialog--member--1920--light.png`.
- Sources: M1.
- Checked: by the lead in the code.
- Confidence: high.

#### R-M6 The account settings say that almost all profile data is public, which is false

- Screen: Account settings, every section (`/user/profile`), new-member and member, 1920, light.
- What is wrong: the text beside "Profile Information" is "Update your account's profile information. Note that Apart from your username, all the information will be publicly visible to other Knowii users." The same form gives bio, location and phone each a box "Visible to the members of my communities" (the phone is off by default), other members do not see the email address, and the username is exactly what others see, also visitors on public pages ("@atlas-moderator" on the public page of a community). The sentence also has a capital "Apart" in the middle and says "users" where the product says "members".
- Why it matters: the text misleads about privacy in both directions. A careful person thinks the email address and the phone are public and leaves the profile empty, or thinks the username is private.
- Proposed fix: replace the sentence in `Pages/Profile/Partials/UpdateProfileInformationForm.tsx:204` with what is true. These are privacy words: the owner approves the new text (decision 1).
- Evidence: `Pages/Profile/Partials/UpdateProfileInformationForm.tsx:62,204-205`; pictures `account/profile--full--new-member--1920--light.png`, `community/member-profile--other--member--1920--light.png`, `public/community--full--visitor--1920--light.png`.
- Sources: N2, W1. Two reviewers found it independently.
- Checked: by the lead in the code.
- Confidence: high.

#### R-M7 The emoji picker stays open when the focus leaves it, and covers the next controls

- Screen: Post, emoji picker open (`/community/atlas-garden/space/garden-talk/post/my-tomatoes-have-yellow-leaves`) and every reaction bar (comments, chat); member; 360 and 1920; both themes.
- What is wrong: after Tab leaves the picker, the picker stays open. Escape then does nothing, because only the search field listens for Escape. Only a pointer click outside closes it. At 360 px the picker is a sheet fixed to the bottom of the screen, and the next four Tab stops ("See who reacted", "Collapse all", "Collapse the thread of Tomas Silva", "👍: 1 reaction") are under it. At 1920 px the open picker covered the Bold and Italic buttons of the reply form when the focus reached them.
- Why it matters: a keyboard user cannot see the control that has the focus, and cannot close the layer without going back into it.
- Proposed fix: in `Components/Reactions/EmojiPicker.tsx`, close on `focusout` when the new focus is outside the picker (next to the pointer listener at line 102), and handle Escape on the container, not only on the input (line 213). Or make the sheet modal at small widths, with a focus trap.
- Evidence: steps: focus "Add a reaction to this post", Enter, Tab six times (`a11y-keys.mjs mobile --width 360`, `a11y-keys.mjs emoji`): "sheet still open 1" and "COVERED:DIV" on each stop.
- Sources: A3.
- Checked: by the accessibility reviewer in the browser (not checked by the lead).
- WCAG: 2.4.11 Focus Not Obscured (Minimum), AA.
- Confidence: high (the reviewer's).

#### R-M8 Dark theme: muted text on raised surfaces is 4.32:1 (bell panel, emoji picker, menus)

- Screen: Notification bell panel open (`/dashboard`), Post, emoji picker open, User menu open; member; 1920 and 360; dark.
- What is wrong: `text-muted` (white at 64 %) on `bg-surface-elevated` (#475363) gives 4.32:1. It colors the text of every line of the bell panel (space name, time), the section names of the emoji picker, and the email line of the user menu (`DropdownMenu.tsx:40`). The token comment gives 4.85:1 on `surface`, but the raised surface is lighter.
- Why it matters: "read the notifications" is a main path, in the default theme.
- Proposed fix: raise the dark `--color-muted` to white at 70 % (about 4.8:1 on #475363): one token, `styles.css:42`.
- Evidence: axe-core `member/bell/1920/dark` (21 nodes), `member/post-emoji/*/dark` (11 nodes); hand check: luminance 0.531 against 0.084, ratio 4.32.
- Sources: A2.
- Checked: by the accessibility reviewer in the browser.
- WCAG: 1.4.3 Contrast (Minimum), AA.
- Confidence: high.

#### R-M9 The same checkbox saves at once in one card and needs "Save" in the next

- Screen: Community settings, every section (`/community/atlas-garden/settings`), owner and admin, 1920 and 360, both themes; Account settings, every section (`/user/profile`), member.
- What is wrong: "Members can write private messages", "Search engines may index", the "Who can join" choice and "Show the other members when I am online" save on click. The "Moderator rights" checkboxes and the "Visible to the members of my communities" boxes of the profile need "Save". All use the same `Checkbox` in cards that look the same. "Open" in "Who can join" opens the community to anyone with an account in one click, without a question. `PopupsForm.tsx:44` gives a checkbox `role="switch"`, but it still looks like a checkbox.
- Why it matters: the owner ticks a moderator right, sees the cards above save at once, and leaves without "Save". A misclick on "Open" or on indexing changes who gets in or what search engines see.
- Proposed fix: a `Switch` building block for every setting that saves at once, with a `SaveStatus` ("Saved") next to it; `Checkbox` only inside a form with "Save". A confirmation for the two changes that widen access ("Open", indexing on).
- Evidence: `Components/Communities/DirectMessagesSetting.tsx:13,39,43`; `Components/Communities/JoinPolicySetting.tsx:19,54`; `Components/Communities/IndexingSetting.tsx:14,42,46`; `Pages/Profile/Partials/OnlineStatusForm.tsx:41`; `Components/Communities/ModeratorPermissionsForm.tsx:64`; pictures `settings/community--full--owner--1920--dark.png` ("Direct messages" card, then "Moderator rights" with "Save"), `account/profile--full--member--1920--light.png`.
- Sources: D2, M16. Two reviewers found it independently. Their weights differed (D2 must, M16 should): the higher one is kept.
- Checked: by two reviewers, in pictures and code (not checked by the lead).
- Confidence: high (both reviewers').

#### R-M10 Some error texts use the fill color `danger`, about 1.9:1 in the dark theme

- Screen: Notifications page and Notification bell panel, when "Done" or "Mute" fails; Account settings, every section (connected accounts); Sign-in page (sign-in with a provider); member and visitor; dark.
- What is wrong: `text-danger` (#dc2626, a fill token) colors error text in `NotificationsPanel.tsx:656`, `OpenIdConnectButton.tsx:22` and `ConnectedAccountsForm.tsx:58`. On the dark `surface` this is about 1.9:1. The 21 other error texts use `text-danger-text` (for example `InputError.tsx:18`).
- Why it matters: in the default theme the person cannot read the error message.
- Proposed fix: `text-danger-text` (and `border-danger-text` for the box) in the three places; a lint rule that forbids `text-danger` and `text-secondary` as text colors.
- Evidence: `Components/Notifications/NotificationsPanel.tsx:656`; `Components/Auth/OpenIdConnectButton.tsx:22`; `Pages/Profile/Partials/ConnectedAccountsForm.tsx:58`; `styles.css:60-62`. The error state of a notification action is not in the atlas.
- Sources: D3.
- Checked: by one reviewer, in the code only.
- WCAG: 1.4.3 Contrast (Minimum), AA.
- Confidence: high for the token use; the ratio is computed from the tokens, not measured in a browser.

### Should fix

#### R-S1 A visitor with a link gets the sign-in form with no title, no reason and no community name

- Screen: Space for members only, as a visitor (`/community/atlas-garden/space/garden-talk`), visitor, 360 and 1920, light; Private community, as a visitor (`/community/atlas-empty`); Sign-in page (`/login`) and Sign-up page (`/register`), visitor, 1920 and 360, light.
- What is wrong: a visitor who opens a link to a members-only space sees the plain sign-in form: logo, Email, Password, "Log in". No line says why, no community name, no way back to its public page; "Need an account?" in small text is the only clue. The sign-in and sign-up pages have no heading ("Sign in to Knowii", "Create your account"), so the two forms look almost the same. A person who pressed "Sign up to join" on Atlas Garden sees nothing about Atlas Garden on the sign-up page.
- Why it matters: the visitor does not know whether they have an account or need one, and loses the thread "I am joining this community".
- Proposed fix: give `Components/AuthenticationCard.tsx` a `title` and an optional line under it. Pass the intended page to the sign-in and sign-up pages and show one line above the form: "Garden talk is for the members of Atlas Garden. Sign in, or create an account to join." Show "Create an account" as a visible button. `Pages/Auth/Login.tsx` (the `status` slot exists at line 53), `Pages/Auth/Register.tsx:68-70`.
- Evidence: pictures `public/space-members-only--sign-in--visitor--360--light.png`, `public/community-private--sign-in--visitor--1920--light.png`, `auth/register--empty--visitor--1920--light.png`; `Pages/Auth/Login.tsx:52`, `Pages/Auth/Register.tsx:69`.
- Sources: N5, N6.
- Checked: by one reviewer, in pictures and code.
- Confidence: high.

#### R-S2 "Sign in" and "Log in", "Sign up" and "Register" name the same two actions

- Screen: Home page (`/`), Public page of a community (`/community/atlas-garden`), Sign-in page (`/login`), Sign-up page (`/register`), Verify your email address; visitor; all widths.
- What is wrong: the home page header says "Log in" and "Register"; the community pages say "Sign in", "Sign up" and "Sign up to join"; the sign-in page button and tab say "Log in" under the link "Email me a sign-in link instead"; the sign-up button says "Register" and its link "Already registered?". The sign-in link mail, the two-factor page and the magic link page say "Sign in". "Log out" and "Log Out" leave the account.
- Why it matters: a person presses "Sign up to join" and lands on a page whose button is "Register". A non-native reader can think these are two different accounts or steps. It is on the first path of every person.
- Proposed fix: one set of words everywhere: "Sign in", "Sign out", "Sign up". `Pages/Auth/Login.tsx:51,157,164`, `Pages/Auth/Register.tsx:68,208,218`, `Components/PageHeader.tsx:79,91,96`, `Layouts/AppLayout.tsx:107,109,209`, `Pages/Auth/VerifyEmail.tsx:50`, `Pages/Profile/Partials/LogoutOtherBrowserSessionsForm.tsx:99,105` ("Sign out of other browsers"), `bootstrap/app.php:302` ("You are already signed in"). The newcomer proposed "Create an account" instead of "Sign up"; "Sign up" is already in most places, so the merge keeps it.
- Evidence: pictures `public/home--full--visitor--1920--light.png`, `public/community--full--visitor--1920--light.png`, `auth/login--empty--visitor--1920--light.png`, `auth/register--empty--visitor--1920--light.png`; the glossary table of `findings/words.md`.
- Sources: N4, W4. Two reviewers found it independently.
- Checked: by two reviewers, in pictures and code.
- Confidence: high.

#### R-S3 The sign-up form hides its rules, and the sign-in, sign-up and password pages show the framework messages

- Screen: Sign-up page, sent empty (`/register`), Sign-in page, wrong email address or password (`/login`), Forgot password page, address without an account (`/forgot-password`), Account settings (password), visitor and member, 1920 and 360, light.
- What is wrong: the server asks for at least 8 characters, but the form says nothing before an error. "Name" does not say that it shows next to everything the person writes, also to visitors in public spaces. The messages are the framework defaults, with field keys: "These credentials do not match our records.", "We can't find a user with that email address.", "The name field is required.", "The terms field must be accepted.", "The provided password does not match your current password.", "The provided two factor authentication code was invalid." The repository has no `lang/en` folder to override them.
- Why it matters: these are the first error texts a newcomer meets. "credentials", "records", "user", "terms field" are jargon, and none says what to do. The person finds the password rule only by failing.
- Proposed fix: hints under "Password" ("At least 8 characters.") and "Name" ("Shown next to what you write.") in `Pages/Auth/Register.tsx:74,111`. Add `lang/en/auth.php`, `passwords.php` and `validation.php` (or `lang/en.json`) with product texts, for example "The email address or the password is not correct. Try again, or use "Forgot your password?"."; "No account uses this email address. Check it, or sign up."; "Enter your name."; "Tick the box to accept the terms of service and the privacy policy."; "This code is not correct. Enter the code that your app shows now." Set the `attributes` names ("email address", not "email").
- Evidence: pictures `auth/register--errors--visitor--360--light.png`, `auth/login--wrong-password--visitor--1920--light.png`, `auth/forgot-password--unknown-address--visitor--1920--light.png`; `app/Actions/Fortify/PasswordValidationRules.php:16`; `app/Actions/Fortify/UpdateUserPassword.php:28`; `app/Actions/Communities/TransferCommunityOwnership.php:48`; `app/Http/Controllers/API/UserApiController.php:176`.
- Sources: N7, W5. Two reviewers found it independently.
- Checked: by two reviewers, in pictures and code.
- Confidence: high.

#### R-S4 The errors of the sign-in, sign-up and account forms are not linked to their fields

- Screen: Sign-up page, sent empty (`/register`), Sign-in page, sent empty (`/login`), Forgot password page, Reset password page, Confirm password page, Account settings (profile information, password, browser sessions, delete account), Quiz sent without answers; visitor and member; all widths.
- What is wrong: after a failed submit, the fields have no `aria-invalid` and no `aria-describedby`. The error is a `role="alert"` paragraph under the field, spoken once; back on the field, the field does not say that it is invalid or why. The red border comes only from `aria-invalid` (`form-field.ts`), so these fields have no visual mark either. The newer dialogs (`CreateResourceDialog.tsx`, `SpaceDialog.tsx`) do it right.
- Why it matters: sign-up is a main path. A screen reader user who goes back to a field does not hear the error.
- Proposed fix: a form field building block (label, control, hint, error) that sets `id`, `aria-invalid` and `aria-describedby` itself, next to `TextInput`; use it in `Pages/Auth/Register.tsx:75-140`, `Pages/Auth/Login.tsx:99-129`, `Pages/Auth/ForgotPassword.tsx:61`, `Pages/Auth/ResetPassword.tsx:62,81`, `Pages/Auth/ConfirmPassword.tsx:52`, `Pages/Profile/Partials/UpdateProfileInformationForm.tsx`, `Pages/Profile/Partials/UpdatePasswordForm.tsx:85,104`, `Components/SpacePages/LessonQuiz.tsx`.
- Evidence: `a11y-errors.mjs`: on `/register` four errors shown, every field `invalid=null describedby=null`; the same on `/login` and `/forgot-password`.
- Sources: A7.
- Checked: by the accessibility reviewer in the browser.
- WCAG: 3.3.1 Error Identification (met by the text), 1.3.1 Info and Relationships, 4.1.2 Name, Role, Value.
- Confidence: high.

#### R-S5 The verify-your-address page hides the address and sends the person to a page where they cannot change it

- Screen: Verify your email address (`/email/verify`), new-member with an address not verified, 360, light.
- What is wrong: the text is "Before continuing, could you verify your email address by clicking on the link we just emailed to you? If you didn't receive the email, we will gladly send you another." It does not show the address, does not mention the spam folder, and has no heading. The way to fix a wrong address is the link "Edit profile", but on that page every field is disabled while the address is not verified ("WARNING: You will not be able to edit your profile until your email address has been verified."). The tab says "Email Verification"; the status says "A new verification link has been sent to the email address you provided during registration."
- Why it matters: a typo in the address is the common reason to be stuck here, on the first screen after sign-up, and the person cannot fix it.
- Proposed fix: heading "Check your email"; "We sent a link to <address>. Open it to finish your sign-up. No email? Look in the spam folder, or send a new link."; button "Send a new link"; "Wrong address? Change it" to a field that stays editable while the address is not verified (or remove the link). On the profile page, drop "WARNING:" and say "Verify your email address to change your profile." `Pages/Auth/VerifyEmail.tsx:27-50`, `Pages/Profile/Partials/UpdateProfileInformationForm.tsx:209,243,260`.
- Evidence: picture `auth/verify-email--full--new-member--360--light.png`; `Pages/Auth/VerifyEmail.tsx:30,47`; `vendor/laravel/jetstream/routes/inertia.php:31-33` (the profile route has no `verified`).
- Sources: N8, W12. Two reviewers found it independently.
- Checked: by two reviewers, in pictures and code.
- Confidence: medium. Not verified in a browser that the email field stays disabled after a reload.

#### R-S6 The home page says "Coming soon" and "Join the waitlist" next to a working "Register"

- Screen: Home page (`/`), visitor and member, 1920 and 360, light.
- What is wrong: the h1 is always "Coming soon 👋", then "Get ready for a revolutionary knowledge-sharing experience!" and the card "Join the waitlist ... one email when Knowii opens". The same page shows a "Register" button that opens a working sign-up to a visitor, and "Dashboard" to a member. The page does not say what a person can do. The h2 capitalizes "Knowledge, Ideas and Inspiration"; the footer does not.
- Why it matters: a newcomer who comes from a community link and presses the logo lands here. "Coming soon" tells them the product is not open, so the community they just saw looks like a test. Two opposite main actions (wait, register) on one screen.
- Proposed fix: in `Pages/Welcome.tsx:43-58`, show "Coming soon" and the waitlist only when registration is closed. Else: h1 "A place for your community's knowledge, ideas and inspiration", two lines on what a person can do, and "Sign up" (or "Open your dashboard" for a member). Drop "revolutionary". Waitlist button "Join the waitlist" instead of "Subscribe" (`Components/Waitlist/WaitlistForm.tsx:112`). Whether registration is open is a decision of the owner (decision 5).
- Evidence: pictures `public/home--full--visitor--1920--light.png`, `public/home--full--visitor--360--light.png`, `public/home--full--member--1920--light.png`; `Pages/Welcome.tsx:43-58`.
- Sources: N3, W11. Two reviewers found it independently.
- Checked: by two reviewers, in pictures and code.
- Confidence: high.

#### R-S7 The "Join" button does not say what joining does

- Screen: Public page of a community (`/community/atlas-garden`) and Public pages space (`/community/atlas-garden/space/welcome`), new-member, 1920 and 360, light.
- What is wrong: one button "Join" with a door icon. On a space page it reads as "join this space". Nothing says that the person joins Atlas Garden, that the members will see their name and profile, or that they can leave. After the click the page reloads with no message.
- Why it matters: a careful person hesitates before a one-click action with an effect they cannot see.
- Proposed fix: label "Join Atlas Garden" and one small line under it: "Members see your name and profile. You can leave at any time." A short confirmation after the join. `Components/Communities/JoinCommunity.tsx:59-70`.
- Evidence: pictures `public/community--full--new-member--1920--light.png`, `public/space-pages--full--new-member--360--light.png`.
- Sources: N16.
- Checked: by one reviewer, in pictures and code.
- Confidence: high.

#### R-S8 On a public post, a signed-in non-member has no way to comment, and a visitor only "Sign in to reply"

- Screen: Post of a public space (`/community/atlas-garden/space/announcements/post/welcome-to-atlas-garden`), new-member and visitor, 360 and 1920, light.
- What is wrong: as a new member who is not in the community, the comments end with "No comments yet." and nothing else: no "Join to comment", no Join button on the post page. As a visitor, the only action is "Sign in to reply", with no path for a person without an account.
- Why it matters: a post is the most shared kind of link, and the person who wants to answer has no visible way to do it.
- Proposed fix: in `Components/Comments/CommentSection.tsx:430-438`, for a signed-in non-member of an open community, show `JoinCommunity` with "Join to comment"; for a visitor, "Sign in or sign up to reply".
- Evidence: pictures `public/post--full--new-member--360--light.png`, `public/post--full--visitor--1920--light.png`.
- Sources: N17.
- Checked: by one reviewer, in pictures and code.
- Confidence: high.

#### R-S9 A community by invitation offers "Sign up" to a visitor and says nothing to a signed-in person

- Screen: Public page of a community with "Who can join" set to invitation (not in the atlas), visitor and new-member.
- What is wrong: for a visitor the page shows "Sign in" and "Sign up" even when an account does not let them in. For a signed-in non-member, `JoinCommunity` renders nothing, and no text says "By invitation".
- Why it matters: a newcomer signs up, verifies the address, comes back, and still cannot get in, with no reason given.
- Proposed fix: show "This community is by invitation. Ask a member for an invitation." to both, and do not show "Sign up" as the main action there. `Pages/Communities/Public.tsx:42-53`, `Components/Communities/JoinCommunity.tsx:26-28`.
- Evidence: `Pages/Communities/Public.tsx:42-49`; `Components/Communities/JoinCommunity.tsx:26`.
- Sources: N18.
- Checked: by one reviewer, in the code only (the atlas has only an open public community).
- Confidence: medium.

#### R-S10 The first dashboard of a new member has no welcome and no way to find a community

- Screen: Dashboard (`/dashboard`), new-member, 1920 and 360, light.
- What is wrong: the page shows "My Communities", a search field, "Filter by visibility", "Clear filters", one card "Nina Newcomer's Space / Nina Newcomer's Space / Personal" and a "New community" card. Nothing says what the personal space is for or how to find or join a community. The only large action is to create a community.
- Why it matters: most newcomers came to join a community, not to start one. Search and filter for one card add noise and push the cards down at 360 px.
- Proposed fix: for a person with only the personal community, a short welcome card: what the personal space is, and "Have a link to a community? Open it to join." Hide search and filter under 4 communities. `Pages/Dashboard.tsx:99-139`. Related: #1398 (onboarding), #213 (product tour).
- Evidence: pictures `dashboard/home--full--new-member--1920--light.png`, `dashboard/home--full--new-member--360--light.png`.
- Sources: N9.
- Checked: by one reviewer, in pictures and code.
- Confidence: high.

#### R-S11 An empty community gives its owner no next step, and the first space needs a "space group" first

- Screen: Personal community of a new member (`/community/nina-newcomer-s-space`), new-member, 1920 and 360, light and dark; Community home without content (`/community/atlas-empty`), owner, 360, light; Settings of the personal community of a new member (`/community/nina-newcomer-s-space/settings`), new-member, 1920, light.
- What is wrong: the personal community shows "Messages", "No spaces yet.", "Online now (1)" with the person's own face, and "Recent Resources / No resources yet." The community name is not a visible heading (the h1 is for screen readers only). The home of an empty community shows "No resources yet." and a member count; the only way forward is the small "Settings" link. There, the empty state says "No space groups yet. Create a space group (for example “Start Here”), then add spaces to it.": two new words before the first space exists.
- Why it matters: the personal community is the first community a newcomer opens, and it looks broken. A new owner who does not reach a first space in the first minutes leaves an empty community.
- Proposed fix: on the community home, for an owner with no spaces, an `EmptyState` that explains the place and offers "Add your first space"; create a default space group behind the scenes, or offer the first group and space in one step. `Pages/Communities/Show.tsx:75,83-103`, `Components/Spaces/SpaceNavigation.tsx:48`, `Components/Spaces/SpaceTreeEditor.tsx:101-102`. Related: #1457 (owner checklist).
- Evidence: pictures `community/personal--empty--new-member--1920--light.png`, `community/personal--empty--new-member--360--dark.png`, `community/home--empty--owner--360--light.png`, `settings/personal--new--new-member--1920--light.png`.
- Sources: N12, N14.
- Checked: by one reviewer, in pictures and code.
- Confidence: high.

#### R-S12 The "New community" dialog shows "Personal / Private / Public" with no explanation, and picks "Public"

- Screen: New community dialog (`/dashboard`), new-member and member, 1920 and 360, light.
- What is wrong: "Community visibility" is a three-part switch with "Public" selected and no text. The product has a description for each choice (`COMMUNITY_VISIBILITY_DESCRIPTIONS`), shown only later in the settings and on the About page. The dialog also says "Provide the details of your new community.", the button is "Go ahead!" (see R-S52), and the line "you accept the data processing agreement" gives no reason.
- Why it matters: a person who picks "Private" thinks nothing can be read from outside; a person who leaves "Public" thinks everything goes on the web. Both are wrong, on the first thing a new owner does.
- Proposed fix: show the description of the selected choice under the switch: the texts that the owner approved in #1512, not new ones. Add a description slot to `Components/ui/SegmentedControl.tsx`. Button "Create the community". The default choice and a plain line before the agreement link are decisions of the owner (decision 6). `Components/Communities/CreateCommunityDialog.tsx:46,96,103,161-190`, `libs/common/src/lib/types/community.schema.ts:40-43`.
- Evidence: pictures `dashboard/new-community--dialog--new-member--1920--light.png`, `dashboard/new-community--dialog--new-member--360--light.png`.
- Sources: N10. The rest of M3 and W17 (the control itself) is already decided: see "Not kept".
- Checked: by one reviewer, in pictures and code; the lead confirmed that this part stays a finding.
- Confidence: high.

#### R-S13 After a new community or a new space, the owner stays where they were

- Screen: New community dialog (`/dashboard`), member and new-member; Community settings, New space dialog, owner.
- What is wrong: after a create, the dialog closes, a toast says "Community created successfully", and the new card goes into the alphabetical list. The owner must find it, open it, then open Settings to add spaces. After a new space, the owner stays in the settings and must find the space in the navigation.
- Why it matters: "create a community" is a main path, and the next step is always the same.
- Proposed fix: after a create, go to the new community (or to its spaces settings) with a first-steps card for the owner; after a new space, offer "Open the space" in the toast. `Components/Communities/CreateCommunityDialog.tsx:62-74`, `Pages/Dashboard.tsx:28-35`, `Components/Spaces/SpaceDialog.tsx:173`.
- Evidence: the files above.
- Sources: N11, M20. Two reviewers found it independently.
- Checked: by two reviewers, in the code only.
- Confidence: high (M20), medium (N11).

#### R-S14 The personal community has the settings of a shared one

- Screen: Settings of the personal community of a new member (`/community/nina-newcomer-s-space/settings`), new-member, 1920, light.
- What is wrong: the personal community is "Only you." elsewhere, but its settings show "Direct messages: Members can write private messages to each other", "Moderator rights", "Members: Invite by email", "Pending invitations" and "Access groups". The visibility line says "Personal. A personal community stays personal."
- Why it matters: the person cannot tell whether this place is private, or what happens after an invitation.
- Proposed fix: hide the sections that do not apply to a personal community; if invitations are allowed, say what happens. Replace the circular line with the real description. `Components/Communities/UpdateCommunityForm.tsx`, `Pages/Communities/Settings.tsx`. Related: #1549 (one personal community per account), #1550.
- Evidence: picture `settings/personal--new--new-member--1920--light.png`.
- Sources: N13.
- Checked: by one reviewer, in pictures only. Whether the server accepts an invitation to a personal community: not verified.
- Confidence: medium.

#### R-S15 Several pages have no h1, and the page title sits in five different places

- Screen: Sign-in page, Sign-up page, Forgot password page (no heading at all), Dashboard, Account settings, Community settings (h2 only), Community home (name only in the breadcrumb), Notifications page; all roles; 1920 and 360.
- What is wrong: `PageTitle.tsx` renders an h1 only when a page gives `pageTitle`. The title is (1) in the breadcrumb only, with a hidden h1 (`Pages/Communities/Show.tsx:75`, `Pages/Messages/Index.tsx:90`, `Pages/Profile/Notifications.tsx:55`); (2) an h1 in the content with an emoji (`Pages/Spaces/Show.tsx:127`); (3) an h2 and no h1 (`Pages/Dashboard.tsx:99`); (4) a large centered h1 and no breadcrumb (`Pages/Notifications/Index.tsx:30`); (5) the global h1 style at 36 to 60 px (`Pages/Contact.tsx:27`, `Pages/Error.tsx:48`, `styles.css:161`). At 360 px the breadcrumb is centered when the page has actions and left aligned when not (`PageTitle.tsx:19`). The resource list goes from h1 to h3 (`ResourceCard.tsx:29`).
- Why it matters: a screen reader user who jumps to the first heading finds none. A sighted member cannot rely on one place to see where they are; after "Join", the community home shows no name.
- Proposed fix: one visible h1 at the top of the content of every page, in one style (`text-2xl font-extrabold sm:text-3xl`); the breadcrumb in the title bar on every page, left aligned; a title in `AuthenticationCard` (see R-S1). Limit the global `h1`/`h2` rules of `styles.css:161-167` to the home page. h2 for resource cards, or an h2 above the list.
- Evidence: `a11y-out/tree.log` ("h1":0 on these pages), `a11y-out/tree/visitor-register-1920.yml`; pictures `notifications/page--full--member--1920--dark.png`, `community/home--full--member--360--light.png`, `dashboard/home--full--member--1920--light.png`.
- Sources: A8, D11. Two reviewers found it independently.
- Checked: by the accessibility reviewer in the browser; by the design reviewer in pictures and code.
- WCAG: 1.3.1 Info and Relationships, 2.4.6 Headings and Labels, AA.
- Confidence: high.

#### R-S16 No skip link: 18 Tab stops before the content of a community page

- Screen: Community home, every space and post page (`/community/atlas-garden/...`), member, 1920, both themes.
- What is wrong: there is no "Skip to content" link. On the community home and in the chat, the first content control is Tab stop 19: logo, theme, bell, main menu, user menu, two breadcrumb links, "Messages" and ten space links.
- Why it matters: the landmarks help screen reader users, but a sighted keyboard user has no landmark keys.
- Proposed fix: a skip link as the first element of `Layouts/AppLayout.tsx`, visible on focus, to an `id` on the `main` of `PageContentWrapper.tsx` or on the start of the space content in `CommunityShell.tsx`. It does not change the look when it has no focus.
- Evidence: `a11y-keys.mjs member-community` and `chat`: tab 19 is the first content control.
- Sources: A10.
- Checked: by the accessibility reviewer in the browser.
- WCAG: 2.4.1 Bypass Blocks, A.
- Confidence: high.

#### R-S17 Error toasts show the server and framework texts instead of the product messages

- Screen: every screen that saves through the API (settings, members, spaces, posts, resources, messages); seen on New post, composer open, member.
- What is wrong: `apiRequest` uses the `message` of the server when there is one, and the client message ("Failed to delete the post.") only when there is none. The server always sends one: "Internal Server Error", "Not found", the HTTP status text such as "Too Many Requests", "This action is unauthorized." and "The name field is required. (and 1 more error)". About 40 places show `result.message` as the toast title.
- Why it matters: the roughly 130 client messages do not show in the cases that matter. A member reads "Internal Server Error" and does not know what to do.
- Proposed fix: in `libs/common/src/lib/api/api-result.ts`, keep the client message for status 0, 401, 403, 404, 405, 429 and 5xx; use the server message only for business errors and as field errors for 422. One text for 429: "Too many tries. Wait a minute, then try again."
- Evidence: `libs/common/src/lib/api/api-result.ts:62-67`; `app/Traits/ApiResponses.php:90,152,166`; `bootstrap/app.php:169-201`; `Pages/Posts/Show.tsx:92`. Steps of the lead: answer the create request of a post with a 500: the only text shown is "Internal Server Error" (the dialog stays open, so the text is not lost).
- Sources: W3.
- Checked: by the lead in the browser.
- Confidence: high.

#### R-S18 Every writing field shows Markdown code as help, in a monospace font

- Screen: New post, composer open, Post (comment form), Chat space, Conversation of two; member; 1920 and 360; both themes.
- What is wrong: under each editor: "Markdown: **bold**, _italic_, [link](https://…), `code`, - list, > quote, @name to mention a member. A YouTube or Vimeo link alone on its line becomes a video." At 360 px it takes three lines, and the chat adds a second hint line. The field itself is `font-mono text-sm`, so the person writes in JetBrains Mono and reads the result in Noto Sans at 16 px. At 360 px the toolbar wraps and leaves the video icon alone on a line.
- Why it matters: a non-technical person does not know "Markdown" or backticks, and the toolbar already does bold, link and list. Writing a post feels like editing code, on the main writing surface of the product.
- Proposed fix: in `Components/ui/MarkdownEditor.tsx:632`, `font-sans text-base`. At line 732, keep the two useful tips in plain words ("Type @ to mention someone. Paste a YouTube link to show the video.") and move the syntax behind a "Formatting help" button in the toolbar. One hint line at most.
- Evidence: pictures `discussion/post-short--full--member--360--light.png`, `chat/space--full--member--360--light.png`, `chat/space--full--member--1920--dark.png`, `discussion/composer--open--member--360--dark.png`.
- Sources: N21, D28. Two reviewers found it independently. Their weights differed (N21 should, D28 later): the higher one is kept.
- Checked: by two reviewers, in pictures and code.
- Confidence: high.

#### R-S19 The comment field has no visible label

- Screen: Short post without comments (`/community/atlas-garden/space/garden-talk/post/short-one-is-rain-water-better`), member, 360, light.
- What is wrong: under "No comments yet." there is an empty editor and a pale "Comment" button. The label "Write a comment" is for screen readers only, and there is no placeholder. The chat field has one ("Message Lounge").
- Why it matters: the newcomer must guess that the box is for them.
- Proposed fix: a visible label or placeholder "Write a comment". `Components/Comments/CommentForm.tsx:74-76`.
- Evidence: picture `discussion/post-short--full--member--360--light.png`; `Components/Comments/CommentSection.tsx:433`.
- Sources: N22.
- Checked: by one reviewer, in pictures and code.
- WCAG: 3.3.2 Labels or Instructions, A (mapped in the merge; the reviewer did not name a criterion).
- Confidence: high.

#### R-S20 The new post dialog does not say who will read the post

- Screen: New post, composer open (`/community/atlas-garden/space/garden-talk`), member, 1920, light.
- What is wrong: the dialog title is "New post in Garden talk", then "Title", "Post" and "Publish". The page behind has a chip "Members only" or "Public", but the dialog covers it. In a public discussion space the post, with the name of the person, goes on the open web.
- Why it matters: the first post is the moment a careful newcomer asks "who sees this?".
- Proposed fix: an audience line under the dialog title: "Members of Atlas Garden can read this." or "Everybody on the web can read this." Build it once (see "Missing building blocks") and use it in `Components/Posts/PostComposer.tsx:93-107`, `Components/Comments/CommentForm.tsx` and `Components/Chat/ChatComposer.tsx`.
- Evidence: picture `discussion/composer--open--member--1920--light.png`; `Components/Posts/PostComposer.tsx:97`.
- Sources: N24.
- Checked: by one reviewer, in pictures and code. A public space where members post is not in the atlas.
- Confidence: medium.

#### R-S21 No keyboard shortcut sends a post, a comment or a page

- Screen: New post, composer open, Post (comment form), Page in edit mode, Lesson in edit mode; member and owner.
- What is wrong: the chat sends with Enter. The comment form, the post composer and the page editor have no Ctrl/Cmd+Enter; the member must Tab to the button. The editor already handles Ctrl+B/I/K/E.
- Why it matters: members who write many comments a day meet two behaviors in two composers.
- Proposed fix: in `Components/ui/MarkdownEditor.tsx` (the key handler at line 452), submit the form on Ctrl/Cmd+Enter and say it in the hint. Keyboard shortcuts in general are #1442; this one belongs to the editor.
- Evidence: `Components/ui/markdown-format.ts:78-83`; `Components/Comments/CommentForm.tsx` (no key handler).
- Sources: M12.
- Checked: by one reviewer, in the code only.
- Confidence: high.

#### R-S22 The focus is lost after "Collapse all", "Expand all" and after a comment is deleted

- Screen: Post with comments, replies and reactions (`/community/atlas-garden/space/garden-talk/post/my-tomatoes-have-yellow-leaves`), member, 1920, light.
- What is wrong: "Collapse all" disables itself when every thread is collapsed, and "Expand all" does the same, so the focus goes to `BODY`. After "Delete" in the comment dialog, the button that opened it is gone with the comment, so `use-return-focus.ts` has nothing to return to.
- Why it matters: the user must find the comments again from the top of the page.
- Proposed fix: after "Collapse all", move the focus to "Expand all" and the reverse, or keep both enabled with `aria-disabled`. After a delete, focus the next comment, the previous one, or the heading "N comments" (`#comments-title` with `tabIndex={-1}`). `Components/Comments/CommentSection.tsx:340-352`. A "focus after removal" helper (see "Missing building blocks").
- Evidence: `a11y-keys.mjs collapse` (after "Collapse all": BODY; after "Expand all": BODY); `a11y-keys.mjs comment` (after delete: BODY).
- Sources: A5.
- Checked: by the accessibility reviewer in the browser.
- WCAG: 2.4.3 Focus Order, A.
- Confidence: high.

#### R-S23 No status message after a comment is posted

- Screen: Post with comments, replies and reactions, member, 1920, light.
- What is wrong: after the comment is sent, no live region gets text, and the focus is on the page body (R-M4). The delete says "Comment deleted"; the post says nothing.
- Why it matters: a screen reader user does not know whether the comment was posted.
- Proposed fix: announce "Comment posted" in the toaster or a polite region, or move the focus to the new comment (comment items already take the focus, `CommentSection.tsx:255`). The same check is needed for a new post and a resource (not verified). An "announce" helper (see "Missing building blocks").
- Evidence: `a11y-keys.mjs comment`: "live regions [ '[polite] ', 'status[polite] ', 'status[polite] ' ]" after the send.
- Sources: A6.
- Checked: by the accessibility reviewer in the browser.
- WCAG: 4.1.3 Status Messages, AA.
- Confidence: high.

#### R-S24 "Not following Follow Mute this thread": the state looks like a button

- Screen: Post with comments, replies and reactions, Post, reply form open, Short post without comments; member; 360 and 1920; light.
- What is wrong: under the title, three items in a row: "Not following" (bold text), "Follow" (grey button) and "Mute this thread" (bold text, also a button). Nothing says that "Follow" means "get a notification for new comments".
- Why it matters: the member cannot tell the state from the actions, nor what "Follow" does.
- Proposed fix: one control: a button "Follow" with the hint "Get a notification for new comments"; when on, "Following" with a menu for "Stop following" and "Mute". `Components/Notifications/ThreadFollowControl.tsx`.
- Evidence: pictures `discussion/post-short--full--member--360--light.png`, `discussion/post--reply-open--member--1920--light.png`.
- Sources: N23.
- Checked: by one reviewer, in pictures only.
- Confidence: high.

#### R-S25 "Reply" on a reply answers the wrong person

- Screen: Post, reply form open (`/community/atlas-garden/space/garden-talk/post/my-tomatoes-have-yellow-leaves`), member, all widths.
- What is wrong: replies are one level deep. "Reply" on Lena's reply opens a form labelled "Reply to Maya Moderator" (the author of the top-level comment) and puts nothing in the text. Nothing tells Lena or the readers that the answer is for her.
- Why it matters: in a thread of four replies the conversation loses who answers whom.
- Proposed fix: when the target is a reply, prefill `@username ` of its author and label the form "Reply to Lena Fischer"; the mention then also notifies her. `Components/Comments/CommentSection.tsx:293,409-417`.
- Evidence: `Components/Comments/CommentSection.tsx:293,409-417`.
- Sources: M7.
- Checked: by one reviewer, in the code only. Who gets the notification today is not verified.
- Confidence: high.

#### R-S26 Comments carry more controls than text

- Screen: Post with comments, replies and reactions, member, 1920 and 360, both themes.
- What is wrong: each comment is a meta line, the text, a reaction row (an empty add-reaction chip even with no reaction) and a row of two to four labelled buttons (Reply, Copy link, Edit, Delete). A one-line comment takes four lines; at 360 px "Edit" and "Delete" wrap to their own row, so a thread of nine comments is four screens of buttons. The post header has two rows of buttons in mixed variants before the text.
- Why it matters: reading the thread, the main path, is slowed by repeated controls.
- Proposed fix: keep "Reply" and the reaction button visible; put "Copy link", "Edit", "Delete" in a "..." `DropdownMenu`, as the notification lines do. Show the empty add-reaction chip on hover and focus only (always on touch). Follow and mute in one menu next to the post title (R-S24). `Components/Comments/CommentSection.tsx:277-330`, `Pages/Posts/Show.tsx:140-152`.
- Evidence: pictures `discussion/post--full--member--1920--dark.png`, `discussion/post--full--member--360--dark.png`, `discussion/post--full--member--360--light.png`.
- Sources: D18, M24. Two reviewers found it independently. Their weights differed (D18 should, M24 later): the higher one is kept.
- Checked: by two reviewers, in pictures and code.
- Confidence: medium (D18: the order of priority is a judgment), high (M24).

#### R-S27 Authors are shown in different ways, and their names do not link to the profile

- Screen: Discussion space, Post with comments, Chat space, Direct messages, Notifications page, Public page of a community, Resource; member; all widths.
- What is wrong: the author of a post, a comment or a chat message is plain text. The member profile (with "Send a message" and "Block") is reachable from "Online now", the About page and the resource "Added by", not from the content. Authors have an avatar in chat, direct messages, notifications and the public page, and none in the post list, on the post page, in comments and on a resource. The meta order changes from place to place.
- Why it matters: "who is this, write to them, block them" starts from a message. The reader cannot scan who wrote what in comments.
- Proposed fix: one `MemberIdentity` block (avatar, name linked to `MEMBER_URL`, optional handle, meta) and one `MetaLine`, used in `Components/Posts/PostCard.tsx:34`, `Pages/Posts/Show.tsx:124`, `Components/Comments/CommentSection.tsx:261`, `Components/Chat/ChatMessageItem.tsx:48-64`, `Components/Resources/ResourceDetails.tsx:150`, `Pages/Communities/Public.tsx:77`.
- Evidence: the files above; pictures `resources/resource--full--member--1920--light.png` against `discussion/post--full--member--1920--dark.png`, `public/community--full--visitor--1920--light.png`.
- Sources: M8, D19. Two reviewers found it independently.
- Checked: by two reviewers, in pictures and code.
- Confidence: high.

#### R-S28 The post list holds the full body of each post, with its headings at the level of the post titles

- Screen: Discussion space (`/community/atlas-garden/space/garden-talk`), member and visitor, all widths.
- What is wrong: `PostCard.tsx:39` renders the full `body_html` and clips it only visually with `line-clamp-4`. A screen reader reads the whole body of every post in the list, and the headings in a post are h2, the level of the post titles: "A full guide to the first season on a balcony" adds "1. Light", "2. Pots" ... "7. The harvest" to the list of titles. Links in the clipped part stay in the Tab order while invisible (none in the seed).
- Why it matters: the heading navigation of the space page is wrong, and the list is very long to hear.
- Proposed fix: render a plain-text excerpt for the card (on the server, or by stripping blocks), or mark the clipped body `aria-hidden` and `inert` with a short accessible summary. One fix with R-S29.
- Evidence: `a11y-out/tree.log`, entry `member/discussion`.
- Sources: A9.
- Checked: by the accessibility reviewer in the browser. Links in the clipped part: not verified.
- WCAG: 1.3.1 Info and Relationships, A.
- Confidence: high.

#### R-S29 The post list shows full images, galleries and video players

- Screen: Discussion space (`/community/atlas-garden/space/garden-talk`), member, 1920 and 360, both themes.
- What is wrong: `PostCard` clamps the text to four lines, but a video player and a gallery of four images show in full. One post with a picture is about 600 px high; sixteen posts make a page of 5 228 px.
- Why it matters: the member scrolls past media to find titles; a busy space becomes a feed instead of an index.
- Proposed fix: in `Components/Posts/PostCard.tsx`, the first image as a fixed-ratio thumbnail (or "4 pictures", "1 video" with an icon), no embeds in the excerpt, the counts (comments, reactions) on one line; a full card only for the pinned post.
- Evidence: pictures `discussion/space--full--member--1920--dark.png` (lower half), `discussion/space--full--member--1920--light.png`.
- Sources: D20, M23. Two reviewers found it independently. Their weights differed (D20 should, M23 later): the higher one is kept.
- Checked: by two reviewers, in pictures (and code for D20).
- Confidence: high (D20), medium (M23).

#### R-S30 A lock shows next to every space a member can open, and the "new content" dot changes with the theme

- Screen: Community home (`/community/atlas-garden`, `/community/atlas-empty`), Discussion space, Post; member; 1920; both themes.
- What is wrong: the navigation shows a lock beside "Empty discussion", "Garden talk", "Lounge", "Balcony course" and others, for a member who can open all of them. The lock means "members only" (the text is for screen readers only). The "new content" dot is 10 px with a 2 px border in the text color: a white ring in dark, a black dot in light, with a 6 px magenta center, and it sits next to the lock. #1421 plans the lock for spaces the member cannot open, so the icon will have two meanings.
- Why it matters: a lock tells a newcomer "closed to you", so they do not click. The useful signals (new, unread) compete with a constant one.
- Proposed fix: in `Components/Spaces/SpaceNavigation.tsx:28-36,96-111`, do not show the lock for "members only" to members; keep the lock for #1421. Which icon, if any, marks "Public" or "Access groups only" is a choice (decision 7). The dot: 8 px solid accent with a 2 px ring in the background color (`ring-2 ring-background`, with a `forced-colors:` border), and a tooltip "New since your last visit".
- Evidence: pictures `community/home--empty--member--1920--light.png`, `community/home--full--member--1920--light.png`, `discussion/space--full--member--1920--dark.png`, `discussion/space--full--member--1920--light.png`.
- Sources: N20, M22, D22. Three reviewers found it independently. Their weights differed (N20 and M22 should, D22 later): the higher one is kept.
- Checked: by three reviewers, in pictures and code.
- Confidence: high (N20, D22), medium (M22).

#### R-S31 On a phone the chat shows one or two messages, and editing happens through a small window

- Screen: Chat space (`/community/atlas-garden/space/lounge`), Chat, own message in edit mode, Conversation of two; member; 360 and 320; both themes.
- What is wrong: the chat is a box of fixed height (`ChatRoom.tsx:344`, `h-[70vh] min-h-96`) that includes the composer (tabs, a toolbar of two rows, the field, the Markdown hint, the key hint, Send). Above it: the title, the description, "Notifications: Everything", the chips and "1 here". At 360 x 800 the message log is about 230 px high: one and a half messages, and the "1 here" pill sits over a cut message. The inline edit form is 515 px high inside the 233 px log, so its toolbar and its buttons are cut off; at 320 x 640 (the size of a 400 % zoom) the log is 71 px high. Nothing is lost: the log and the field scroll. The full footer follows under the chat.
- Why it matters: chat is a main path and mostly reading. The member scrolls inside a small box inside a scrolling page; at zoom, a low-vision user edits through a 71 px slit.
- Proposed fix: at small widths, give the log the height of the viewport minus the composer (`h-[calc(100dvh-…)]`), fold the description, the chips and the composer toolbar, drop the Markdown and key hints (R-S18), and no footer under a chat (R-S44). While a message is edited, collapse the main composer, use a compact edit form, let the field grow with its text, and scroll the whole edit item into view. `Components/Chat/ChatRoom.tsx:344`, `Pages/Spaces/Show.tsx`.
- Evidence: pictures `chat/space--full--member--360--light.png`, `chat/space--full--member--360--dark.png`, `messages/conversation--direct--member--360--light.png`, `chat/message--edit--member--360--light.png` (shows the log window, not a broken edit box); `a11y-chat360.mjs`: `360 {"log":[429,233],"editItem":[378,515],"textarea":[528,56,"scrollH",76]}`, `320 {"log":[478,71]}`.
- Sources: N25, M10, D9, A12. Four reviewers found it independently; the lead joined A12 to it.
- Checked: by the accessibility reviewer in the browser; by three reviewers in pictures.
- WCAG: 1.4.10 Reflow, AA (scrolling in a region passes the letter; the function is hard to use at zoom).
- Confidence: high.

#### R-S32 On a phone, Enter sends the chat message, so a member cannot write a second line

- Screen: Chat space, Conversation of two, Chat with a thread panel open; member; 360.
- What is wrong: Enter without Shift sends on every device. A phone keyboard has no Shift+Enter, so the "return" key sends half a message, and a member cannot write a list or two lines. The hint "Enter sends, Shift+Enter adds a line, Up arrow edits your last message" shows on phones too.
- Why it matters: lists and two-line messages are common; a sent half message then needs an edit.
- Proposed fix: on a coarse pointer (`matchMedia('(pointer: coarse)')`), Enter adds a line and the "Send" button sends; hide or change the hint there. `Components/Chat/ChatComposer.tsx:94-98,139`.
- Evidence: `Components/Chat/ChatComposer.tsx:95,139`; picture `chat/space--full--member--360--light.png`.
- Sources: N26, M11. Two reviewers found it independently.
- Checked: by two reviewers, in the code (and a picture for the hint). Not verified on a real phone.
- Confidence: medium.

#### R-S33 The chat opens below the "New messages" line and marks everything read

- Screen: Chat space (`/community/atlas-garden/space/lounge`), member, all widths.
- What is wrong: the member has 11 unread messages. The log scrolls to the newest message on load, so the "New messages" line is above the visible part. The room is marked read as soon as the last message renders.
- Why it matters: the unread marker exists, but the member does not see it and must guess where they stopped. The count in the sidebar goes to 0 before anything was read.
- Proposed fix: in `Components/Chat/ChatRoom.tsx`, on the first render with `firstUnreadCuid`, scroll that message to the top of the log; add a "Jump to the newest" button while the log is not at the bottom; mark the room read when the newest message was in view.
- Evidence: `Components/Chat/ChatRoom.tsx:81,99-112,379`; picture `chat/space--full--member--1920--light.png` (no divider in view; the sidebar count was 11 before the visit).
- Sources: M4.
- Checked: by one reviewer, in pictures and code (not checked by the lead).
- Confidence: high (the reviewer's).

#### R-S34 The thread panel blocks the chat at 1920 px

- Screen: Chat with a thread panel open (`/community/atlas-garden/space/lounge`), member, 1920.
- What is wrong: the thread opens in a modal `Drawer` that dims the room. With 1 400 px of free width, the member cannot read or answer the room while a thread is open.
- Why it matters: people follow a thread and the room at the same time; every switch costs a close and a reopen.
- Proposed fix: at `lg` and wider, render `ThreadPanel` as a column beside the log, not modal; keep the drawer below `lg`. `Components/Chat/ThreadPanel.tsx:58`.
- Evidence: picture `chat/thread--open--member--1920--light.png`; `Components/Chat/ThreadPanel.tsx:58`.
- Sources: M9.
- Checked: by one reviewer, in pictures and code.
- Confidence: high.

#### R-S35 Lists that load after the page show nothing while they load or fail, and the thread panel spins for ever

- Screen: Community home, "Catch up" card, while the data loads and when the data request fails; Thread panel, when the data request fails; Members list and Access groups, while the data loads; member and owner; 360 and 1920.
- What is wrong: only the notifications panel has a skeleton. The "Catch up" card renders nothing while it loads and nothing when its request fails, so the page jumps when it arrives, and the member cannot tell "nothing new" from "could not load". When a thread fails to load, the panel keeps the spinner for ever and a toast says why, then goes away: the error picture is the loading picture.
- Why it matters: the member waits on a spinner that never stops (chat with a thread is a main path), and does not see what they came back for.
- Proposed fix: a `Skeleton` building block for every list that loads after the page (`CatchUpCard`, `CommunityMembersManager`, `AccessGroupsManager`, `ThreadPanel`); an inline error with "Retry" in the thread panel and in the "Catch up" card ("Catch up could not load. Retry."), like the notifications panel. `Components/Notifications/CatchUpCard.tsx:134-136`, `Components/Chat/ChatRoom.tsx:313-320`, `Components/Chat/ThreadPanel.tsx:60`.
- Evidence: pictures `chat/thread--error--member--360--light.png` against `chat/thread--loading--member--360--light.png`, `community/home-catch-up--loading--member--360--light.png`, `community/home-catch-up--error--member--360--light.png`, `community/home-catch-up--error--member--1920--light.png`.
- Sources: D26, M26. Two reviewers found it independently. Their weights differed (D26 should, M26 later): the higher one is kept.
- Checked: by two reviewers, in pictures and code.
- Confidence: high.

#### R-S36 Unread state shows only in the community sidebar

- Screen: Dashboard (`/dashboard`), member, 1920; Chat space and other community pages at 360 px (button "Spaces"), member.
- What is wrong: the dashboard cards show name, description and visibility, nothing new, sorted by name. At 360 px the sidebar is behind a "Spaces" button with no dot and no count, and "Messages 7" is inside that drawer. A direct message creates no notification today (planned in #1392).
- Why it matters: a member of three communities cannot see where something happened; on a phone, seven unread direct messages stay hidden.
- Proposed fix: on `CommunityBox`, a dot for new content and the count of unread direct messages; sort the dashboard by last activity. On the "Spaces" button (`Components/Spaces/CommunityShell.tsx:111-114`), a dot when a space has new content and the count of unread direct messages.
- Evidence: pictures `dashboard/home--full--member--1920--light.png`, `chat/space--full--member--360--light.png`; `Components/Spaces/CommunityShell.tsx:49-53,111-114`; `Pages/Dashboard.tsx:28-35`.
- Sources: M5.
- Checked: by one reviewer, in pictures and code.
- Confidence: high.

#### R-S37 A long thread does not say which comments are new

- Screen: Post with comments, replies and reactions, member, all widths.
- What is wrong: the member was away nine days. Two of the nine comments are new, but they look the same as the others. No "new" mark, no "jump to the first new comment", no count of new comments on the post card.
- Why it matters: in a busy thread the returning member reads everything again or misses the reply.
- Proposed fix: keep the last visit per post and member (the "Catch up" query already counts new comments per post, `BuildCatchUp.php:219`); mark newer comments, show "N new" on `PostCard`, and land on the first new comment.
- Evidence: picture `discussion/post--full--member--1920--light.png`; `Components/Comments/CommentSection.tsx:249-262`; `Components/Posts/PostCard.tsx:34`.
- Sources: M6.
- Checked: by one reviewer, in pictures and code.
- Confidence: high.

#### R-S38 The Notifications page shows the same item up to three times, with two "I am caught up" buttons

- Screen: Notifications page (`/notifications`), member, 1920, light.
- What is wrong: "Who wants to share an order of seeds?" is in "Catch up" and in "Needs you"; "The garden hall is closed next week" in "Catch up" and in "Following"; "My tomatoes have yellow leaves" in all three. The "Catch up" card and the "Following" section each have a button "I am caught up". Both call the same request (`knowiiApiClient.notifications.caughtUp()`), which hides the card and clears "Following".
- Why it matters: the member triages the same thing twice and cannot tell what each button clears.
- Proposed fix: on the page and in the panel, leave out of "Catch up" the lines that are already in "Needs you" or "Following", or show "Catch up" only on the community home. Keep one "I am caught up", or give the two buttons labels that say what they do.
- Evidence: picture `notifications/page--full--member--1920--light.png`; `Components/Notifications/CatchUpCard.tsx:45,105,158`; `Components/Notifications/NotificationsPanel.tsx:562-581,755-761`.
- Sources: M13, W21. Two reviewers found it independently. W21 says that the two buttons clear different things (one community card, all of "Following"); M13 says that they send the same request. The code settles it for M13: both call `caughtUp()` and both hide the card and clear "Following" (`CatchUpCard.tsx:45` says so in its comment).
- Checked: by two reviewers, in pictures and code; the contradiction was settled in the code during the merge.
- Confidence: high.

#### R-S39 "Catch up" lines lose the space and the time

- Screen: Community home, "Catch up" card, member, all widths.
- What is wrong: a line shows "space, time" only when it has no excerpt. Three of four lines have an excerpt, so they say neither where nor when.
- Why it matters: after nine days away, "where" and "how old" decide what to open first.
- Proposed fix: in `Components/Notifications/CatchUpCard.tsx:166-176`, always show "space · time".
- Evidence: picture `community/home--full--member--1920--light.png`; `Components/Notifications/CatchUpCard.tsx:170-175`.
- Sources: M14.
- Checked: by one reviewer, in pictures and code.
- Confidence: high.

#### R-S40 The notification for a new lesson reads as broken English

- Screen: Notifications page (`/notifications`), member, 1920, light.
- What is wrong: "Lesson Pruning of Balcony course is out". The title is not set apart, and "is out" is an idiom.
- Why it matters: a non-native reader cannot parse it on one read, on a main path.
- Proposed fix: in `libs/common/src/lib/types/notification.schema.ts:177`: "New lesson in Balcony course: Pruning", the same pattern as "Maya Moderator posted in Announcements: The garden hall is closed next week".
- Evidence: picture `notifications/page--full--member--1920--light.png`; `notification.schema.ts:177`.
- Sources: W20.
- Checked: by one reviewer, in pictures and code.
- Confidence: high.

#### R-S41 The notification level of a space has two different controls

- Screen: Notification level of a space, menu open (`/community/atlas-garden/space/garden-talk`), and Notification settings (`/user/notifications`), member, 1920, dark.
- What is wrong: in the space, the level is a radio group with one sentence per level. In the settings page it is a bare `Select` with only the level names: 14 selects in a row, each labelled "Notifications for <space>", without the emoji of the space.
- Why it matters: the settings page hides the meaning of "Only what is for me" and "Muted" exactly where the member compares all spaces.
- Proposed fix: one `NotificationLevelPicker` in both places; in the settings, a compact row per space (emoji, name, segmented "Everything / For me / Muted") and the explanation once at the top of the group. `Components/Notifications/SpaceLevelControl.tsx:68`, `Pages/Profile/Partials/SpaceLevelsForm.tsx`.
- Evidence: pictures `discussion/space-level--menu--member--1920--dark.png`, `notifications/settings--full--member--1920--dark.png`.
- Sources: D7.
- Checked: by one reviewer, in pictures and code.
- Confidence: high.

#### R-S42 The main menu holds one link, the user menu holds "API tokens", and there is no help

- Screen: Main menu open (`/dashboard`), member, 360; User menu open, new-member, 360; header of every signed-in page, 1920 and 360.
- What is wrong: the header has four square controls (theme, bell, menu, avatar). The menu button opens a drawer whose only entry is "Dashboard". At 360 px the "Spaces" button of a community uses the same bars icon for another drawer. The user menu holds "Edit profile", "Notification settings", "API tokens", "Log out". No "Help" anywhere; the footer offers "Self-hosting guide", "Source First", "Licence (AGPL-3.0)", "Contributing".
- Why it matters: two clicks for one link, two identical icons with two targets on one phone screen, a developer word in the first menu a newcomer opens, and no place to ask when lost.
- Proposed fix: replace the menu button by a link to the dashboard (a house icon, or the logo: choose one), and keep the bars icon for the community drawer. Move "API tokens" into the account settings; add "Help" to the user menu and put the help link first in the footer. `Layouts/AppLayout.tsx:137,179-202`, `Components/MainMenu.tsx`, `Components/Footer.tsx`. A change of the shell: the owner decides (decision 8).
- Evidence: pictures `dashboard/main-menu--open--member--360--light.png`, `dashboard/main-menu--open--member--360--dark.png`, `dashboard/user-menu--open--new-member--360--light.png`, `community/home--full--member--360--light.png`.
- Sources: N29, D24. Two reviewers found it independently.
- Checked: by two reviewers, in pictures and code.
- Confidence: high.

#### R-S43 The start page has three names, and the account and notification pages do not match their menu items

- Screen: Dashboard, User menu open, Account settings, Notification settings, Notifications page; member; 1920; light.
- What is wrong: the start page is "Dashboard" in the menu and the tab, "Home" in the breadcrumb, "My Communities" as heading. "Edit profile" opens a page with password, two-factor, sessions and account deletion; its tab says "Profile". "Notification settings" opens a page whose tab and breadcrumb say "Notifications": both pages have the title "(1) Notifications - Knowii". The menu item "Catch up" opens the Notifications page.
- Why it matters: two browser tabs with the same name for two pages; a member who wants to change the password does not look under "Edit profile".
- Proposed fix: start page "Home" everywhere, heading "Your communities" (`Layouts/AppLayout.tsx:137`, `Components/PageHeader.tsx:69`, `Pages/Dashboard.tsx:98-99`). Account page "Account settings" in menu, breadcrumb and tab (`AppLayout.tsx:179`, `Pages/Profile/Show.tsx:31,34`). Notification settings: `browserPageTitle="Notification settings"` and the same breadcrumb, with a visible h1 (`Pages/Profile/Notifications.tsx:51-55`). User menu: "Notifications" instead of "Catch up", shown always (`AppLayout.tsx:167`).
- Evidence: pictures `dashboard/user-menu--open--member--1920--light.png`, `account/profile--full--member--1920--light.png`; `a11y-out/tree.log` (titles of `member/notifications` and `member/notif-settings`).
- Sources: W7, A15. Two reviewers found it independently. Their weights differed (W7 should, A15 later): the higher one is kept.
- Checked: by the accessibility reviewer in the browser (the titles); by one reviewer in pictures and code (the names).
- WCAG: 2.4.2 Page Titled, A (the titles).
- Confidence: high.

#### R-S44 The full marketing footer shows under every signed-in screen, also under a chat

- Screen: every page in `AppLayout`; Chat space, Dashboard; member; 1920 and 360; both themes.
- What is wrong: the footer is a 420 px block at 1920 px and about 900 px at 360 px, with 21 links and a 56 px "Knowii" wordmark, the largest text of every signed-in page (page titles are 24 to 30 px). Under a chat, the page scrolls past the chat into the footer.
- Why it matters: it pulls the eye away from the work and doubles the page length on a phone.
- Proposed fix: the existing `Footer variant="compact"` (used by the sign-in and error pages) in `Layouts/AppLayout.tsx:236`; the full footer for the public pages. A change of the shell: the owner decides (decision 8).
- Evidence: pictures `chat/space--full--member--360--dark.png`, `dashboard/home--full--member--1920--dark.png`; `Components/Footer.tsx:57`.
- Sources: D8.
- Checked: by one reviewer, in pictures and code.
- Confidence: high.

#### R-S45 The time zone question shows raw zone codes and no reason

- Screen: Time zone question at the top of the page (`/dashboard`), member, 360, light.
- What is wrong: "Show times in America/New_York?" with "Yes, use America/New_York" and "Keep Europe/Brussels". The codes have a slash and an underscore, and no line says why it asks.
- Why it matters: a non-technical person reads it as a technical prompt and may press either button.
- Proposed fix: the readable name, as the notification settings already show: "You seem to be in New York time. Show times in New York time (EST)?" with "Yes" / "No, keep Brussels time". `Components/TimeZone/TimeZoneAsk.tsx:103-112`.
- Evidence: pictures `account/time-zone-question--open--member--360--light.png`, `notifications/settings--new--new-member--1920--light.png`.
- Sources: N27.
- Checked: by one reviewer, in pictures and code.
- Confidence: high.

#### R-S46 "Leave the community" says that coming back needs an invitation, which is false for an open community

- Screen: About the community (`/community/atlas-garden/about`) and Leave the community, dialog; member; 1920; light.
- What is wrong: "To come back, you need a new invitation." Leaving creates no join block (only a removal does), so in an open community the person can press "Join" again.
- Why it matters: the text can stop someone from leaving, or from coming back; in a community by invitation, the reverse mistake is possible if the text changes for all.
- Proposed fix: pass the join policy and show one sentence: "You can join again at any time." or "To come back, you need a new invitation." `Components/Communities/LeaveCommunityButton.tsx:46`, `Pages/Communities/About.tsx:221`.
- Evidence: `app/Actions/CommunityMembers/LeaveCommunity.php`; `app/Actions/CommunityMembers/RemoveCommunityMember.php:39`; picture `community/about--full--member--1920--light.png`.
- Sources: N28, W10. Two reviewers found it independently. The part of N28 about the visibility text is already decided (#1500, #1512).
- Checked: by two reviewers, in pictures and code.
- Confidence: high.

#### R-S47 The lesson page has no course outline

- Screen: Lesson that is not done (`/community/atlas-garden/space/balcony-course/page/watering`), member, 1920 and 360.
- What is wrong: the lesson shows "Lesson 5 of 8", Previous and Next. The list of lessons is only on the course page. At 1920 px the right half of the page is empty.
- Why it matters: going back to a quiz or skipping to lesson 7 costs a trip to the outline.
- Proposed fix: the outline (sections, check marks, current lesson) in a column at `xl`, and in a disclosure "Lessons (3 of 8 done)" at small widths; reuse the list of `Components/SpacePages/PageList.tsx`.
- Evidence: pictures `course/lesson--not-done--member--1920--light.png`, `course/outline--full--member--1920--light.png`.
- Sources: M19.
- Checked: by one reviewer, in pictures only.
- Confidence: high.

#### R-S48 Resource rows start with two unexplained glyphs and end with "Link only"

- Screen: Resources space (`/community/atlas-garden/space/reading-list`) and Community home, member, 1920 and 360.
- What is wrong: each title starts with a level emoji (🌱 🌿 🌳 🏆 ❓) and a type icon. The level emoji is `aria-hidden`, has no title and no legend; "❓" (no level) reads as an error. At 360 px the two glyphs push the title to three lines. Every row ends with "Link only" in 12 px. The resource page shows the level as a word ("Beginner"); the list does not.
- Why it matters: the member cannot use the level to choose what to read, which is why it exists.
- Proposed fix: in `Components/Resources/ResourceCard.tsx:31-32`, the type icon only before the title; level and capture state as one `MetaLine` under the description ("Beginner · Article"); nothing for "no level"; no "Link only" in the list. Filtering by level is #273.
- Evidence: pictures `resources/space--full--member--1920--light.png`, `resources/space--full--member--1920--dark.png`, `community/home--full--member--360--light.png`, `resources/resource--full--member--1920--light.png`.
- Sources: M21, D31. Two reviewers found it independently. Their weights differed (M21 should, D31 later): the higher one is kept.
- Checked: by two reviewers, in pictures and code.
- Confidence: high (M21), medium (D31).

#### R-S49 The resources space is described as "curated articles", but members add any kind of link

- Screen: Resources space, Resources space without resources, New space, type resources, visitor on a resources space.
- What is wrong: the type says "Curated links to articles"; the empty state says "Curated articles will show here."; in "Reading list" members add, and the types include video, book, podcast, tool. The visitor prompt says "Sign in to post" while the action is "Add a resource".
- Why it matters: the text says what the space does not do, and "curated" is a hard word.
- Proposed fix: type "Useful links: articles, videos, books, tools"; empty state "Useful links will show here."; visitor prompt "Sign in to add a resource". `libs/common/src/lib/types/space.schema.ts:35`, `Components/Resources/ResourceList.tsx:111,118`, `Pages/Spaces/Show.tsx:60`.
- Evidence: the lines above; `resource.schema.ts:13-19`.
- Sources: W22.
- Checked: by one reviewer, in the code only.
- Confidence: high.

#### R-S50 The account deletion form lets a member think that the box also removes resources

- Screen: Delete account, dialog (`/user/profile`), member.
- What is wrong: before the box, the form says that posts, comments, resources and messages stay, "or tick the box below". With the box ticked, ADR 0043 keeps resources, pages and lessons (by design) and keeps an empty marker for a post or a comment with replies of others. The box text ("Also delete my posts, comments and messages") is true; the sentence before it suggests more. The section text lists "your avatar" and says nothing about messages.
- Why it matters: a member who wants their content gone ticks the box and believes it is done, but their resources stay.
- Proposed fix: one help line under the box, for example "Your resources, pages and lessons stay, under the name "Deleted member". A post or a comment that has replies stays as an empty marker." The owner approved these words on 2026-11-15 (#1344), so the new line is his decision (decision 4). `Pages/Profile/Partials/DeleteUserForm.tsx:52,80,107`.
- Evidence: `Pages/Profile/Partials/DeleteUserForm.tsx:52,80,107`; `app/Actions/Users/DeleteMemberContent.php:19-26`.
- Sources: W2. The reviewer gave "must"; the lead lowered it to "should".
- Checked: by the lead in the code (partly confirmed).
- Confidence: high.

#### R-S51 The "Delete the community" dialog does not say what goes

- Screen: Delete the community, dialog (`/community/atlas-garden/settings`), owner, 1920, light.
- What is wrong: title "Are you sure you want to delete this community?"; body "Please enter the full community name to confirm you would like to permanently delete it: "Atlas Garden"." The list of what is deleted is only on the page behind the backdrop, and vague ("its spaces, posts, resources and interactions"). The field has a lock icon, as if it asks for a password. The button is "Go ahead!"; the section title is "Delete Community".
- Why it matters: this is the most destructive action of the product; the dialog itself must say what goes, who is affected and that it cannot be undone.
- Proposed fix: in `Pages/Communities/Partials/DeleteCommunityForm.tsx`: title "Delete Atlas Garden?"; body "All its spaces, posts, comments, chat messages, direct messages, pages, courses, resources and members' progress are deleted. Its members lose access. This cannot be undone."; label "Type the name of the community to confirm: Atlas Garden"; no lock icon; button "Delete the community"; toast "Atlas Garden deleted". Build it on a `ConfirmDialog` building block.
- Evidence: picture `settings/delete-community--dialog--owner--1920--light.png`; `DeleteCommunityForm.tsx:47,79-118`.
- Sources: W8.
- Checked: by one reviewer, in pictures and code.
- Confidence: high.

#### R-S52 Four dialogs confirm with "Go ahead!"

- Screen: New community dialog, Delete the community, dialog, Delete account, dialog, Log out other browser sessions, dialog.
- What is wrong: the confirm button is "Go ahead!" in four dialogs; every other dialog names the action ("Delete", "Leave", "Remove", "Transfer").
- Why it matters: the label does not say what happens, and two of the four actions cannot be undone.
- Proposed fix: "Create the community", "Delete the community", "Delete my account", "Sign out of other browsers". `Components/Communities/CreateCommunityDialog.tsx:103`, `Pages/Communities/Partials/DeleteCommunityForm.tsx:106`, `Pages/Profile/Partials/DeleteUserForm.tsx:73`, `Pages/Profile/Partials/LogoutOtherBrowserSessionsForm.tsx:112`.
- Evidence: the four lines above.
- Sources: W9.
- Checked: by one reviewer, in the code only.
- Confidence: high.

#### R-S53 Community settings is one page of 5 300 px with no section navigation

- Screen: Community settings, every section (`/community/atlas-garden/settings`), owner and admin, 1920 and 360.
- What is wrong: ten sections (details, direct messages, who can join, search engines, spaces, moderator rights, members, invitations, access groups, ownership, delete) follow each other on one page, with no tabs, no anchors and no table of contents. The page is 5 336 px at 1920 px; the danger zone is at the bottom of the same scroll.
- Why it matters: "change a role" means scrolling past the space tree every time; a moderator sees another subset in the same layout.
- Proposed fix: sub-pages or tabs with their own address (`/settings/general`, `/settings/spaces`, `/settings/members`, `/settings/access`) so that links and the back button work; as a first step, anchors and a sticky list of sections. `Pages/Communities/Settings.tsx:80-170`.
- Evidence: picture `settings/community--full--owner--1920--light.png`.
- Sources: M15.
- Checked: by one reviewer, in pictures and code.
- Confidence: high.

#### R-S54 The community settings page uses two layouts

- Screen: Community settings, every section (`/community/atlas-garden/settings`), owner, 1920, both themes; Account settings; Notification settings.
- What is wrong: the first ten sections are full-width cards with `CardHeader`. "Transfer the ownership" and "Delete Community" switch to the two-column `ActionSection` (title left, card right). Account settings and notification settings are all two-column. Field widths follow: full card width in one, about two thirds in the other.
- Why it matters: the eye loses its column at the bottom of the longest page, and the two settings areas look like two products.
- Proposed fix: one settings layout for every settings page. The one-column card fits narrow screens and the space tree: move `DeleteCommunityForm`, `TransferOwnershipForm` and the `Pages/Profile/Partials` forms to it (or the reverse), then remove the other pattern from settings. `DeleteCommunityForm.tsx:79`, `Components/FormSection.tsx:16`.
- Evidence: pictures `settings/community--full--owner--1920--dark.png` (bottom), `account/profile--full--member--1920--light.png`.
- Sources: D4.
- Checked: by one reviewer, in pictures and code.
- Confidence: high.

#### R-S55 A role changes from a select, at once, without undo

- Screen: Role of a member, menu open (`/community/atlas-garden/settings`), owner and admin, all widths.
- What is wrong: the role select in each row calls the server on change. A slip with the arrow keys on the focused select makes a member "Admin". Only a toast follows: no confirmation, no undo.
- Why it matters: an admin can change the community and remove people; granting it needs a deliberate step.
- Proposed fix: confirm when the new role is higher than the old one, or show "Undo" in the toast for 8 seconds, as the notifications do. `Components/Communities/CommunityMembersManager.tsx:171-182`.
- Evidence: picture `settings/members--role-menu--owner--1920--light.png`; `CommunityMembersManager.tsx:171-182`.
- Sources: M17.
- Checked: by one reviewer, in pictures and code.
- Confidence: high.

#### R-S56 Adding a person to an access group needs their exact user name or email address

- Screen: Members of an access group, dialog (`/community/atlas-garden/settings`), owner, all widths.
- What is wrong: the field "Username or email" has no suggestions. "New message" has a search of members by name (`MemberPicker`).
- Why it matters: the manager must look up the user name elsewhere and type it exactly; two screens that pick a member behave differently.
- Proposed fix: move `Components/Messages/MemberPicker.tsx` to `Components/ui` and use it in `Components/Communities/AccessGroupsManager.tsx:303` and in the ownership transfer (`Pages/Communities/Partials/TransferOwnershipForm.tsx:82`).
- Evidence: picture `settings/access-group--members-dialog--owner--1920--light.png`; `MemberPicker.tsx:23-27`.
- Sources: M18.
- Checked: by one reviewer, in pictures and code.
- Confidence: high.

#### R-S57 Four ways to save a form

- Screen: Account settings, Community settings, Resource, Notification settings; 1920; both themes.
- What is wrong: (a) a grey footer strip with "Reset" and "Save", "Save" always enabled (`Components/FormSection.tsx:27`, `UpdateProfileInformationForm.tsx:214`); (b) "Save" at the bottom right of the card, disabled until a change (`UpdateCommunityForm.tsx:155`, `ModeratorPermissionsForm.tsx:84`); (c) a small "Save" at the bottom left of a well (`ResourceChangesForm.tsx:70`); (d) no button, saved at once.
- Why it matters: the place of the button and its state change from card to card; a member meets (a) and (d) from the same user menu.
- Proposed fix: one `FormActions` block (right aligned, "Save" primary, optional "Reset" ghost, disabled until something changed) for forms; `Switch` and `SaveStatus` for instant settings (R-M9).
- Evidence: pictures `account/profile--full--member--1920--light.png`, `settings/community--full--owner--1920--dark.png`, `resources/resource--full--member--1920--light.png`.
- Sources: D5.
- Checked: by one reviewer, in pictures and code.
- Confidence: high.

#### R-S58 Three kinds of feedback after an instant save

- Screen: Community settings, Account settings, Notification settings, Discussion space header.
- What is wrong: community toggles and "Online status" show a toast; notification settings show a sticky "Saved" bar at the top of the page; the level control of a space shows an inline "Saved". Errors are an inline "Not saved. Retry." in some places and a toast in others.
- Why it matters: the same act gets three answers in three places.
- Proposed fix: one `SaveStatus` next to the control (inline "Saved" / "Not saved"), for every instant setting; toasts only for actions that leave the screen. `DirectMessagesSetting.tsx`, `IndexingSetting.tsx:30`, `OnlineStatusForm.tsx:34`, `Pages/Profile/Notifications.tsx:57-60`, `SpaceLevelControl.tsx:105`.
- Evidence: the lines above; picture `notifications/settings--full--member--1920--dark.png`.
- Sources: D6.
- Checked: by one reviewer, in the code only.
- Confidence: high.

#### R-S59 Forced colors: checked checkboxes and radio buttons look unchecked, and filled buttons lose their shape

- Screen: Quiz before the first attempt (`/community/atlas-garden/space/balcony-course/page/quiz-water-and-compost`), Sign-up page (age and terms box), Notification settings ("Show pop-ups"); member and visitor; 1280; forced colors.
- What is wrong: `Checkbox.tsx` and `Radio.tsx` use `appearance-none` and draw the checked state with a magenta fill, a white check and a magenta dot. Forced colors remove the fill and the dot: the checked "In the morning", "Dry leaves" and the checked sign-up box look like the unchecked ones. Filled buttons ("Register", "Send") show no border and look like plain text.
- Why it matters: users of high-contrast modes cannot see their own answers and choices.
- Proposed fix: `forced-colors:appearance-auto` on the two inputs, or `forced-colors:checked:bg-[CanvasText]` for the box and the dot; `border border-transparent` on `Button`.
- Evidence: `a11y-out/forced2-quiz.png`, `a11y-out/forced2-register-checked.png` (Playwright `check()` confirmed the checked state).
- Sources: A11.
- Checked: by the accessibility reviewer in the browser.
- WCAG: related to 1.4.11 Non-text Contrast, AA (WCAG does not test forced colors directly).
- Confidence: high.

#### R-S60 Profile fields have a wrong or missing input purpose

- Screen: Account settings, every section (`/user/profile`), member, all widths.
- What is wrong: the Knowii user name field has `autocomplete="username"`, the same value as the email field above it, so a password manager can write the email address into the user name field and the person saves it without seeing it. The phone field has no `autocomplete="tel"`; the website field has no `autocomplete="url"`.
- Why it matters: a wrong user name can be saved by accident.
- Proposed fix: `autocomplete="email"` on the email field (or keep `username` there only); `autocomplete="off"` or `nickname` on the Knowii user name; `tel` on the phone; `url` on the website. `Pages/Profile/Partials/UpdateProfileInformationForm.tsx:241,258,300,343-353`.
- Evidence: `UpdateProfileInformationForm.tsx:241,258,300,343-353`.
- Sources: A13.
- Checked: by one reviewer, in the code only.
- WCAG: 1.3.5 Identify Input Purpose, AA.
- Confidence: high.

#### R-S61 Error messages follow five patterns

- Screen: all screens with an action that can fail.
- What is wrong: the same kind of failure is said five ways: "Failed to save the community." (about 130 client texts), "We could not mark it as done. Try again.", "Not saved. Retry." (six places, where "Retry" is plain text, not a button), "Could not copy the link", and a summary plus "Please try again later.". "Failed to ..." does not say what to do; "Please" is in about 10 messages and not in the others.
- Why it matters: a member learns one pattern and expects it; "Retry" reads as an order and does not say how.
- Proposed fix: one pattern: "We could not <verb> the <thing>. Try again." Replace "Failed to" in `libs/common/src/lib/api/knowii-api-client.ts`; drop "Please"; "Not saved. Change it again to retry." in `PopupsForm.tsx:52`, `SpaceLevelsForm.tsx:203,222`, `TimeZoneForm.tsx:70`, `SpaceLevelControl.tsx:110`, `ThreadFollowControl.tsx:140`, `TimeZoneAsk.tsx:116`.
- Evidence: the lines above; `NotificationsPanel.tsx:327`; `CommentSection.tsx:167`; `DeleteCommunityForm.tsx:57-58`; `CreateCommunityDialog.tsx:78-79`.
- Sources: W13.
- Checked: by one reviewer, in the code only.
- Confidence: high.

#### R-S62 The texts of the older account and password pages are long and in another voice

- Screen: Forgot password page, Confirm password page, Account settings (password, browser sessions, delete account).
- What is wrong: "Forgot your password? No problem. Just let us know your email address and we will email you a password reset link that will allow you to choose a new one."; "This is a secure area of the application. Please confirm your password before continuing."; "Ensure your account is using a long, random password to stay secure."; "If necessary, you may log out of all of your other browser sessions across all of your devices. Some of your recent sessions are listed below; however, this list may not be exhaustive. If you feel your account has been compromised, you should also update your password."; "Click here to re-send the verification email."
- Why it matters: long sentences and words such as "exhaustive", "compromised", "secure area of the application" are hard on one read for a non-native reader.
- Proposed fix: "Enter your email address. We send you a link to choose a new password." and "Send the link"; the confirm page uses the text of the dialog ("For your security, confirm your password to continue."); "Use a long password that you use nowhere else."; "These browsers are signed in to your account. If you do not know one of them, sign them all out and change your password."; "Enter your password to confirm."; "Send the verification email again". `Pages/Auth/ForgotPassword.tsx:36`, `Pages/Auth/ConfirmPassword.tsx:35`, `UpdatePasswordForm.tsx:60`, `LogoutOtherBrowserSessionsForm.tsx:59,119`, `DeleteUserForm.tsx:83`, `UpdateProfileInformationForm.tsx:275`.
- Evidence: the lines above; picture `auth/forgot-password--unknown-address--visitor--1920--light.png`.
- Sources: W16.
- Checked: by one reviewer, in pictures and code.
- Confidence: high.

#### R-S63 "Only moderators can post" is false: the owner and the admins post too

- Screen: New space and Edit a space, dialog; Community settings (spaces list); owner.
- What is wrong: the option is "Only moderators can post" and the list shows " · Moderators post". The policy lets the owner, admins and moderators post. Elsewhere the product calls these three "the team".
- Why it matters: an admin can think they cannot post in an announcements space.
- Proposed fix: "Only the team can post (owner, admins, moderators)" and " · Team posts". `libs/common/src/lib/types/space.schema.ts:49`, `Components/Spaces/SpaceTreeEditor.tsx:174`.
- Evidence: `space.schema.ts:48-49`; `SpaceTreeEditor.tsx:174`; `app/Policies/SpacePolicy.php:63-67,101-107`.
- Sources: W18.
- Checked: by one reviewer, in the code only.
- Confidence: high.

#### R-S64 The team has five other names: manager, managers, curator, "a member who manages the spaces", moderators

- Screen: Community settings (who can join), Resource, Link space, join messages.
- What is wrong: "People join when a manager invites them."; "Ask its managers for an invitation"; "the curator kept only the link"; "A member who manages the spaces can fix it." The roles are Owner, Admin, Moderator, Member, and the product calls them together "the team".
- Why it matters: a member looks for a "manager" in the team list and finds none.
- Proposed fix: "People join when the team invites them."; "Ask the team for an invitation."; "The member who added it kept only the link."; "The team can fix it." `Components/Communities/JoinPolicySetting.tsx:13`, `app/Actions/CommunityMembers/JoinCommunity.php`, `Components/Resources/ResourceDetails.tsx:118`, `Pages/Spaces/Show.tsx:61`.
- Evidence: the lines above; `member.schema.ts:35-38`.
- Sources: W19.
- Checked: by one reviewer, in the code only.
- Confidence: high.

#### R-S65 "Tell the followers of this space now" names a group that does not exist

- Screen: New post, composer open, moderator.
- What is wrong: "Tell the followers of this space now" and "Without it, the post tells them after 5 minutes. An edit or a deletion in that time tells nobody." Members do not follow a space; they choose a level ("Everything", "Only what is for me", "Muted").
- Why it matters: the moderator cannot know who gets the notification.
- Proposed fix: box "Notify now"; help "Members whose level is Everything get a notification after 5 minutes. Tick this to send it now. If you edit or delete the post in those 5 minutes, nobody gets it." `Components/Posts/PostComposer.tsx:165-167`.
- Evidence: `PostComposer.tsx:165-167`.
- Sources: W23.
- Checked: by one reviewer, in the code only.
- Confidence: high.

#### R-S66 "Member since 3 months ago", and four date formats

- Screen: Member profile of another member, About the community, Account settings (connected accounts), API tokens, time tooltips.
- What is wrong: "Member since" and "Created" are followed by a relative time ("Member since about 1 year ago"). Absolute dates use three formats: "November 17th, 2026", the browser locale (a French browser shows "17 novembre 2026" inside English text), and "Nov 17, 2026, 10:00:00 AM CET" in tooltips.
- Why it matters: "since ... ago" is wrong English, and a date in another language inside an English sentence confuses.
- Proposed fix: "Member since" and "Created" with an absolute date ("November 2025"); one date function in `libs/common` with a fixed `en` locale and one format, used in all places; no seconds in the tooltip. `Pages/Members/Show.tsx:96`, `Pages/Communities/About.tsx:134`, `ConnectedAccountsForm.tsx:18`, `EntitlementsCard.tsx:11`, `Pages/API/Index.tsx:70`, `libs/common/src/lib/utils/time-zone.fn.ts:79`.
- Evidence: the lines above.
- Sources: W24.
- Checked: by one reviewer, in the code only.
- Confidence: high.

#### R-S67 Empty states: two patterns, missing in one place, and never with their action

- Screen: Notifications page without notifications, Dashboard, search without a result, Course without lessons, Direct messages, Discussion space without posts; 1920; dark.
- What is wrong: the dashed `EmptyState` sits next to a plain muted sentence on the same screen ("Nothing needs you." boxed, "Nothing new in what you follow." plain); about 15 places use the plain sentence. A dashboard search without a result shows no text, only the "New community" tile. The course shows "0 lessons" above "No lessons yet". The action ("New post", "Add a page") sits above the empty box; the `action` prop of `EmptyState` is used by no space.
- Why it matters: the empty screen must say what to do first; here the eye must find the button elsewhere.
- Proposed fix: `EmptyState` with a `size="inline"` variant; a "No community matches" state on the dashboard; the primary action in `action`; no count when it is 0. `NotificationsPanel.tsx:721,790`, `CommentSection.tsx:356`, `ConversationList.tsx:19`, `Dashboard.tsx:124`, `Pages/Communities/Public.tsx:100`.
- Evidence: pictures `notifications/page--empty--new-member--1920--dark.png`, `dashboard/home--no-match--member--1920--dark.png`, `course/outline--empty--member--1920--dark.png`, `discussion/space--empty--owner--1920--dark.png`.
- Sources: D25.
- Checked: by one reviewer, in pictures and code.
- Confidence: high.

#### R-S68 Three content widths with no rule, and half the screen empty at 1920 px

- Screen: community pages, Notifications page, Member profile of another member; 1920; both themes.
- What is wrong: posts, chat, pages, courses and member profiles use `readable-width` (768 px), so 470 px stay empty on the right. Resources, Messages, About and the community home use the full frame. Notifications use `max-w-6xl` (1152 px), where an excerpt runs about 150 characters per line. The member profile is a 420 px card in a 768 px column.
- Why it matters: moving from a space to Messages shifts the whole layout; long lines are slow to read; the empty right side looks unfinished.
- Proposed fix: one rule: reading content (posts, comments, notification lines, lessons) at `readable-width`, lists of cards and tools at the full frame; `readable-width` on the notification lines. Using the empty right side is a proposal for the owner (see "Proposals for the look of the whole"). `Components/Spaces/CommunityShell.tsx:137`, `Components/PageContentWrapper.tsx:12`.
- Evidence: pictures `discussion/space--full--member--1920--dark.png`, `messages/conversation--direct--member--1920--light.png`, `notifications/page--full--member--1920--dark.png`, `community/member-profile--other--member--1920--light.png`.
- Sources: D10.
- Checked: by one reviewer, in pictures and code.
- Confidence: high.

#### R-S69 Nine type sizes, five weights and about 20 heading styles

- Screen: all.
- What is wrong: in the code: sizes `xs` (88), `sm` (244), `base` (12), `lg` (20), `xl` (6), `2xl` (13), `3xl` (3), `4xl` (2), `text-[10px]` (`HereNowButton.tsx:36`) and the global h1 at 5xl/6xl; weights `bold` (132), `extrabold` (36), `medium` (10), `semibold` (1), `normal` (2); about 20 class strings on h1/h2/h3. Section titles have four looks: `CardHeader` (18 px, bar, icon), `SectionTitle` (18-20 px, bar), a plain 20 px h2 ("Spaces", `SpaceTreeEditor.tsx:87`), plain 16 px ("Catch up", `CatchUpCard.tsx:142`) and 14 px ("Needs you", `NotificationsPanel.tsx:814`).
- Why it matters: the levels do not read as levels; a heading of one rank changes size from screen to screen.
- Proposed fix: named text styles in `styles.css` (`text-title`, `text-heading`, `text-subheading`, `text-label`, `text-body`, `text-meta`), used everywhere; no `font-medium`, `font-semibold` or `text-[10px]`.
- Evidence: grep counts by the reviewer; pictures `settings/community--full--owner--1920--dark.png`, `community/home--full--member--360--light.png`.
- Sources: D12.
- Checked: by one reviewer, in code and pictures.
- Confidence: high.

#### R-S70 Seven icon sets, two of them for the same glyphs

- Screen: header, breadcrumb, Community home, Community settings, Public page of a community.
- What is wrong: `react-icons/fa` (32 files) and `fa6` (61 files) give the same Font Awesome glyphs twice (`FaBars` in `AppLayout.tsx:18` and `CommunityShell.tsx:6`; `FaSearch` and `FaMagnifyingGlass`; and others). Three other families add outline icons among the solid ones: `GrGroup` (`CommunityIcon.tsx:17`, in every breadcrumb and community card), `TiGroupOutline` ("Members" card), `MdLayers` ("Recent Resources"), plus `FaRegNewspaper` on the public page.
- Why it matters: the breadcrumb shows a solid house next to an outline group; one screen mixes three drawing styles; two copies of one set cost bundle size (#1170).
- Proposed fix: `fa6` solid only (keep `si`/`bs` for brand marks); `FaUsers`, `FaUserGroup`, `FaLayerGroup` for the three outliers; one icon map in `Components/ui/icons.ts`.
- Evidence: pictures `community/home--full--member--1920--dark.png`, `public/community--full--visitor--1920--light.png`; the files above.
- Sources: D14.
- Checked: by one reviewer, in pictures and code.
- Confidence: high.

#### R-S71 Button heights and field heights do not line up

- Screen: Dashboard (filters), Community settings (invite row), Home page (waitlist), newsletter form.
- What is wrong: `Button` is 32/40/48 px; fields are 36/44/48 px. Pages patch it by hand: `className="h-9"` (`Dashboard.tsx:120`), `className="h-11"` (`WaitlistForm.tsx:111`, `NewsletterForm.tsx:119`), `sm:mt-7` (`CommunityMembersManager.tsx:267`).
- Why it matters: every new inline form needs a patch; without it the button sits 4 px short of the field.
- Proposed fix: align the two scales (`Button.tsx:28-32`, `form-field.ts:14-18`) and remove the four patches.
- Evidence: pictures `dashboard/home--full--member--1920--dark.png`, `settings/community--full--owner--1920--dark.png`; the lines above.
- Sources: D15.
- Checked: by one reviewer, in code and pictures.
- Confidence: high.

#### R-S72 Buttons and links are styled by hand outside `Button`

- Screen: Chat space, Post, Notifications page, Dashboard, Account settings, Verify your email address.
- What is wrong: hand-built buttons with their own classes: "Reply in thread" (`ChatMessageItem.tsx:118`), the add-reaction chip (`ReactionBar.tsx:156`), "1 here" (`HereNowButton.tsx:22`), the round "..." next to the rounded "Done" (`NotificationLine.tsx:197`), "Undo" and "Show all" as underlined text (`NotificationsPanel.tsx:628`, `CatchUpCard.tsx:200`, `NotificationLine.tsx:153`), three disclosure toggles (`SpaceLevelControl.tsx:68`, `NotificationsPanel.tsx:813`, `CommentSection.tsx:388`), the "New community" tile (`CommunityBox.tsx:22`), "Send the link again" (`UpdateProfileInformationForm.tsx:271`), "Log out" (`VerifyEmail.tsx:49`), the sign-out item of the user menu (`AppLayout.tsx:207`), the emoji search field (`EmojiPicker.tsx:252`).
- Why it matters: each has its own hover, focus, size and no busy state; the same action looks different by screen (R-S73).
- Proposed fix: `Button variant="ghost" size="sm"` for text actions, `Button iconOnly` as the `DropdownMenu` trigger, a `Disclosure` building block, `TextInput size="sm"` in the emoji picker, a `link` variant of `Button`.
- Evidence: pictures `notifications/page--full--member--1920--dark.png`, `chat/space--full--member--1920--dark.png`; the lines above.
- Sources: D16.
- Checked: by one reviewer, in code and pictures.
- Confidence: high.

#### R-S73 The same action has different looks

- Screen: Post, Chat space, Conversation of two, Account settings, Community settings, About the community.
- What is wrong: "Reply" is a ghost button with an icon under a comment and a pink text link under a chat message. "Cancel" is `secondary` in `CommentForm.tsx:100` and `ghost` in `ChatComposer.tsx:144`. The delete trigger is `ghost` on a post (`Posts/Show.tsx:152`), `ghost` text under a comment, `danger sm` for "Leave this community", `danger md` for "Delete Account". "Edit" is `secondary` on a post and `ghost` on a comment. In direct messages "Edit" and "Delete" always show under the own message; in the chat they do not.
- Why it matters: the member reads every row again to find the action.
- Proposed fix: one rule per kind of action, written in the toolkit (#657): row actions on content are `ghost sm` with icon and label; a destructive trigger is `ghost` with a danger-colored icon and its confirmation is `danger`; "Cancel" is always `secondary`. A `ContentActions` building block.
- Evidence: pictures `discussion/post--full--member--1920--dark.png`, `chat/space--full--member--1920--dark.png`, `messages/conversation--direct--member--1920--light.png`, `community/about--full--member--1920--dark.png`.
- Sources: D17.
- Checked: by one reviewer, in pictures and code.
- Confidence: high.

#### R-S74 The accent color is spent on avatars and chrome

- Screen: Notifications page, Chat space, Community settings (members list), header, footer; dark.
- What is wrong: the fallback avatar is a magenta disc for every member (`Avatar.tsx:41`). The accent also marks primary buttons (with a glow), counts, dots, breadcrumb chevrons (`Breadcrumb.tsx:74`), every card title bar, and the email button of the footer (`Footer.tsx:86`, a primary with glow for a mail link).
- Why it matters: on the notifications page the column of magenta discs is the strongest element; the accent no longer points at the one action.
- Proposed fix: a neutral fallback avatar (`bg-overlay/15 text-primary`, or a few muted hues taken from the name); chevrons in `text-muted`; the footer icons `secondary`; magenta for primary actions, counts and the current item. Colors of the whole product: the owner decides (decision 9).
- Evidence: pictures `notifications/page--full--member--1920--dark.png`, `settings/community--full--owner--1920--dark.png`.
- Sources: D21.
- Checked: by one reviewer, in pictures and code.
- Confidence: high.

### Later

#### R-L1 The error page uses emojis and "forbidden", sends a signed-in person to "Coming soon", and does not follow the system

- Screen: Error page (`Pages/Error.tsx`), 404, 403, 500, 503; any role; outside `local` (the atlas shows the framework page).
- What is wrong: the texts are "Whoops, something went wrong on our servers. 😔", "Sorry, the page you are looking for could not be found. 😭", "Sorry, you are forbidden from accessing this page. ⛔", "Sorry, we are doing some maintenance. Please check back soon. 😔". The only button, "Go back to the homepage", goes to `/`, the "Coming soon" page, also for a signed-in person. The page uses the global h1 and h2 sizes, a header without the signed-in controls, and the compact footer inside `main`; it does not use `EmptyState`, `PageTitle` or `AppLayout`.
- Why it matters: the tone differs from the rest of the product, screen readers read the emoji names, and a lost member is sent further away from their communities, where trust is lowest.
- Proposed fix: inside `AppLayout` when signed in, an `EmptyState` with one sentence and one `LinkButton` ("Go to my communities" for a signed-in person, the home page else). Texts: "This page does not exist, or it moved."; "Something went wrong on our side. Try again in a minute."; "Knowii is under maintenance. Come back in a few minutes."; no emojis; titles without the number. The 403 on community content is R-M1.
- Evidence: `Pages/Error.tsx:18-28,24-62`.
- Sources: N30, W27, D33. Three reviewers found it independently.
- Checked: by three reviewers, in the code only.
- Confidence: high.

#### R-L2 After "Email me a sign-in link", the form goes back to the password and empties the address

- Screen: Sign-in page, link by email, sent (`/login`), visitor, 360, light.
- What is wrong: the green message shows, but the form below switches back to "Email / Password / Log in" with an empty Email field.
- Why it matters: a newcomer may think they must still type a password.
- Proposed fix: after sending, show only the message, "Open the email on this device", and a "Send again" link. `Pages/Auth/Login.tsx:39`.
- Evidence: picture `auth/login-link--sent--visitor--360--light.png`.
- Sources: N33.
- Checked: by one reviewer, in pictures and code.
- Confidence: high.

#### R-L3 The sign-up button is disabled with no word why

- Screen: Sign-up page (`/register`), visitor, 1920, light.
- What is wrong: "Register" stays disabled until the bot check returns a token. No text says so; "Please wait for the bot check to finish." exists only in the newsletter and waitlist forms, and only after a click.
- Why it matters: when the check is slow or blocked (privacy add-ons), a newcomer cannot sign up and does not know why.
- Proposed fix: under the check, "Wait a moment: we check that you are not a robot."; if it fails to load, "The robot check did not load. Turn off the blocker for this site, or reload the page." `Pages/Auth/Register.tsx:192-218`.
- Evidence: picture `auth/register--errors--visitor--1920--light.png` (the atlas blocked the check); `Register.tsx:215`.
- Sources: W34.
- Checked: by one reviewer, in pictures and code.
- Confidence: medium.

#### R-L4 The notification settings of a new member say "This community has no space with notifications yet."

- Screen: Notification settings of a new member (`/user/notifications`), new-member, 1920, light.
- What is wrong: under "Your spaces", the sentence names "This community", but no community is on the screen.
- Why it matters: the newcomer wonders which community, and whether something is broken.
- Proposed fix: "When you join a community, its spaces show here." `Pages/Profile/Partials/SpaceLevelsForm.tsx:172`.
- Evidence: picture `notifications/settings--new--new-member--1920--light.png`.
- Sources: N31.
- Checked: by one reviewer, in pictures and code.
- Confidence: high.

#### R-L5 Direct messages do not say who can read them

- Screen: Direct messages, no conversation (`/community/atlas-empty/messages`), member, 360, light.
- What is wrong: "Conversations", "New message", "No conversations yet." Nothing says that only the people in a conversation can read it (even the owner cannot open the conversation of others: `messages/conversation--forbidden--owner`).
- Why it matters: "can the moderators read my messages?" is a common first question.
- Proposed fix: an empty state: "Write privately to one member or a small group. Only the people in the conversation can read it." `Components/Messages/ConversationList.tsx:19`.
- Evidence: picture `messages/index--empty--member--360--light.png`.
- Sources: N32.
- Checked: by one reviewer, in pictures and code.
- Confidence: high.

#### R-L6 No direct way from the notifications to their settings

- Screen: Notification bell panel open, Notifications page; member.
- What is wrong: the panel and the page have no "Settings" link in their header; the way is the "..." menu of a line or the user menu. The page has no breadcrumb.
- Why it matters: the moment a member is annoyed by a notification is the moment they look for the settings.
- Proposed fix: a "Settings" link next to the heading "Notifications" in both variants of `Components/Notifications/NotificationsPanel.tsx:609`.
- Evidence: pictures `notifications/bell-panel--open--member--1920--light.png`, `notifications/page--full--member--1920--light.png`.
- Sources: M27.
- Checked: by one reviewer, in pictures and code.
- Confidence: high.

#### R-L7 The first reaction takes two clicks

- Screen: Post, Chat space; member; 1920.
- What is wrong: with no reaction yet, the member opens the picker, then picks; the quick reactions are inside the picker.
- Why it matters: a "+1" is the most frequent action in a community.
- Proposed fix: show the quick reactions on hover or focus of the item at 1920 px, next to the picker button. `Components/Reactions/ReactionBar.tsx`.
- Evidence: picture `discussion/post--emoji-picker--member--1920--light.png`.
- Sources: M29.
- Checked: by one reviewer, in pictures only.
- Confidence: medium.

#### R-L8 The default notification level of a space can be set only after it exists

- Screen: New space, type discussion (`/community/atlas-garden/settings`), owner.
- What is wrong: "What members get by default" shows only when the space is edited, not when it is created.
- Why it matters: an announcement space needs "Everything" from the first post; the owner must create, then edit.
- Proposed fix: show the field on create too, with the built-in default selected. `Components/Spaces/SpaceDialog.tsx:462-480`.
- Evidence: `SpaceDialog.tsx:462-480`; picture `settings/space-form-discussion--new--owner--1920--light.png`.
- Sources: M25.
- Checked: by one reviewer, in pictures and code.
- Confidence: high.

#### R-L9 "Remove member" is an icon only, while the next list uses words

- Screen: Community settings, every section (Members and Access groups), owner, 1920.
- What is wrong: the member rows end with an icon-only button (person with minus). The access group rows use "Members", "Space groups", "Edit", "Delete" in words, and the access group dialog uses "Remove".
- Why it matters: the destructive action is the least clear one on the screen.
- Proposed fix: "Remove" with its icon, as in the access group dialog. `Components/Communities/CommunityMembersManager.tsx`.
- Evidence: the Members section of `settings/community--full--owner--1920--light.png`.
- Sources: M28.
- Checked: by one reviewer, in pictures only.
- Confidence: high.

#### R-L10 Reordering spaces is one step per click

- Screen: Community settings, every section (Spaces), owner.
- What is wrong: "Move up" and "Move down" move a space or a group by one place; moving a space to another group is in the edit dialog.
- Why it matters: moving the tenth space to the top is nine clicks and nine requests.
- Proposed fix: add "Move to..." (position and group) in the row; keep the arrows for the keyboard. `Components/Spaces/SpaceTreeEditor.tsx:22,46-62`.
- Evidence: `SpaceTreeEditor.tsx:22,46-62`; the Spaces section of `settings/community--full--owner--1920--light.png`.
- Sources: M30.
- Checked: by one reviewer, in pictures and code.
- Confidence: high.

#### R-L11 The "Delete a space group" dialog always says that the group must be empty

- Screen: Delete a space group, dialog (`/community/atlas-garden/settings`), owner.
- What is wrong: the text is always "Only an empty space group can be deleted. Move or delete its spaces first.", also for an empty group, and the "Delete" button shows also for a group with spaces (the server then refuses).
- Why it matters: the owner does not know whether the click will work.
- Proposed fix: empty group: "The group "<name>" is deleted. This cannot be undone." Group with spaces: "Move or delete its N spaces first." and one button "Close". `Components/Spaces/SpaceTreeEditor.tsx:249-262`.
- Evidence: `SpaceTreeEditor.tsx:249-262`.
- Sources: W30.
- Checked: by one reviewer, in the code only.
- Confidence: high.

#### R-L12 Every confirmation dialog has an empty band

- Screen: Delete a post, a comment, a message, a resource, a space, dialog; 1920 and 360; both themes.
- What is wrong: `Dialog` always renders the body padding, so a dialog with only a title and a description shows an empty 32 px strip.
- Why it matters: it looks unfinished on the dialogs that ask for the most care.
- Proposed fix: render the body only when `children` is set. `Components/ui/Dialog.tsx:79`.
- Evidence: picture `discussion/post--delete-dialog--member--1920--light.png`.
- Sources: D27.
- Checked: by one reviewer, in pictures and code.
- Confidence: high.

#### R-L13 Success messages follow two patterns

- Screen: Account settings, Dashboard, Community settings.
- What is wrong: most toasts say "<Thing> saved". Older ones say "Success" plus "Profile updated successfully.", "Password updated successfully.", "Logged you out of other browser sessions.", "Community created successfully", "Community deleted successfully".
- Why it matters: "Success" says nothing; "successfully" is filler.
- Proposed fix: "Profile saved", "Password changed", "You are signed out of the other browsers", "<Name> created", "<Name> deleted", no "Success" title. `UpdateProfileInformationForm.tsx:188`, `UpdatePasswordForm.tsx:40`, `LogoutOtherBrowserSessionsForm.tsx:44`, `CreateCommunityDialog.tsx:66`, `DeleteCommunityForm.tsx:47`.
- Evidence: the lines above.
- Sources: W14.
- Checked: by one reviewer, in the code only.
- Confidence: high.

#### R-L14 Title Case and sentence case mix on the same pages

- Screen: Account settings, Dashboard, Community home, Community settings, Sign-up page, footer, browser tabs.
- What is wrong: Title Case in "Profile Information", "Update Password", "Current Password", "Browser Sessions", "Log Out Other Browser Sessions", "Social Links", "Delete Account", "Delete Community", "My Communities", "Recent Resources", "Terms of Service", "Privacy Policy", and the tabs "Confirm Password", "Forgot Password", "Reset Password", "Email Verification", "Contact Us". The rest uses sentence case ("Profile picture", "Two-factor authentication").
- Why it matters: it shows two products glued together and makes the older parts look unfinished.
- Proposed fix: sentence case everywhere, with a lint rule or a test on the `title` props. Files: `UpdateProfileInformationForm.tsx:200,360`, `UpdatePasswordForm.tsx:59,72,90,109`, `LogoutOtherBrowserSessionsForm.tsx:57,105`, `DeleteUserForm.tsx:50,59`, `DeleteCommunityForm.tsx:79,86`, `Dashboard.tsx:99`, `Communities/Show.tsx:84`, `Register.tsx:163,167`, `OpenIdConnectButton.tsx:32,36`, `Contact.tsx:12`.
- Evidence: pictures `account/profile--full--member--1920--light.png`, `community/home--full--member--1920--light.png`.
- Sources: W15.
- Checked: by one reviewer, in pictures and code.
- Confidence: high.

#### R-L15 The word "notification" also names the toasts, and the toast region is an empty landmark "Notifications" on every page

- Screen: every page; all roles.
- What is wrong: `Toaster.tsx:81` renders `<section aria-label="Notifications">`: an empty region in the landmark list of every page, with the name of the bell, the bell panel and the notifications page. Each toast close button is "Dismiss notification"; the notification pop-up has "Close the notification".
- Why it matters: a screen reader user who goes to "Notifications" by landmark lands in an empty region and hears one word for two things.
- Proposed fix: name the region "Status messages" (or drop the `section` and keep a plain live list); toast button "Close the message"; "Close" in both places. `Components/ui/Toaster.tsx:64,81`, `Components/Notifications/NotificationPopup.tsx:103`.
- Evidence: `a11y-out/tree/*.yml`, first line `region "Notifications"`.
- Sources: W25, A19. Two reviewers found it independently.
- Checked: by the accessibility reviewer in the browser.
- WCAG: 2.4.6 Headings and Labels, AA.
- Confidence: high.

#### R-L16 Internal words in the screens

- Screen: Quiz editor, Add a resource, dialog, Resource, Edit an access group, dialog, the composers, the error page.
- What is wrong: "The server grades their answers"; "capture", "Capture again", "Capture failed", "Capturing the page…", "captured"; "Entitlement keys, one per line", "A member who holds one of these keys at the sign-in provider is in this group"; the Markdown help line; the server text "The follow could not be saved"; error titles "403: Forbidden", "500: Server Error".
- Why it matters: a member does not know what a server, a capture, an entitlement key or Markdown is.
- Proposed fix: "Knowii checks the answers"; "Save a copy of the page" / "Save the copy again" / "Copy not saved" / "Saving a copy of the page…"; "Product codes from <provider name>, one per line" with an example; error titles without the number. The word for "capture" is a choice of the owner (decision 10). `QuizEditor.tsx:153`, `CaptureStatus.tsx:24-36`, `ResourceDetails.tsx:109,188,195`, `CapturedArticle.tsx:45`, `AccessGroupsManager.tsx:143,163`, `Error.tsx:18-21`.
- Evidence: the lines above.
- Sources: W26.
- Checked: by one reviewer, in the code only.
- Confidence: medium.

#### R-L17 Client validation messages: "arobases", hidden allowed characters, two voices

- Screen: New community dialog with errors, Add a resource, dialog with errors, Profile information, user name that is too short.
- What is wrong: "Please use only letters, numbers, spaces, hyphens, or arobases" ("arobase" is French for "@"); the rule also allows the apostrophe, and for resources the comma. Also "Please choose a name for your new community (between 3 and 64 characters)" next to "Use between 3 and 64 characters"; "Please select the visibility level for your community"; "Invalid username"; "The URL is too long" next to "The address is too long".
- Why it matters: a word the reader cannot know, and a message that hides allowed characters.
- Proposed fix: "Use only letters, digits, spaces, hyphens (-), apostrophes (') and the at sign (@)." (with the comma for resources); "Enter a name of 3 to 64 characters." everywhere; "Choose who can see the community."; "The address is too long (maximum N characters)."; no "Please". `community.schema.ts:12,54`, `new-community-resource.schema.ts:24`, `space.schema.ts:59,88`, `course-material.schema.ts:47`, `UpdateProfileInformationForm.tsx:119`, `constants.ts:130,202`.
- Evidence: the lines above.
- Sources: W28.
- Checked: by one reviewer, in the code only.
- Confidence: high.

#### R-L18 The same text is written by hand in several places, in different words

- Screen: spaces without content, comments, chat, account.
- What is wrong: the empty states of spaces exist twice (`Pages/Spaces/Show.tsx:56-60` and the lists), with small differences. "Deleted member" is written 10 times, "Not saved. Retry." 6 times, the copy-link toasts twice, the bot-check message twice, the confirm-password text twice in two voices, and the "Everything" rule four ways (`SpaceDialog.tsx:481`, `SpaceLevelsForm.tsx:156`, `app/Actions/Spaces/UpdateSpace.php:47`).
- Why it matters: the copies drift; a fix in one place misses the others.
- Proposed fix: a shared texts module (`libs/common/src/lib/texts.ts`) used by every component.
- Evidence: `PostList.tsx:166-167`, `ResourceList.tsx:117-118`, `PageList.tsx:202-211`, `ChatRoom.tsx:359-360`, `ConfirmPassword.tsx:35`, `ConfirmsPassword.tsx:26`.
- Sources: W29.
- Checked: by one reviewer, in the code only.
- Confidence: high.

#### R-L19 Small wording defects: comments, notification levels, copy link, dots

- Screen: Post with comments (moderator), notification level menu, Resource, Dashboard.
- What is wrong: the accessible name "Delete the deleted comment" (`CommentSection.tsx:318`); "Muted: You will get nothing from this space, also when someone mentions you." ("also when" means "even when"); "Everything: You get a line for every new post here" ("a line" is internal, and in a course it is a lesson); "Copy link" (`CommentSection.tsx:306`) against "Copy the link" (`ResourceDetails.tsx:182`); "Search communities..." with three dots (`Dashboard.tsx:106`).
- Why it matters: each is small; together they cost a second read.
- Proposed fix: "Delete this empty comment"; "Muted: You get nothing from this space, even when someone mentions you."; "Everything: You get a notification for each new post or lesson."; one label "Copy the link"; "Search communities…". `notification-follow.schema.ts:61`.
- Evidence: the lines above.
- Sources: W31.
- Checked: by one reviewer, in the code only.
- Confidence: high.

#### R-L20 British and American spelling mix

- Screen: footer of every page, Community settings.
- What is wrong: British "Licence (AGPL-3.0)" (`libs/common/src/lib/footer-links.ts:41`) and "Invitation to ... cancelled" (`CommunityMembersManager.tsx:218`); American "Space groups organize the spaces" (`SpaceGroupDialog.tsx:58`).
- Why it matters: small, but visible in the footer of each page.
- Proposed fix: one spelling, written in the style guide (decision 11).
- Evidence: the lines above; the footer in `dashboard/user-menu--open--member--1920--light.png`.
- Sources: W32.
- Checked: by one reviewer, in pictures and code.
- Confidence: medium.

#### R-L21 The API tokens page names a browser extension that a member cannot find

- Screen: API tokens, no token (`/user/api-tokens`), member (the atlas build answered 500; text read in the code).
- What is wrong: "Scripts, tools and the browser extension use a token instead of your password." and the placeholder "For example: browser extension". The extension is planned (#1538), and the product has no link to it.
- Why it matters: the text points to something the product does not offer.
- Proposed fix: "Scripts and other tools use a token instead of your password."; placeholder "For example: my backup script"; name the extension again when it ships. `Pages/API/Index.tsx:152,166`.
- Evidence: `Pages/API/Index.tsx:152,166`.
- Sources: W33.
- Checked: by one reviewer, in the code only.
- Confidence: low. Not verified whether an extension exists outside the repository.

#### R-L22 Code blocks and tables that scroll sideways cannot get the keyboard focus

- Screen: Discussion space and posts with code (360), legal pages with tables (360); member and visitor.
- What is wrong: `pre` blocks and wide tables scroll sideways at 360 px and have no `tabindex`. Current Chromium and Firefox make such scrollers focusable; Safari does not.
- Why it matters: Safari keyboard users cannot scroll them.
- Proposed fix: in `Components/ui/MarkdownContent.tsx`, `tabindex="0"`, `role="region"` and a name ("Code", "Table") on `pre` and wide tables when they overflow.
- Evidence: axe-core rule `scrollable-region-focusable` on `visitor/legal/360/*` and `member/discussion/360/*`.
- Sources: A14.
- Checked: by the accessibility reviewer in the browser (Chromium only).
- WCAG: 2.1.1 Keyboard, A.
- Confidence: medium.

#### R-L23 The chat log puts `role="log"` on the list, so its items are no longer list items

- Screen: Chat space, Chat with a thread panel open; member, owner; all widths and themes.
- What is wrong: `<ol role="log">` replaces the list role, and its `<li>` children are invalid (axe-core `listitem`: 13 nodes in the room, 5 in the thread).
- Why it matters: screen readers can drop the item count.
- Proposed fix: `role="log"` on a wrapping `div`, the `ol` inside. `Components/Chat/ChatRoom.tsx:365`, `Components/Chat/ThreadPanel.tsx:76`.
- Evidence: axe-core `member/chat/*`, `member/chat-thread/*`.
- Sources: A16.
- Checked: by the accessibility reviewer in the browser.
- WCAG: 1.3.1 Info and Relationships, A.
- Confidence: high.

#### R-L24 The breadcrumb of a resource page is 6 px too wide at 320 px

- Screen: Resource (`/community/atlas-garden/space/reading-list/resource/balcony-vegetables-guide`), member, 320.
- What is wrong: the last breadcrumb item has no maximum width below `sm` and pushes the page to 326 px.
- Why it matters: a small horizontal scroll at the size of a 400 % zoom.
- Proposed fix: `min-w-0` on the last `li` and `max-w-full` on its label. `Components/ui/Breadcrumb.tsx:51`.
- Evidence: `a11y-reflow.mjs reflow member`: `member/resource {"scrollW":326}`.
- Sources: A17.
- Checked: by the accessibility reviewer in the browser.
- WCAG: 1.4.10 Reflow, AA.
- Confidence: high.

#### R-L25 The emoji search field has almost no visible border

- Screen: Post, emoji picker open, Chat, emoji picker open; member; all widths; both themes.
- What is wrong: the field uses `border-border bg-background`: a 10 % border (about 1.3:1) on the raised panel. Other fields use `border-border-strong` (3.2:1 or more).
- Why it matters: the edge of the field is hard to see; the placeholder and the autofocus soften it.
- Proposed fix: `formControlClasses` from `form-field.ts` in `Components/Reactions/EmojiPicker.tsx:252` (or `TextInput size="sm"`, R-S72).
- Evidence: `EmojiPicker.tsx:252`; tokens `styles.css:54,110`. The ratio is computed from the tokens.
- Sources: A18.
- Checked: by one reviewer, in the code only.
- WCAG: 1.4.11 Non-text Contrast, AA.
- Confidence: medium.

#### R-L26 Cards on the same screen with and without the header bar and icon

- Screen: Community home and About the community, member, 1920, both themes.
- What is wrong: on the community home, "Catch up" has a plain title and a subtitle; "Recent Resources" and "Members" have an icon and the magenta bar. On About, "About", "Team" and "What moderators can do" have an icon; "What you can do" and "Leave the community" do not.
- Why it matters: a small inconsistency on the first screen of a community, every day.
- Proposed fix: `CatchUpCard` uses `Card` + `CardHeader` (`CatchUpCard.tsx:138-146`); in one page, every `CardHeader` has an icon or none has one.
- Evidence: pictures `community/home--full--member--360--light.png`, `community/about--full--member--1920--dark.png`.
- Sources: D13.
- Checked: by one reviewer, in pictures only.
- Confidence: high.

#### R-L27 Four looks for the current item of a menu

- Screen: Main menu open, Navigation drawer of a community, User menu open; member; 360; dark.
- What is wrong: the main menu marks the current entry with a magenta left bar, a pink icon and large text (`MainMenu.tsx:45`); the space navigation with `bg-overlay/15` and bold; the user menu with `bg-overlay/10` and bold (`AppLayout.tsx:164`); the "Messages" link with `bg-overlay/15` (`CommunityShell.tsx:155`).
- Why it matters: small, but met on every screen.
- Proposed fix: one `NavItem` with one current style, used by the three menus.
- Evidence: pictures `dashboard/main-menu--open--member--360--dark.png`, `community/navigation--drawer--member--360--dark.png`, `dashboard/user-menu--open--member--360--dark.png`.
- Sources: D23.
- Checked: by one reviewer, in pictures and code.
- Confidence: high.

#### R-L28 Member text comes in two sizes

- Screen: Post with comments, Chat space, Conversation of two.
- What is wrong: a comment and a post card body are 14 px (`CommentSection.tsx:277`, `PostCard.tsx:39`); a chat message, a direct message and the post body are 16 px with prose line height (`ChatMessageItem.tsx:80`, `Posts/Show.tsx:163`).
- Why it matters: the comment, the main reading surface after the post, is the small one.
- Proposed fix: all member text at the body size; the list excerpt may stay smaller.
- Evidence: pictures `discussion/post--full--member--1920--dark.png`, `chat/space--full--member--1920--dark.png`.
- Sources: D29.
- Checked: by one reviewer, in pictures and code.
- Confidence: high.

#### R-L29 Labels and pills are built six ways

- Screen: space header, Dashboard, Notifications page, Quiz after an attempt, Resource, Community settings, About the community.
- What is wrong: space chips `rounded-full border` (`Spaces/Show.tsx:139-144`); the visibility badge `rounded-lg bg-overlay/10 text-xs font-medium` (`CommunityBox.tsx:53`); "NEW" `rounded border-primary uppercase` (`NotificationLine.tsx:95`); "Your answer" (`LessonQuiz.tsx:283`); resource chips (`ResourceDetails.tsx:23`); role badges (`About.tsx:164`, `CommunityMembersManager.tsx:314`). The community icon color codes the visibility (green, pink, yellow) without a legend (`community.schema.ts:29-31`).
- Why it matters: the same kind of fact (a type, a state, a role) has a different shape each time.
- Proposed fix: a `Badge` building block, and one fixed color for the community icon.
- Evidence: pictures `dashboard/home--full--member--1920--dark.png`, `notifications/page--full--member--1920--dark.png`, `community/about--full--member--1920--dark.png`, `course/quiz--after--member--1920--dark.png`.
- Sources: D30.
- Checked: by one reviewer, in pictures and code.
- Confidence: high.

#### R-L30 Light theme: a raised surface has the color of a flat one

- Screen: menus, interactive cards, "Catch up" card; light.
- What is wrong: in light, `--color-surface-elevated` equals `--color-surface` (#ffffff). The hover of an interactive `Card` changes no fill in light, and the "Catch up" card looks like any card.
- Why it matters: the light theme loses a level that the dark theme has.
- Proposed fix: a light value one step off white for `surface-elevated`, or the shadow alone on purpose, said in the token comment. A color value: the owner decides (decision 9). `styles.css:97-98`, `Components/ui/Card.tsx:20`.
- Evidence: `styles.css:97-98`; picture `community/home--full--member--360--light.png`.
- Sources: D32.
- Checked: by one reviewer, in code and pictures.
- Confidence: medium.

### Not kept

1. M3 ("Private" community does not make anything private): already decided. The owner decided on 2026-11-16 (#1500, pull request #1512) that the control stays, with help texts that say what it does today. That a signed-in non-member can read a public chat space is the written rule of ADR 0044. The new part (the "New community" dialog) is R-S12.
2. W17 ("Public" and "Private" promise a privacy that the setting does not give): already decided, for the same reason (#1500, #1512). The part about the "New community" dialog is R-S12.
3. W6 (a personal community is named "<Name>'s Space"): covered by the open issue #1549. Its point 5 asks the owner to choose between today's name and "My library" in the navigation.
4. N15 (the first screen after "Join" shows less than the public page): planned features: the welcome and the first page for new members are #1398, the posts on the community home are #1399. The missing visible community name is part of R-S15.
5. N19 (the public page of a community does not say what members get): planned feature #1421 (locked spaces shown to people without access, with a lock screen, also for visitors of a public community).
6. N34 (the Welcome page names spaces without links; a way to link a space from the editor): planned feature #1439 (links to posts, spaces, events and lessons with # in the editor).

Reasons counted: already decided 2; planned feature or open issue 4; already fixed 0; defect of the atlas 0 (the missing "Catch up" card in the dark pictures was not a reviewer finding; it is under "Limits of this review").

## Decisions for the owner

1. The privacy text of the account settings (R-M6). Options: (a) the newcomer's draft: "Your name, user name and picture show next to what you write. In a public space, visitors see them too. Your email address is never shown. Bio, location and phone: you choose below."; (b) the editor's draft: "Your name, username and profile picture are visible to the members of your communities. For the bio, location and phone, you choose below. Your email address stays private." Recommendation: (a), because the name and the username show to visitors in public spaces and (b) leaves that out. Before it ships, check in the member resource of the API that the email address never goes to other members (the editor asks for this check). Spell "username" the way the field label does.
2. The accent pink as text in the dark theme (R-M2). Options: (a) a lighter pink for text in the dark theme only, fills and buttons keep #e5007d; (b) links and mentions in the text color with an underline, pink only for icons and fills. Recommendation: (a): it keeps the brand and it is one token. Show before and after pictures of a post, the chat and the home page.
3. Deleting a comment or a post that has replies of others (R-M5). Options: (a) the author's delete leaves a marker and keeps the replies, as chat messages and account deletion do; (b) the same, and moderators can still delete the whole thread; (c) keep the cascade and say it more clearly in the dialog. Recommendation: (b). It changes point 33.2 of ADR 0043, so it needs an ADR update. Decide before launch.
4. One help line under the box of the account deletion form (R-S50). Options: (a) add "Your resources, pages and lessons stay, under the name "Deleted member". A post or a comment that has replies stays as an empty marker."; (b) keep the approved text of #1344 as it is. Recommendation: (a): the box text stays as approved, and the new line removes the wrong reading.
5. The home page while sign-up is open (R-S6). Options: (a) show "Coming soon" and the waitlist only when registration is closed, and a short text on what a person can do when it is open; (b) close registration and hide "Register" until launch. Recommendation: (a) if people can sign up today, else (b). Only the owner knows which state the product is in.
6. The "New community" dialog (R-S12). Two choices: the default visibility ("Public" today, "Private", or no default), and a plain line before the link to the data processing agreement (legal words). Recommendation: "Private" as the default, because "Public" only marks a community for a list that does not exist yet; and one line that the owner writes, for example "You will hold the data of your members; this agreement says how."
7. Which icon a member sees next to a space (R-S30). Options: (a) no icon for "Members only" when the person is a member, the lock kept for #1421; (b) icons for managers only; (c) a globe for "Public" spaces ("visitors can read this"), no icon for the rest. Recommendation: (a) with (c): the lock keeps one meaning, and the globe tells a member where visitors read along (see R-S20).
8. The shell of the signed-in pages (R-S42, R-S44): replace the menu button (one link) by a link to the dashboard, add "Help" to the user menu, move "API tokens" to the account settings, and use the compact footer. Recommendation: accept all four; show before and after pictures of the dashboard and the chat at 360 px.
9. Color values (R-S74, R-L30): a neutral fallback avatar instead of magenta for every member, chevrons in the muted color, and a light value one step off white for `surface-elevated`. Recommendation: accept, with before and after pictures of the notifications page and the community home in both themes.
10. The word for "capture" of a resource (R-L16). Options: (a) "Save a copy of the page"; (b) keep "capture" and explain it once. Recommendation: (a).
11. The spelling (R-L20). Options: American ("License", "canceled") or British ("Licence", "cancelled"). Recommendation: American, as the editor proposes, written in the style guide.

## Missing building blocks

For the component toolkit (#657, slice 4). Lines are those of the commit `382ded2d`. Paths are under `apps/knowii/src` unless they start with `libs/`.

| Proposed name                                | What it is                                                                                                            | Variants seen                                                                     | Built by hand today                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                 | Findings it would fix          |
| -------------------------------------------- | --------------------------------------------------------------------------------------------------------------------- | --------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ------------------------------ |
| `Switch` (with an instant-save setting card) | A setting that saves on change, with label, description, busy state and an optional confirmation                      | with and without a description; with a confirmation for changes that widen access | `Components/Communities/DirectMessagesSetting.tsx:39`, `Components/Communities/IndexingSetting.tsx:42`, `Components/Communities/JoinPolicySetting.tsx:19`, `Pages/Profile/Partials/OnlineStatusForm.tsx:41`, `Pages/Profile/Partials/PopupsForm.tsx:44` (`role="switch"`)                                                                                                                                                                                                                                                                                           | R-M9, R-S58                    |
| `SaveStatus`                                 | The "Saving / Saved / Not saved" state next to a control, with a retry                                                | inline text, sticky bar, filled box, screen-reader only                           | `Components/Notifications/SpaceLevelControl.tsx:105`, `Components/Notifications/ThreadFollowControl.tsx:135`, `Components/TimeZone/TimeZoneAsk.tsx:115`, `Pages/Profile/Partials/PopupsForm.tsx:51`, `Pages/Profile/Partials/SpaceLevelsForm.tsx:202,221`, `Pages/Profile/Partials/TimeZoneForm.tsx:69`, `Pages/Profile/Notifications.tsx:57`, `Components/Notifications/CatchUpCard.tsx:215`, `Components/Notifications/NotificationsPanel.tsx:626`                                                                                                                | R-M9, R-S58, R-S61             |
| `StatusMessage`                              | A one-line success, error or neutral message in a form, with the right text token                                     | success, danger, neutral                                                          | `Pages/Auth/Login.tsx:54`, `Pages/Auth/ForgotPassword.tsx:41`, `Pages/Auth/VerifyEmail.tsx:35`, `Pages/Profile/Partials/UpdateProfileInformationForm.tsx:279`, `Pages/Profile/Partials/ConnectedAccountsForm.tsx:58,63`                                                                                                                                                                                                                                                                                                                                             | R-M10, R-S5                    |
| `FormField`                                  | Label, control, hint and error that set `id`, `aria-invalid` and `aria-describedby`                                   | with a hint, with an error, with both                                             | `Pages/Auth/Register.tsx:75-140`, `Pages/Auth/Login.tsx:99-129`, `Pages/Auth/ForgotPassword.tsx:61`, `Pages/Auth/ResetPassword.tsx:62,81`, `Pages/Auth/ConfirmPassword.tsx:52`, `Pages/Profile/Partials/UpdatePasswordForm.tsx:85,104`                                                                                                                                                                                                                                                                                                                              | R-S4, R-S3                     |
| `FormActions`                                | The save row of a form: right aligned, "Save" primary, optional "Reset", disabled until a change                      | footer strip, inside the card, in a well, in a composer                           | `Components/FormSection.tsx:27`, `Components/Communities/UpdateCommunityForm.tsx:154`, `Components/Communities/ModeratorPermissionsForm.tsx:83`, `Components/Resources/ResourceChangesForm.tsx:70`, `Components/Comments/CommentForm.tsx:98`, `Components/Chat/ChatComposer.tsx:136`                                                                                                                                                                                                                                                                                | R-S57                          |
| `ConfirmDialog`                              | A confirmation with the object name, the list of what goes, the undo sentence, "Cancel" focused and a `danger` button | plain; with a typed name; with a password                                         | `Components/Communities/AccessGroupsManager.tsx:675`, `Components/Communities/CommunityMembersManager.tsx:421`, `Components/Communities/LeaveCommunityButton.tsx:47`, `Components/Spaces/SpaceTreeEditor.tsx:256`, `Components/Comments/CommentSection.tsx:450`, `Components/SpacePages/CourseMaterials.tsx:507`, `Components/SpacePages/PageList.tsx:303`, `Components/Chat/ChatRoom.tsx:440`, `Pages/Resources/Show.tsx:166`, `Pages/Posts/Show.tsx:199`, `Pages/Communities/Partials/DeleteCommunityForm.tsx:94`, `Pages/Profile/Partials/DeleteUserForm.tsx:67` | R-S51, R-S52, R-L11, R-L12     |
| Unsaved-changes guard of `Dialog`            | An option of `Dialog` that asks before it closes a changed form                                                       | Escape, click outside, "Cancel"                                                   | `Components/ui/Dialog.tsx:18-20,72`; used by `Components/Posts/PostComposer.tsx:54-63`, `Components/SpacePages/PageEditor.tsx:101-110` (also `SpaceDialog.tsx`, `CreateResourceDialog.tsx`, `QuizEditor.tsx`)                                                                                                                                                                                                                                                                                                                                                       | R-M3                           |
| `MemberIdentity` and `MetaLine`              | Avatar, name linked to the profile, optional handle, then a meta line with one separator                              | with and without avatar, link, handle; separators "·", two spaces, comma          | `Components/Posts/PostCard.tsx:34`, `Pages/Posts/Show.tsx:124`, `Components/Comments/CommentSection.tsx:261`, `Components/Chat/ChatMessageItem.tsx:48-64`, `Components/Resources/ResourceDetails.tsx:150`, `Pages/Communities/Public.tsx:77`, `Components/Notifications/NotificationLine.tsx:112,135`, `Pages/Communities/About.tsx:154`, `Components/Communities/CommunityMembersManager.tsx:294`, `Components/Messages/ConversationList.tsx:45`, `Components/Communities/AccessGroupsManager.tsx:324`                                                             | R-S27, R-S48                   |
| `MemberPicker` (moved to `Components/ui`)    | A combobox that finds a member by name                                                                                | single, several                                                                   | `Components/Messages/MemberPicker.tsx:23-27` (the model); needed in `Components/Communities/AccessGroupsManager.tsx:303`, `Pages/Communities/Partials/TransferOwnershipForm.tsx:82`                                                                                                                                                                                                                                                                                                                                                                                 | R-S56                          |
| `ContentActions`                             | The actions of a post, comment or message: main ones visible, the rest in a "..." menu                                | Reply, Copy link, Edit, Delete; always shown or on hover                          | `Components/Comments/CommentSection.tsx:287-330`, `Components/Chat/ChatMessageItem.tsx:97-108`, `Components/Messages/ConversationView.tsx`; `Components/Notifications/NotificationLine.tsx:197` already uses `DropdownMenu`                                                                                                                                                                                                                                                                                                                                         | R-S26, R-S73                   |
| `EmptyState` (inline size, action in use)    | The existing block, with a small inline size and the primary action inside                                            | boxed, inline; with and without action                                            | `Pages/Communities/Show.tsx:102`, `Components/Spaces/SpaceNavigation.tsx:48`, `Pages/Communities/Public.tsx:100`, `Components/Comments/CommentSection.tsx:356`, `Components/Messages/ConversationList.tsx:19`, `Pages/Profile/Partials/SpaceLevelsForm.tsx:172`, `Components/Notifications/NotificationsPanel.tsx:790`, `Pages/Dashboard.tsx:124`                                                                                                                                                                                                                   | R-S11, R-S67, R-L1, R-L4, R-L5 |
| `NoAccessPanel`                              | What a person sees on content they cannot read, with the next step                                                    | join; ask for an invitation; sign in or sign up                                   | `Pages/Error.tsx:24-29`, `Components/Communities/JoinCommunity.tsx:26`, `Components/Reader/Reader.tsx:31` (`SignInPrompt`)                                                                                                                                                                                                                                                                                                                                                                                                                                          | R-M1, R-S1, R-S8, R-S9         |
| `AudienceNotice`                             | One line in a composer that says who can read the text                                                                | members of the community; everybody on the web                                    | `Components/Posts/PostComposer.tsx:97`; needed in `Components/Comments/CommentForm.tsx`, `Components/Chat/ChatComposer.tsx`; today only a chip in `Pages/Spaces/Show.tsx:139`                                                                                                                                                                                                                                                                                                                                                                                       | R-S20                          |
| Title of `AuthenticationCard`                | A visible h1 and an optional line under it on every sign-in page                                                      | with and without a community name                                                 | `Pages/Auth/Login.tsx:52`, `Pages/Auth/Register.tsx:69`, `Pages/Auth/VerifyEmail.tsx:28` (also `ForgotPassword.tsx`)                                                                                                                                                                                                                                                                                                                                                                                                                                                | R-S1, R-S15                    |
| Description slot of `SegmentedControl`       | The text of the selected option under the control                                                                     | none (dialog); own text (settings)                                                | `Components/Communities/CreateCommunityDialog.tsx:166`, `Components/Communities/UpdateCommunityForm.tsx:146`                                                                                                                                                                                                                                                                                                                                                                                                                                                        | R-S12                          |
| `Skeleton`                                   | Placeholder lines while a list loads after the page                                                                   | lines, with avatar                                                                | `Components/Notifications/NotificationsPanel.tsx:700-703,770-773`; needed in `Components/Notifications/CatchUpCard.tsx:136`, `Components/Chat/ThreadPanel.tsx:60`, `CommunityMembersManager`, `AccessGroupsManager`                                                                                                                                                                                                                                                                                                                                                 | R-S35                          |
| `Badge`                                      | A small label for a type, a state or a role                                                                           | outline, subtle, accent, success, danger; xs, sm                                  | `Pages/Spaces/Show.tsx:139-144`, `Components/Communities/CommunityBox.tsx:53`, `Components/Notifications/NotificationLine.tsx:95`, `Components/SpacePages/LessonQuiz.tsx:283`, `Components/Resources/ResourceDetails.tsx:23`, `Pages/Communities/About.tsx:164`, `Components/Communities/CommunityMembersManager.tsx:314`, `Components/Posts/PostCard.tsx:23`                                                                                                                                                                                                       | R-L29, R-M2                    |
| `CountBadge` and `NewDot`                    | An unread count and a "new content" dot with a screen-reader text                                                     | inline pill, pill on an icon, dot                                                 | `Components/Spaces/SpaceNavigation.tsx:100,106`, `Components/Spaces/CommunityShell.tsx:161`, `Components/Messages/ConversationList.tsx:60`, `Components/Notifications/NotificationBell.tsx:140`                                                                                                                                                                                                                                                                                                                                                                     | R-S30, R-S36                   |
| `Callout` (with a page banner placement)     | A boxed note with tone, icon, title and action                                                                        | neutral, info, warning, danger, success; in a card or under the header            | `Components/Chat/ChatRoom.tsx:403`, `Components/SpacePages/QuizEditor.tsx:209`, `Components/Spaces/SpaceDialog.tsx:360,425`, `Components/Waitlist/WaitlistForm.tsx:84`, `NewsletterForm.tsx:84`, `Components/Resources/ResourceDetails.tsx:100`, `Pages/Legal/Show.tsx:46`, `Components/Notifications/NotificationsPanel.tsx:656`, `Layouts/AppLayout.tsx:218` (`TimeZoneAsk`)                                                                                                                                                                                      | R-M10, R-S45                   |
| `SectionHeader`                              | A section title with optional count, description and actions                                                          | four sizes today                                                                  | `Components/Spaces/SpaceTreeEditor.tsx:87`, `Pages/Messages/Index.tsx:94`, `Components/Comments/CommentSection.tsx:336`, `Components/Notifications/NotificationsPanel.tsx:814`, `Components/Resources/ResourceList.tsx:102`, `Components/SectionTitle.tsx`                                                                                                                                                                                                                                                                                                          | R-S69, R-L26                   |
| `Disclosure`                                 | A toggle that shows or hides a part                                                                                   | inline, boxed (`Collapsible`)                                                     | `Components/Notifications/SpaceLevelControl.tsx:68`, `Components/Notifications/NotificationsPanel.tsx:813`, `Components/Comments/CommentSection.tsx:375-388`; `Collapsible` once in `Pages/Profile/Partials/UpdateProfileInformationForm.tsx:360`                                                                                                                                                                                                                                                                                                                   | R-S72                          |
| `NavItem`                                    | A menu or navigation entry with one "current" style                                                                   | main menu, space navigation, user menu, "Messages"                                | `Components/MainMenu.tsx:45`, `Components/Spaces/SpaceNavigation.tsx:84`, `Components/Spaces/CommunityShell.tsx:148`, `Layouts/AppLayout.tsx:160-210`                                                                                                                                                                                                                                                                                                                                                                                                               | R-L27                          |
| `NotificationLevelPicker`                    | The choice of a notification level for a space                                                                        | radios with sentences, segmented row                                              | `Components/Notifications/SpaceLevelControl.tsx:68`, `Pages/Profile/Partials/SpaceLevelsForm.tsx:202`                                                                                                                                                                                                                                                                                                                                                                                                                                                               | R-S41                          |
| `LoadMore`                                   | The button that loads the next page of a list                                                                         | "Load more posts", "Load more resources", "Load more", "Show more"; md, sm        | `Components/Posts/PostList.tsx:181`, `Components/Resources/ResourceList.tsx:130`, `Components/Communities/CommunityMembersManager.tsx:333`, `Components/Communities/AccessGroupsManager.tsx:341`, `Components/Notifications/NotificationsPanel.tsx:801,853`                                                                                                                                                                                                                                                                                                         | none directly (consistency)    |
| `Tooltip`                                    | A visible hint for an icon-only control                                                                               | on hover and on focus                                                             | only the native `title` in `Components/ui/MarkdownEditor.tsx:565`; icon-only buttons of the space tree, course materials, quiz editor, resource rows                                                                                                                                                                                                                                                                                                                                                                                                                | R-S30, R-L9                    |
| Icon map                                     | One list of icon names in `Components/ui/icons.ts`, one icon set                                                      | solid only                                                                        | `Layouts/AppLayout.tsx:18`, `Components/Spaces/CommunityShell.tsx:6`, `Pages/Dashboard.tsx:10`, `Components/Communities/CommunityMembersManager.tsx:25`, `Components/Communities/CommunityIcon.tsx:17`                                                                                                                                                                                                                                                                                                                                                              | R-S70                          |
| Focus after removal                          | A helper that moves the focus to the next item, the previous one or the list heading when the focused item goes       | comment delete, collapse, message delete, "Done"                                  | `Components/Comments/CommentSection.tsx:340-352`, `Components/Chat/ChatRoom.tsx:440`, `Components/Notifications/NotificationsPanel.tsx:378`                                                                                                                                                                                                                                                                                                                                                                                                                         | R-S22                          |
| Announce                                     | One polite live region for results that show no toast                                                                 | comment posted, post published, message sent in a thread                          | own status regions today, for example `Pages/Communities/Partials/TransferOwnershipForm.tsx:163`, `Components/Notifications/CatchUpCard.tsx:115`; the reviewer also names `PageList.tsx`, `CourseMaterials.tsx`, `SpaceTreeEditor.tsx`                                                                                                                                                                                                                                                                                                                              | R-S23                          |
| Scrollable region                            | A focusable, named wrapper for wide code blocks and tables                                                            | code, table                                                                       | `Components/ui/MarkdownContent.tsx` (renders `pre` and `table`; no line given by the reviewer)                                                                                                                                                                                                                                                                                                                                                                                                                                                                      | R-L22                          |

Not components, but shared pieces of the same work: a texts module `libs/common/src/lib/texts.ts` (R-L18, R-S61), a `lang/en` folder for the server messages (R-S3), one date function next to `RelativeTime` (R-S66). Not needed yet: `Tabs` (the only tab set is Write / Preview inside the editor).

## Proposals for the look of the whole

These are not findings. Each one changes the product as a whole, so the owner decides, with before and after pictures.

1. A first-run path for a new account (newcomer). A small "Getting started" card on the dashboard and on the community home after a join, with three steps that tick themselves (complete your profile, read the "Start here" space, write your first post or message), hidden once done. Reasons: R-S10, R-S11 and N15 are the same gap seen from several screens; the product has the parts (welcome pages, empty states, About page) but nothing ties them together. Cost: not estimated by the reviewer; it needs a card, a stored state of three steps per member and a place in two pages. It overlaps #1398 (onboarding of new members), #1457 (owner checklist) and #213 (product tour).
2. Keep a person in the community they came for (newcomer). Remember the community a person signed up from, land them there after the address check, and show the personal space as secondary. Reasons: R-M1, R-S1, R-S6, R-S10, R-L1. Cost: not estimated by the reviewer; it needs the intended community kept through sign-up and verification, and a change of the redirect after verification.
3. Use the right side of community pages at 1600 px and more (design). A right column with the context of the space (description, notification level, members here, pinned posts, next lesson) would empty the header of each space and give the reading column its full height. Reasons: R-S68 (470 px empty at 1920 px), the crowded space header (R-S31). Cost (reviewer): a new region in `CommunityShell`, a decision of what goes there per space type, and today's layout as the fallback under 1600 px.
4. A larger step between `background` and `surface` in the dark theme (design). Today a card is #3f4957 on #37404c with a 10 % white border, about 1.1:1; cards are found by their fill only and melt at a low screen brightness. Cost (reviewer): one token change and a visual check of every screen in dark; it touches the brand colors.
5. Write the system down where the toolkit lives (design and words). The named text styles of R-S69, the action rules of R-S73, the width rule of R-S68, the one settings layout of R-S54, and a word list and style sheet (one word per row of the glossary in `findings/words.md`, sentence case, no "Please", no "successfully", one error pattern, one spelling, one date format). Reasons: without these rules, Storybook documents today's differences as variants, and the older account screens keep their own voice. Cost: not estimated by the reviewers; one page per rule in the toolkit (#657), plus a lint rule for sentence case and text tokens.

The accessibility reviewer's two proposals are in this report as decision 2 (the accent pink in the dark theme) and R-S16 (the skip link, which does not change the look). The experienced member made no proposal for the look of the whole.

## Issues filed

Filed on 2026-11-17 in knowii-oss/knowii. Labels: MUST and P1 (milestone NOW), SHOULD, or COULD; `a11y` when the issue names a WCAG criterion, else `ux`.

| Issue | Weight | Findings                                        | Owner decision | Title                                                                                     |
| ----- | ------ | ----------------------------------------------- | -------------- | ----------------------------------------------------------------------------------------- |
| #1561 | must   | R-M1                                            |                | A link to a members-only space or a private community ends on a bare 403 page             |
| #1562 | must   | R-M2                                            | yes            | Dark theme: links, mentions and small labels in the accent pink are below 4.5:1           |
| #1563 | must   | R-M3                                            |                | The post composer and the page and lesson editors lose the text on Escape                 |
| #1564 | must   | R-M4                                            |                | A busy button drops the keyboard focus to the page body                                   |
| #1565 | must   | R-M5                                            | yes            | Deleting your own comment deletes the replies of other members                            |
| #1566 | must   | R-M6                                            | yes            | The account settings say that almost all profile data is public, which is false           |
| #1567 | must   | R-M7                                            |                | The emoji picker stays open after the focus leaves it and covers the next controls        |
| #1568 | must   | R-M8                                            |                | Dark theme: muted text on raised surfaces is 4.32:1                                       |
| #1569 | must   | R-M9                                            |                | The same checkbox saves at once in one settings card and needs Save in the next           |
| #1570 | must   | R-M10                                           |                | Some error texts use the fill color danger, about 1.9:1 in the dark theme                 |
| #1571 | should | R-S1, R-S2, R-S5, R-L2, R-L3                    |                | Sign-in and sign-up pages have no title, lose the community, and use two names            |
| #1572 | should | R-S3, R-S62                                     |                | Sign-in, sign-up and password pages show framework messages and long older texts          |
| #1573 | should | R-S4, R-S60                                     |                | Sign-in, sign-up and account form fields lack aria-invalid and the right autocomplete     |
| #1574 | should | R-S6, R-L1                                      | yes            | The home page says Coming soon while sign-up works, and the error page sends people there |
| #1575 | should | R-S10, R-S11, R-S14                             |                | A new member and a new owner get no next step on the dashboard or in an empty community   |
| #1576 | should | R-S12, R-S13                                    | yes            | New community dialog: no help for the visibility, and the owner stays on the dashboard    |
| #1577 | should | R-S51, R-S52, R-L11, R-L12                      |                | Confirmation dialogs say Go ahead!, hide what goes, and show an empty band                |
| #1578 | should | R-S7, R-S8, R-S9, R-S46                         |                | Join and leave texts do not say what happens, and non-members have no way to comment      |
| #1579 | should | R-S30, R-L27                                    | yes            | Space navigation shows a lock on every space and a weak new-content dot                   |
| #1580 | should | R-S42, R-S43, R-S44                             | yes            | The signed-in shell has a one-link menu, no help, mismatched page names and a full footer |
| #1581 | should | R-S18, R-S19, R-S20, R-S21                      |                | Writing fields show Markdown code in a monospace font, with no label or audience          |
| #1582 | should | R-S31, R-S32, R-S34                             |                | Chat on a phone shows two messages and sends on Enter; the thread panel blocks the room   |
| #1583 | should | R-S33, R-S36, R-S37                             |                | A returning member cannot see what is new in chat, on the dashboard or in long threads    |
| #1584 | should | R-S24, R-S25, R-S26, R-L7                       |                | Post page: the follow state looks like a button, Reply targets the wrong person           |
| #1585 | should | R-S22, R-S23                                    |                | Post page: focus is lost after collapse and delete, and a new comment is not announced    |
| #1586 | should | R-S27                                           |                | Author names are not links to the profile, and authors are shown in different ways        |
| #1587 | should | R-S38, R-S39, R-S40, R-S41, R-L6                |                | Notifications show duplicates, two I am caught up buttons, lines without place or time    |
| #1588 | should | R-S35                                           |                | Lists that load after the page show nothing on error, and the thread panel spins for ever |
| #1589 | should | R-S67, R-L4, R-L5                               |                | Empty states follow two patterns, hold no action, and confuse a new member                |
| #1590 | should | R-S53, R-S54, R-S55, R-S56, R-L8, R-L9, R-L10   |                | Community settings is one 5 300 px page with two layouts and risky instant changes        |
| #1591 | should | R-S57, R-S58                                    |                | Forms save in four ways and give three kinds of feedback                                  |
| #1592 | should | R-S47, R-S48, R-S49                             |                | Lessons have no course outline, and resource rows start with unexplained emoji            |
| #1593 | should | R-S28, R-S29                                    |                | The post list renders the full body of each post, with full media and inner headings      |
| #1594 | should | R-S45, R-S63, R-S64, R-S65, R-L16, R-L19, R-L21 | yes            | Screen texts are false or internal: moderators only, manager, curator, capture            |
| #1595 | should | R-S17, R-S61, R-L13                             |                | Error toasts show Internal Server Error, and messages follow many patterns                |
| #1596 | should | R-S66, R-L14, R-L17, R-L18, R-L20               | yes            | Text style drifts: four date formats, Title Case, French words, copies, two spellings     |
| #1597 | later  | R-L15, R-L22, R-L23, R-L24, R-L25               |                | Accessibility: empty toast landmark, code blocks, chat log role, breadcrumb, emoji field  |
| #1598 | should | R-S15, R-S16                                    |                | Several pages have no h1, the title sits in five places, and there is no skip link        |
| #1599 | should | R-S59                                           |                | Forced colors: checked boxes and radios look unchecked, filled buttons lose their edge    |
| #1600 | should | R-S71, R-S72, R-S73                             |                | Buttons are built by hand, miss the field heights, and one action has several looks       |
| #1601 | should | R-S68, R-S69, R-L26, R-L28                      |                | Type and widths have no rule: nine sizes, about 20 heading styles, three content widths   |
| #1602 | should | R-S70, R-S74, R-L29, R-L30                      | yes            | Colors and icons drift: magenta avatars, seven icon sets, six badges, flat light cards    |
| #1603 | should | R-S50                                           | yes            | The account deletion form lets a member think that the box also removes resources         |

Also filed during the review: #1552 (closed by #1553), #1554 (closed by #1556), #1551 (the token ability of the upload route), #1557 (CI does not type-check the spec files).

## Sources

1. `findings/lead-notes.md`: notes of the review lead (they win over the reviewer files).
2. `findings/newcomer.md`: newcomer (N1 to N34).
3. `findings/experienced-member.md`: experienced member (M1 to M30).
4. `findings/words.md`: editor for the words (W1 to W34).
5. `findings/accessibility.md`: accessibility reviewer (A1 to A19), with the scripts `tools/screen-atlas/a11y-*.mjs`. Their output (`a11y-out/`, named in the evidence lines) is not in the repository: the scripts make it again.
6. `findings/design.md`: design critic (D1 to D33).
7. `atlas-index.md`: the screen atlas (270 screens, 1 068 pictures, commit `382ded2d`). The pictures named in the evidence lines are not in the repository: `tools/screen-atlas/atlas.mjs` makes them again.
8. Issue #1523 of `knowii-oss/knowii`: the scope and the method of the review.
