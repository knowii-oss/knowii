# Findings: experienced-member

## Scope covered

1. Pictures (atlas of commit `382ded2d`), light theme, 1920 px and 360 px: community home (member, error state of "Catch up"), dashboard (member), discussion space, post with comments, composer, emoji picker, chat space, thread panel, direct messages list and conversation (360 px), notification bell panel, Notifications page, `/user/notifications`, course outline, lesson, quiz after an attempt, resources space and resource, page in edit mode, community settings (owner, full page), new space dialog, access group members dialog, remove member dialog, public page of a community (signed-in outsider).
2. Code: `apps/knowii/src` (pages and components of the paths above), `app/Policies/SpacePolicy.php`, `app/Actions/Comments/DeleteComment.php`, `app/Models/Post.php`, `app/Support/PublicCommunity.php`, `routes/web.php`.
3. Roles: member and owner on the daily paths; owner and admin on the settings. Moderator only through code.
4. Not looked at: dark theme (one picture or none per screen), the sign-in and account screens, API tokens, link spaces, the moderator and admin pictures one by one. No browser: what I say about the keyboard and about phones comes from the code.
5. Planned, so not reported (open issues): community home feed of posts #1399 (the public page already has a "Newest posts" block to reuse), sort of posts and comments #1437, draft posts #1405, report content #1394, close comments and move a post #1438, keyboard shortcuts #1442, search #123, chat mark as unread and search #1434, links to chat messages #1436 (comments have "Copy link", chat messages do not), direct messages in the bell #1392, trash and restore #1449, member administration with filters and bulk actions #1401, suspension #1395, invite links #1391, bulk invitations #1390, request to join #838, quiz "require a pass" #1460 (today "Next" and "Mark as done" mark a quiz lesson done without an attempt), comments on lessons #1440, conversation settings #1435, filter of resources by level #273, locked spaces #1421.

## What works

1. Emoji picker: search, quick reactions, frequently used, skin tone that stays, arrow keys in the grid (`discussion/post--emoji-picker--member`, `Components/Reactions/EmojiPicker.tsx:71`).
2. Comment permalinks: "Copy link", and the target comment gets the scroll, the focus and a short highlight (`Components/Comments/CommentSection.tsx:61-73`).
3. Collapse per thread, "Expand all" / "Collapse all", and a click on the thread line collapses it (`discussion/post--full--member`).
4. New posts wait behind a "N new posts" button, so the list does not move under the reader (`Components/Posts/PostList.tsx:43`).
5. Chat: Enter sends, Up arrow edits the last message, typing indicator, thread summary "5 replies · last about 1 hour ago" (`chat/thread--open--member`).
6. A deleted chat message leaves a marker in its place (`Components/Chat/ChatRoom.tsx:439`).
7. Markdown editor: paste and drop of images, preview, Ctrl+B/I/K/E, consecutive images become a gallery (`Components/ui/MarkdownEditor.tsx:594-610`).
8. Course outline: sections, check marks, "3 of 8 done", "Continue" (`course/outline--full--member`).
9. Quiz review: your answer, the correct answer and the explanation per question, "Try again" (`course/quiz--after--member`).
10. "Next" on a lesson also marks it done: one step less (`Pages/SpacePages/Show.tsx:153`).
11. Notification lines: "Done" with an undo of 8 seconds, mute the thread or the space from the line menu, a modifier click opens a new tab and keeps the panel (`Components/Notifications/NotificationsPanel.tsx:378-395`).
12. Notification levels per space with "Same for all spaces of this group" (`notifications/settings--full--member`).
13. Moving an "Access groups only" space to another space group asks to confirm the change of readers (`Components/Spaces/SpaceDialog.tsx:130`).
14. The remove member dialog says what happens: the content stays, the person cannot join again by themselves (`settings/members--remove-dialog--owner`).
15. Every delete dialog puts the focus on "Cancel".
16. The public page of a community shows the newest posts with author, space and time, and a "Join" button (`public/community--full--new-member`).

## Findings

### M1 Deleting your own comment deletes the replies of other members

- Weight: must
- Screen: Post with comments (`/community/atlas-garden/space/garden-talk/post/my-tomatoes-have-yellow-leaves`), member, all widths
- What is wrong: the author of a top-level comment can delete it, and the server deletes every reply under it, written by other people. The dialog says "Its replies are deleted too." A post author can do the same to a whole discussion with "Delete" on the post. A chat message leaves a marker when it is deleted, and a comment of a deleted account also leaves a marker (#1344), but a normal delete does not.
- Why it matters: one member destroys the answers of others, without their consent and without undo. In a help space the answers are the value. Other community tools keep the replies and show "deleted comment" in place of the parent.
- Proposed fix: when a comment with replies is deleted by its author, keep a marker ("This comment was deleted.") and keep the replies; the code that renders a marker exists (`CommentSection.tsx:249-258`). Keep the cascade for moderators only, or not at all. Same rule for a post with comments by others: marker, or only moderators.
- Evidence: `app/Actions/Comments/DeleteComment.php:15-25`; `apps/knowii/src/Components/Comments/CommentSection.tsx:446-447`; `Components/Chat/ChatRoom.tsx:439`; `Pages/Posts/Show.tsx:199`; picture `discussion/post--comment-delete-dialog--member`
- Seen in: code, picture
- Confidence: high

### M2 The post composer and the page and lesson editor throw text away without a question

- Weight: must
- Screen: New post (`/community/atlas-garden/space/garden-talk`), Page in edit mode (`/community/atlas-garden/space/welcome/page/community-guidelines`), Lesson in edit mode, member and owner, all widths
- What is wrong: posts, pages and lessons are written in a `Dialog`. The dialog closes on Escape, on a click outside the panel and on "Cancel". Nothing asks "Discard?", nothing is kept: the composer resets the form when it opens again (`form.reset` in `PostComposer.tsx:54-58`). No draft is stored in the browser.
- Why it matters: a long post or a lesson is lost with one key or a click beside the dialog. Escape is also the key that closes the mention list and the emoji list inside the editor, so a second Escape is a likely slip.
- Proposed fix: in `Components/ui/Dialog.tsx`, add an option "dirty" that asks before it closes; use it in `PostComposer`, `PageEditor`, `SpaceDialog`, `CreateResourceDialog`. Keep the text of an unsent post per space in local storage until it is published. Draft posts as a feature are #1405; this is the guard against loss, not the feature.
- Evidence: `Components/Posts/PostComposer.tsx:54-63,93-96`; `Components/SpacePages/PageEditor.tsx:101-110`; `Components/ui/Dialog.tsx:18-20,72`; pictures `discussion/composer--open--member`, `pages/page--edit--owner`
- Seen in: code, picture
- Confidence: high

### M3 "Private" community does not make anything private

- Weight: must
- Screen: Community settings, Community details (`/community/atlas-garden/settings`), owner and admin, all widths
- What is wrong: the community has a "Private / Public" switch. The reading rule ignores it: a space with the visibility "Public" is readable by anyone in a "Private" community, and a public chat space by any signed-in person. The help text under the switch says "For a list of public communities, which comes later."
- Why it matters: an owner who sets "Private" believes the content is closed. It is not. This misleads about privacy.
- Proposed fix: either make "Private" a ceiling (no public space and no public page while the community is private, and say so in the space form), or rename the switch to what it does today ("Listed in the directory" / "Not listed") and put it with the future directory. Show the count of public spaces next to the switch.
- Evidence: `app/Policies/SpacePolicy.php:28-31`; `app/Support/PublicCommunity.php:17-22`; `libs/common/src/lib/types/community.schema.ts:42`; picture `settings/community--full--owner`
- Seen in: code, picture
- Confidence: high

### M4 The chat opens below the "New messages" line and marks everything read

- Weight: should
- Screen: Chat space (`/community/atlas-garden/space/lounge`), member, all widths
- What is wrong: the member has 11 unread messages. The log scrolls to the newest message on load, so the "New messages" line is above the visible part. The room is marked read as soon as the last message renders.
- Why it matters: the unread marker exists but the member does not see it; they must scroll up and guess where they stopped. The count in the sidebar goes to 0 before anything was read.
- Proposed fix: in `ChatRoom.tsx`, on the first render with `firstUnreadCuid`, scroll that message to the top of the log instead of the bottom, and add a "Jump to the newest" button while the log is not at the bottom. Mark as read when the newest message was in view.
- Evidence: `Components/Chat/ChatRoom.tsx:81,99-112,379`; picture `chat/space--full--member--1920--light.png` (no divider in view, sidebar count 11 before the visit)
- Seen in: code, picture
- Confidence: high

### M5 Unread state shows only in the community sidebar

- Weight: should
- Screen: Dashboard (`/dashboard`), member; Community pages at 360 px (button "Spaces"), member
- What is wrong: the dashboard cards show name, description and visibility, nothing new. At 360 px the sidebar is behind a "Spaces" button with no dot and no count, and "Messages 7" is inside that drawer. A direct message creates no notification today (in-app kind planned in #1392).
- Why it matters: a member of three communities cannot see where something happened. On a phone, seven unread direct messages are invisible until they open a drawer they have no reason to open.
- Proposed fix: on `CommunityBox` show a dot for new content and the count of unread direct messages; sort the dashboard by last activity, not by name. On the "Spaces" button in `CommunityShell.tsx:111-114` show a dot when a space has new content and the count of unread direct messages.
- Evidence: pictures `dashboard/home--full--member--1920--light.png`, `chat/space--full--member--360--light.png`; `Components/Spaces/CommunityShell.tsx:49-53,111-114`; `Pages/Dashboard.tsx:28-35` (alphabetical)
- Seen in: picture, code
- Confidence: high

### M6 A long thread does not say which comments are new

- Weight: should
- Screen: Post with comments, member, all widths
- What is wrong: the member was away nine days. Two of the nine comments are new ("about 1 hour ago"), but they look the same as the others. There is no "new" mark, no "jump to the first new comment", no count of new comments on the post card in the space list.
- Why it matters: in a busy thread the returning member reads everything again or misses the reply. This is the main reason people use the "unread" view of other community tools.
- Proposed fix: keep the last visit per post and member (the "Catch up" query already counts new comments per post, `BuildCatchUp.php:219`); mark newer comments, link "N new" on the `PostCard`, and land on the first new comment.
- Evidence: picture `discussion/post--full--member--1920--light.png`; `Components/Comments/CommentSection.tsx:249-262` (no read state); `Components/Posts/PostCard.tsx:34`
- Seen in: picture, code
- Confidence: high

### M7 "Reply" on a reply answers the wrong person

- Weight: should
- Screen: Post, reply form open, member, all widths
- What is wrong: replies are one level deep. "Reply" on Lena's reply opens a form labelled "Reply to Maya Moderator" (the top-level author) and puts nothing in the text. Nothing tells Lena or the readers that the answer is for her.
- Why it matters: in a thread of four replies, the conversation loses who answers whom. Notification of the right person is not verified (I think the top-level author gets it, from the form).
- Proposed fix: when the target is a reply, prefill `@username ` of that reply's author and label the form "Reply to Lena Fischer"; the mention then also notifies her.
- Evidence: `Components/Comments/CommentSection.tsx:293,409-417`
- Seen in: code
- Confidence: high

### M8 Author names in posts, comments and chat are not links

- Weight: should
- Screen: Discussion space, Post, Chat space, Direct messages, member, all widths
- What is wrong: the author of a post, a comment or a chat message is plain text. The member profile (with "Message" and "Block") is reachable from "Online now", the About page and the resource "Added by", not from the content.
- Why it matters: "who is this, write to them, block them" starts from a message. Today it needs the About page and a scan of the team list, or knowing the user name.
- Proposed fix: one shared author byline (avatar, name linked to `MEMBER_URL`, time) used in `PostCard.tsx:34`, `Posts/Show.tsx:124`, `CommentSection.tsx:261`, `ChatMessageItem.tsx`.
- Evidence: the files above; `Components/Resources/ResourceDetails.tsx:150` (the one link); picture `resources/resource--full--member` vs `discussion/post--full--member`
- Seen in: code, picture
- Confidence: high

### M9 The thread panel blocks the chat at 1920 px

- Weight: should
- Screen: Chat with a thread panel open, member, 1920 px
- What is wrong: the thread opens in a modal `Drawer` that dims the room. With 1,400 px of free width, the member cannot read or answer the room while a thread is open.
- Why it matters: people follow a thread and the room at the same time. Every switch costs a close and a reopen.
- Proposed fix: at `lg` and wider, render `ThreadPanel` as a column beside the log (not modal); keep the drawer below `lg`.
- Evidence: picture `chat/thread--open--member--1920--light.png`; `Components/Chat/ThreadPanel.tsx:58`
- Seen in: picture, code
- Confidence: high

### M10 At 360 px the chat shows two or three messages

- Weight: should
- Screen: Chat space and Conversation, member, 360 px
- What is wrong: the page header, the notification disclosure, the type tags and the "here" button take the top; the composer with its toolbar, the Markdown hint and the key hint takes about 240 px. The message log is about 230 px high.
- Why it matters: chat on a phone is mostly reading. The member scrolls inside a small box inside a scrolling page.
- Proposed fix: at small widths, hide the Markdown hint and the key hint behind the toolbar, collapse the toolbar to one button, and give the log the height of the viewport minus the composer.
- Evidence: pictures `chat/space--full--member--360--light.png`, `messages/conversation--direct--member--360--light.png`; `Components/Chat/ChatRoom.tsx:344`
- Seen in: picture
- Confidence: high

### M11 On a phone, Enter sends the chat message

- Weight: should
- Screen: Chat space, Conversation, Thread panel, member, 360 px
- What is wrong: Enter without Shift sends. A phone keyboard has no Shift+Enter, so a member cannot write a second line, and the "return" key sends half a message.
- Why it matters: lists and two-line messages are common; a sent half message then needs an edit.
- Proposed fix: on a coarse pointer (`matchMedia('(pointer: coarse)')`), let Enter add a line and send with the button; change the hint to match.
- Evidence: `Components/Chat/ChatComposer.tsx:94-98,139`
- Seen in: code
- Confidence: medium (not verified on a device)

### M12 No keyboard path to send a post, a comment or a page

- Weight: should
- Screen: New post, comment form, page and lesson editor, member and owner
- What is wrong: the chat sends with Enter. The comment form, the post composer and the page editor have no Ctrl/Cmd+Enter; the member must Tab to the button. The editor already handles Ctrl+B/I/K/E.
- Why it matters: experienced members write many comments a day; the two composers behave differently.
- Proposed fix: in `MarkdownEditor.tsx` (the handler at line 452), send the form on Ctrl/Cmd+Enter; say it in the hint. Keyboard shortcuts in general are #1442; this one belongs to the editor.
- Evidence: `Components/ui/markdown-format.ts:78-83`; `Components/Comments/CommentForm.tsx` (no key handler)
- Seen in: code
- Confidence: high

### M13 The Notifications page shows the same item up to three times

- Weight: should
- Screen: Notifications page (`/notifications`), member, 1920 px
- What is wrong: "Who wants to share an order of seeds?" is in "Catch up" and in "Needs you"; "The garden hall is closed next week" in "Catch up" and in "Following"; "My tomatoes have yellow leaves" in all three. The page has two buttons "I am caught up" that call the same request.
- Why it matters: the member triages the same thing twice and cannot tell which button clears what.
- Proposed fix: on the page and in the panel, leave out of "Catch up" the lines that are already in "Needs you" or "Following", or show "Catch up" only on the community home. Keep one "I am caught up".
- Evidence: picture `notifications/page--full--member--1920--light.png`; `Components/Notifications/NotificationsPanel.tsx:562-581,755-761`; `Components/Notifications/CatchUpCard.tsx:98-115`
- Seen in: picture, code
- Confidence: high

### M14 "Catch up" lines lose the space and the time

- Weight: should
- Screen: Community home, "Catch up" card, member, all widths
- What is wrong: a line shows "space, time" only when it has no excerpt. Three of four lines have an excerpt, so they say neither where nor when.
- Why it matters: after nine days away, "where" and "how old" decide what to open first.
- Proposed fix: in `CatchUpCard.tsx:166-176`, always show "space · time" on a third line or after the title.
- Evidence: picture `community/home--full--member--1920--light.png`; `Components/Notifications/CatchUpCard.tsx:170-175`
- Seen in: picture, code
- Confidence: high

### M15 Community settings is one page of 5,300 px without section navigation

- Weight: should
- Screen: Community settings (`/community/atlas-garden/settings`), owner and admin, 1920 px and 360 px
- What is wrong: ten sections (details, direct messages, who can join, search engines, spaces, moderator rights, members, invitations, access groups, ownership, delete) follow each other on one page. There are no tabs, no anchors, no table of contents. The full page is 5,336 px at 1920 px. The danger zone is at the bottom of the same scroll.
- Why it matters: "change a role" means scrolling past the space tree every time; a moderator sees a different subset with the same layout and no orientation.
- Proposed fix: split into sub-pages or tabs with their own address (`/settings/members`, `/settings/spaces`, `/settings/access`, `/settings/general`), so a link and the back button work; start with anchors and a sticky list of sections if routes are too much.
- Evidence: picture `settings/community--full--owner--1920--light.png`; `Pages/Communities/Settings.tsx:80-170`
- Seen in: picture, code
- Confidence: high

### M16 Two save models on one settings page

- Weight: should
- Screen: Community settings, owner and admin, all widths
- What is wrong: "Direct messages", "Who can join", "Search engines" and the member roles save at once. "Community details" and "Moderator rights" need "Save". The cards look the same. "Open" in "Who can join" opens the community to anyone with an account in one click, without a question.
- Why it matters: the owner does not know if a change is live; a misclick on "Open" or on indexing changes who gets in or what search engines see.
- Proposed fix: choose one model per kind: instant save with a visible "Saved" next to the control for toggles, plus a confirmation for the two that widen access ("Open", indexing on); keep forms with "Save" for text. Share it in one component (see "Missing building blocks").
- Evidence: `Components/Communities/DirectMessagesSetting.tsx:13,43`; `JoinPolicySetting.tsx:19,54`; `IndexingSetting.tsx:14,46`; `ModeratorPermissionsForm.tsx`; picture `settings/community--full--owner`
- Seen in: code, picture
- Confidence: high

### M17 A role changes from a select, at once, without undo

- Weight: should
- Screen: Community settings, Members, owner and admin, all widths
- What is wrong: the role select in each row calls the server on change. A slip with the arrow keys on the focused select makes a member "Admin". Only a toast follows; no confirm, no undo.
- Why it matters: an admin can change the community and remove people. Granting it should be deliberate.
- Proposed fix: confirm when the new role is higher than the old one, or show "Undo" in the toast for 8 seconds as the notifications do.
- Evidence: `Components/Communities/CommunityMembersManager.tsx:171-182`; picture `settings/members--role-menu--owner`
- Seen in: code, picture
- Confidence: high

### M18 Adding a person to an access group needs their exact user name or email

- Weight: should
- Screen: Members of an access group, dialog, owner, all widths
- What is wrong: the field "Username or email" has no suggestions. "New message" has a search of members by name (`MemberPicker`).
- Why it matters: the manager must look up the user name elsewhere, then type it exactly. Two screens that pick a member behave differently.
- Proposed fix: move `Components/Messages/MemberPicker.tsx` to `Components/ui` and use it in `AccessGroupsManager.tsx` (field at line 303) and in the ownership transfer.
- Evidence: picture `settings/access-group--members-dialog--owner`; `Components/Communities/AccessGroupsManager.tsx:303`; `Components/Messages/MemberPicker.tsx:23-27`
- Seen in: picture, code
- Confidence: high

### M19 The lesson page has no course outline

- Weight: should
- Screen: Lesson (`/community/atlas-garden/space/balcony-course/page/watering`), member, 1920 px and 360 px
- What is wrong: the lesson shows "Lesson 5 of 8", Previous and Next. The list of lessons and sections is only on the course page. The left column is the community navigation.
- Why it matters: going back to a quiz or skipping to lesson 7 costs a trip to the outline. At 1920 px the right half of the page is empty.
- Proposed fix: show the outline (sections, check marks, current lesson) in a column at `xl` and in a disclosure "Lessons (3 of 8 done)" at small widths; reuse the list of `PageList.tsx`.
- Evidence: pictures `course/lesson--not-done--member--1920--light.png`, `course/outline--full--member--1920--light.png`
- Seen in: picture
- Confidence: high

### M20 After "New community" the owner stays on the dashboard

- Weight: should
- Screen: New community dialog (`/dashboard`), member, all widths
- What is wrong: after a create, the dialog closes, a toast says "Community created successfully" and the new card is put in alphabetical order among the others. The owner must find it, open it, then open Settings to add spaces. Same after a new space: the owner stays in the settings and must find the space in the navigation.
- Why it matters: "create a community" is a main path; the next step is always the same.
- Proposed fix: after a create, go to the new community (or straight to its spaces settings) in `Dashboard.tsx:28-35`; after a new space, offer "Open the space" in the toast.
- Evidence: `Components/Communities/CreateCommunityDialog.tsx:62-73`; `Pages/Dashboard.tsx:28-35`; `Components/Spaces/SpaceDialog.tsx:173`
- Seen in: code
- Confidence: high

### M21 The level icons of resources are not explained

- Weight: should
- Screen: Resources space (`/community/atlas-garden/space/reading-list`), Community home, member, all widths
- What is wrong: each card starts with an emoji for the level (🌱 🌿 🌳 🏆 ❓) and an icon for the type. The level emoji is `aria-hidden` and has no title; the list has no legend. "❓" (no level) looks like an error. Every card also says "Link only".
- Why it matters: the member cannot use the level to choose what to read, which is the reason it exists. The resource page shows the word ("Beginner"), the list does not.
- Proposed fix: in `ResourceCard.tsx`, show the level as the text chip that the resource page uses, hide it when there is no level, and drop "Link only" from the list. Filtering by level is #273.
- Evidence: pictures `resources/space--full--member--1920--light.png`, `resources/resource--full--member--1920--light.png`; `Components/Resources/ResourceCard.tsx` (the `aria-hidden` span)
- Seen in: picture, code
- Confidence: high

### M22 The lock on every space tells a member nothing

- Weight: should
- Screen: Community navigation, member, all widths
- What is wrong: every "Members only" space has a lock, also for the member who is inside. The lock sits next to the "new" dot and the unread count and makes the row busier. #1421 plans the lock for spaces the member cannot open: the same icon will then have two meanings.
- Why it matters: the useful signals (new, unread) compete with a constant one.
- Proposed fix: in `SpaceNavigation.tsx:28-36`, show the visibility icon only to managers, or only for "Public" (a globe: "visitors can read this"), and keep the lock for #1421.
- Evidence: picture `community/home--full--member--1920--light.png`; `Components/Spaces/SpaceNavigation.tsx:28-36,111`
- Seen in: picture, code
- Confidence: medium

### M23 The post list shows full images and video players

- Weight: later
- Screen: Discussion space (`/community/atlas-garden/space/garden-talk`), member, 1920 px
- What is wrong: each card shows the start of the text, the full first image or the video player, and a reaction bar. Sixteen posts make a page of 5,228 px.
- Why it matters: scanning titles for something new takes long scrolling; a busy space becomes a feed instead of an index.
- Proposed fix: in `PostCard.tsx`, a thumbnail at a fixed small size, two lines of text, and the counts (comments, reactions) on one line; a full card only for the pinned post.
- Evidence: picture `discussion/space--full--member--1920--light.png`
- Seen in: picture
- Confidence: medium

### M24 At 360 px each comment carries up to three rows of controls

- Weight: later
- Screen: Post with comments, member, 360 px
- What is wrong: reaction button, then "Reply" and "Copy link", then "Edit" and "Delete" on their own row. The controls take more height than a short comment.
- Why it matters: a thread of nine comments is four screens of buttons on a phone.
- Proposed fix: keep "Reply" and the reaction button visible; put "Copy link", "Edit", "Delete" in the `DropdownMenu` that the notification lines already use.
- Evidence: picture `discussion/post--full--member--360--light.png`
- Seen in: picture
- Confidence: high

### M25 The default notification level of a space can only be set after it exists

- Weight: later
- Screen: New space dialog, owner
- What is wrong: "What members get by default" shows only when the space is edited, not when it is created.
- Why it matters: an announcement space needs "Everything" from the first post; the owner must create, then edit.
- Proposed fix: show the field on create too (`SpaceDialog.tsx:462`), with the built-in default selected.
- Evidence: `Components/Spaces/SpaceDialog.tsx:462-480`; picture `settings/space-form-discussion--new--owner`
- Seen in: code, picture
- Confidence: high

### M26 A failed "Catch up" request shows nothing

- Weight: later
- Screen: Community home, "Catch up" card, error state, member
- What is wrong: when the request fails, the card does not render. The member cannot tell "nothing new" from "could not load".
- Why it matters: it hides what the member came back for.
- Proposed fix: on error, a one-line card "Catch up could not load. Retry."
- Evidence: picture `community/home-catch-up--error--member--1920--light.png`; `Components/Notifications/CatchUpCard.tsx:134`
- Seen in: picture, code
- Confidence: high

### M27 No direct way from the notifications to their settings

- Weight: later
- Screen: Notification bell panel, Notifications page, member
- What is wrong: the panel and the page have no "Settings" link in their header; the only way is the "..." menu of a line or the user menu. The page has no breadcrumb, while `/user/notifications` has one.
- Why it matters: the moment a member is annoyed by a notification is the moment they look for the settings.
- Proposed fix: a "Settings" link next to the heading "Notifications" in `NotificationsPanel.tsx` (both variants).
- Evidence: pictures `notifications/bell-panel--open--member`, `notifications/page--full--member`; `Components/Notifications/NotificationsPanel.tsx:609`
- Seen in: picture, code
- Confidence: high

### M28 "Remove member" is an icon; the next list uses words

- Weight: later
- Screen: Community settings, Members and Access groups, owner, 1920 px
- What is wrong: the member rows end with an icon-only button (person with minus). The access group rows below use "Members", "Space groups", "Edit", "Delete" in words, and the access group members dialog uses "Remove" in words.
- Why it matters: the destructive action is the least clear one on the screen.
- Proposed fix: "Remove" with its icon, as in the access group dialog.
- Evidence: crop of `settings/community--full--owner--1920--light.png` (Members section)
- Seen in: picture
- Confidence: high

### M29 The first reaction takes two clicks

- Weight: later
- Screen: Post, Chat, member, 1920 px
- What is wrong: with no reaction yet, the member opens the picker, then picks. The quick reactions are inside the picker.
- Why it matters: a "+1" is the most frequent action in a community.
- Proposed fix: show the quick reactions on hover or focus of the item at 1920 px, next to the picker button.
- Evidence: picture `discussion/post--emoji-picker--member--1920--light.png`; `Components/Reactions/ReactionBar.tsx`
- Seen in: picture
- Confidence: medium

### M30 Reordering spaces is one step per click

- Weight: later
- Screen: Community settings, Spaces, owner
- What is wrong: up and down buttons move a space or a group by one place; moving a space to another group is in the edit dialog.
- Why it matters: moving the tenth space to the top is nine clicks and nine requests. The buttons are right for the keyboard; they need a faster path beside them.
- Proposed fix: add "Move to..." (position and group) in the row, keep the arrows.
- Evidence: `Components/Spaces/SpaceTreeEditor.tsx:22,46-62`; crop of `settings/community--full--owner--1920--light.png` (Spaces)
- Seen in: code, picture
- Confidence: high

## Missing building blocks

1. Member picker: `Components/Messages/MemberPicker.tsx` is a good combobox but lives in Messages. The access group members dialog (`Components/Communities/AccessGroupsManager.tsx:303`) and the transfer of ownership (`Pages/Communities/Partials/TransferOwnershipForm.tsx`) use their own fields. Move it to `Components/ui`.
2. Author byline (avatar, linked name, time, "edited"): built by hand in `Components/Posts/PostCard.tsx:34`, `Pages/Posts/Show.tsx:124`, `Components/Comments/CommentSection.tsx:261`, `Components/Chat/ChatMessageItem.tsx:48-64`, `Components/Resources/ResourceDetails.tsx:150`, `Pages/Communities/Public.tsx`. Only some link to the profile, only some show an avatar.
3. Instant-save setting card: `DirectMessagesSetting.tsx`, `JoinPolicySetting.tsx`, `IndexingSetting.tsx` repeat the same state ("the value being saved; null: the value of the community"), the same reload and the same layout. One component with an optional confirmation (M16).
4. Unsaved-changes guard for `Components/ui/Dialog.tsx`: needed by `PostComposer.tsx`, `PageEditor.tsx`, `SpaceDialog.tsx`, `CreateResourceDialog.tsx`, `QuizEditor.tsx` (M2).
5. Item action overflow: comments (`CommentSection.tsx:287-330`) and chat messages (`ChatMessageItem.tsx:97-108`) lay out each action as a button; `NotificationLine.tsx` already uses `DropdownMenu` for the same need (M24).

## Proposals for the owner

1. None for the look of the whole. The gaps I found are in behavior (read state, landing after actions, data safety), not in the visual system.
