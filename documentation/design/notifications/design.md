# Notification experience design for the Knowii Platform (final, approved on 2026-11-15)

Status: approved by the owner on 2026-11-15 (the 16 decisions are in section 12). Tracking issue: #1392, with the build slices #1474 to #1483. Built so far: slices 1 to 3 (ADR 0042, ADR 0048). This file is the text of the two design comments on #1392.

This design covers #1392 (in the app), #1393 (email), #191 (weekly summary) and #841 (settings). It comes from a research step, three member voices (a member who left, an overwhelmed member, a community builder), a first draft, and five reviews of that draft (a hostile reader, the overwhelmed member, the member who left, a feasibility check against the code, an accessibility check). The owner approved it with the 16 decisions of section 12. Every number is a first value to test with the real community.

What the reviews changed in the first draft, in short:

1. The level gives the channel, with no exception in the catalogue. A fourth class, "Account", holds the mandatory messages.
2. The bell number means "new for you". It goes away when the member saw the lines. It can never stay at "9+".
3. "Seen" stops the email. A text that the member read in the list is not sent by email.
4. One weekly email budget per account is enforced in code.
5. An announcement always reaches members who do not visit. One announcement alone sends the weekly summary.
6. No mail about absence exists. The day-3 mail, the day-7 mail and "Do you still want our emails?" are deleted.
7. Three follow words in place of four. Mute is full.
8. A journey for the member who is invited after months of silence (4.I).
9. Rules for a member in several communities (2.6), for blocks (6.9) and for access checks at read, send and summary time (6.8).
10. The parts copied from big products are cut: duty rotation, claims, the weekly health mail, Snooze, Save and key shortcuts.
11. Slice 1 is smaller: one table, one action, three kinds, in the app only. Events, questions, phone alerts and reply by email are late slices.

## 1. The promise and the principles

### 1.1 The promise (what a member can read)

"Knowii tells you when a person writes to you, and when the owner has news. It does this once, with the real text and the reason. It stays silent when nothing is for you. Everything else waits in a calm place, and you can catch up in one minute after any absence. You can stop any email in one click, without a login."

### 1.2 Principles (each one has a yes or no test)

1. Traceable. Every email shows "You got this because ..." and a link to the setting behind it. Every line in the app has the same reason one click away. Test: open any item and find the reason.
2. About me first. A reply to me, a mention and a direct message show in the app within 5 seconds. Test: send a reply and time the bell.
3. Small and true numbers. The bell number counts only new lines that are for me. It shows 1 to 9, then "9+". It goes to zero when I saw the lines. Test: open the panel, wait 2 seconds, close it. The number is gone.
4. One truth. The bell, the list, the navigation markers and the tab title use the same server values. Two devices differ by 5 seconds at most. Test: read an item on a phone and look at the laptop.
5. The text is in the message. An email and a line show the author and the text (at least 300 characters, the whole text when it is under 1 500). Test: can the member decide without opening the site?
6. Once per thing. One event gives one line in the app and one email at most. A thing that I saw is never sent again and is never in a summary. Test: read a line in the list, wait one hour, look at the mailbox.
7. Stop in one click. Every email that is not about the account has a one-click stop, with no login and with an undo. Test: count the clicks. The result must be 1.
8. Silence is allowed. The platform sends nothing when nothing is for the member. It never sends a mail about absence, a streak or a count of missed things. Test: a member with nothing for them, in a week with no announcement and no event, gets zero mail.
9. The time belongs to the member. Email is held from 22:00 to 08:00 in the time zone of the account. Every time shows its zone in words. Test: change the zone and look at the send time.
10. Mute means mute. A muted space, thread or conversation sends nothing, in no channel, with no exception. Test: mute, then mention the member, then look at every channel.
11. No debt. No total of unread things exists. Old lines fold away after 14 days. "I am caught up" loses nothing. Test: after 3 months away, the bell shows no number above the lines of the last 14 days.
12. A budget, not a hope. One account gets 7 emails in 7 days at most, account safety mail excluded. The code enforces it. Test: an automated test sends 30 events and counts the emails.
13. For everyone. Keyboard only, screen reader, 360 px, dark and light, no signal by color only, no tracking pixel, no sound. Test: the list in 9.11.

### 1.3 The tension: "people should know" against "do not overload"

1. To be told and to be able to know are two things. Few things tell a person. Everything else is easy to find.
2. The platform makes three promises, and each one has a place. What is for me finds me (the bell, then one email). What I follow waits in the list and in the weekly summary. What happens around me shows as a quiet dot when I arrive.
3. The news of the owner has two protections. A normal announcement is in the list of everyone and in the next weekly summary, also when it is the only item. An announcement that the owner marks "important" (2 a month at most) is also sent by email to each member who did not see it.
4. Test of the balance: a member who never opens a setting gets 7 emails in 7 days at most, and the target for the median member is 2. The same member learns each announcement within 8 days and each event before it starts.

## 2. The attention model

### 2.1 Four levels, and one rule: the level gives the channel

| Level | Name on screen | What it is                                                       | In the app                              | Email                                                                        | Phone alert (late slice)            |
| ----- | -------------- | ---------------------------------------------------------------- | --------------------------------------- | ---------------------------------------------------------------------------- | ----------------------------------- |
| 0     | Account        | Safety and rights of my account. Mandatory.                      | A line at once, counted in the bell     | One email at once. No bundle, no budget, no unsubscribe                      | Never                               |
| 1     | Needs you      | A person wrote to me, or a thing I said yes to changes or starts | A line at once, counted in the bell     | One email if I did not see the line (6.2). Inside the budget                 | Yes, if the member turned alerts on |
| 2     | Following      | A thing I chose to follow, or that the space sends to everyone   | A line under "Following". Never counted | Only inside the weekly summary                                               | Never                               |
| 3     | Around you     | Everything else                                                  | No line. A dot in the navigation        | Only inside the weekly summary, and only if it passes the quality rule (8.4) | Never                               |

1. Each row of the catalogue (section 3) has one level. No row has a channel of its own.
2. A kind of level 1 can set one thing only: the moment of its email (for example "tomorrow at 08:00" for an important announcement).
3. Reactions are level 3. The author sees the counts on the item. No line, no email, no setting.
4. A line of level 2 is hidden from the list after 14 days. A line of level 0 or 1 folds into "Older" after 14 days and is not counted any more.

### 2.2 Follow levels (the one dial of the member)

A space has one of three levels. The word on screen is the same everywhere.

| Word on screen      | Meaning in one sentence                                                                     | Stored |
| ------------------- | ------------------------------------------------------------------------------------------- | ------ |
| Everything          | I get a line for every new post here. In a chat space the lines are bundled, one per space. | `all`  |
| Only what is for me | I am told when someone replies to me, mentions me, or writes in a thread that I follow.     | `mine` |
| Muted               | Nothing from here reaches me, a mention included. The space shows no marker.                | `none` |

1. A thread (a post with its comments, or a chat thread) has three states: "Following", not following, and "Muted". The actions are "Follow", "Stop following" and "Mute this thread".
2. When I post, comment or reply, I follow that thread. The thread shows "Following (you replied)". One click stops it.
3. A mention does not make me follow the thread. I get the mention and nothing more.
4. A chat space is never set to "Everything" by default.
5. The most specific choice wins: thread, then space, then the default of the space.
6. "Mute" always means the same thing at every scope: nothing reaches me from there. The mute button says so: "You will get nothing from this space, also when someone mentions you."

### 2.3 Defaults per kind of space

| Space kind              | Default level       | What a member gets with the default                                                                                            |
| ----------------------- | ------------------- | ------------------------------------------------------------------------------------------------------------------------------ |
| Announcements           | Everything          | Each announcement as a line under "Following" and in the weekly summary. An email only for an announcement marked "important". |
| Discussion              | Only what is for me | Replies to me and threads I follow. New posts: a dot in the navigation, and the summary if they pass the quality rule.         |
| Chat                    | Only what is for me | Mentions and threads I follow. New messages: a dot in the navigation.                                                          |
| Course                  | Only what is for me | A new lesson of a course that I started: one line under "Following" and the summary. No email.                                 |
| Resources, pages, links | Only what is for me | Nothing. New resources: the summary only, by the quality rule.                                                                 |
| Events (after #1388)    | Everything          | A new event as a line under "Following" and in the "Coming up" block of the summary.                                           |

1. A manager can change the default of a space for members who did not choose (7.1). The choice of a member always wins.
2. A manager who sees a restricted space only because of the role, and not through an access group, gets "Only what is for me" there (decision 12).

### 2.4 Why this is simpler than a matrix of kinds by channels

1. The tool that the community uses today asks 16 kinds times 3 channels, so 48 questions. Members keep the defaults, so the default is the design.
2. Here the member answers one question per place: "how close am I?". The platform gets the channel from the level.
3. The account has three more choices: the email rhythm, the weekly summary, and phone alerts (later).
4. Ten times more members and spaces add no setting.

### 2.5 One name per thing (glossary for every screen, email and document)

| Thing                                        | The only name                            | Never                                                    |
| -------------------------------------------- | ---------------------------------------- | -------------------------------------------------------- |
| The bell and its panel                       | Notifications                            | Inbox, Activity                                          |
| Level 0 and level 1 lines                    | Needs you                                | For you, About you (except the summary block, see below) |
| Level 2 lines                                | Following                                | Subscribed, Watching                                     |
| The block of the summary with level 1 items  | About you                                | For you                                                  |
| The weekly email                             | Weekly summary                           | Digest (allowed in code and in issues only)              |
| The state of a line that I did not see       | New                                      | Unread                                                   |
| The button that clears a level 1 line        | Done                                     | Archive, Dismiss                                         |
| The button that clears the "Following" lines | I am caught up                           | Mark all as read                                         |
| The card after an absence                    | Catch up                                 | What you missed                                          |
| The three space levels                       | Everything, Only what is for me, Muted   | Normal, Mentions only, Mute fully                        |
| Thread actions                               | Follow, Stop following, Mute this thread | Unfollow, Unsubscribe                                    |
| Phone messages of the installed app          | Phone alerts                             | Push                                                     |
| The email rhythm setting                     | Email me when I am away                  | Instant email                                            |

### 2.6 A member in several communities

1. Per account: time zone, quiet hours, email rhythm, weekly budget, the rhythm and hour of the weekly summary, the pause, the suppression after a bounce, devices.
2. Per community: space levels, thread follows, mutes, the switch "Important news", the switch "Include this community in my weekly summary", and "Mute this community".
3. One bell for the account. The header of the app is global and the channel `user.{cuid}` covers all communities. The number is the total of the account.
4. When the member is in more than one community, each line shows the name of its community and the panel has a filter per community.
5. One weekly summary per account, with one section per community. One community: 5 items at most. Several: 3 items per community and 9 in total.
6. The sender name comes from the data. A mail about one community uses the name of that community. A summary about several uses the name of the instance. No name is written in the code.

## 3. The event catalogue

Level: 0 Account, 1 Needs you, 2 Following, 3 Around you. The channels come from the level (2.1). "Email moment" exists for levels 0 and 1 only. "Rhythm" means the rule of 6.2. The last column is the slice of section 11.

| #   | Event                                                                    | Who is told                                        | Level  | Email moment                                      | First line                                                                   | Reason                                                 | Slice |
| --- | ------------------------------------------------------------------------ | -------------------------------------------------- | ------ | ------------------------------------------------- | ---------------------------------------------------------------------------- | ------------------------------------------------------ | ----- |
| 1   | Comment on my post                                                       | The author of the post                             | 1      | Rhythm                                            | "Anna replied to your post: <text>"                                          | "You wrote this post."                                 | 1     |
| 2   | Reply to my comment                                                      | The author of the comment                          | 1      | Rhythm                                            | "Ben replied to your comment: <text>"                                        | "You wrote this comment."                              | 1     |
| 3   | Mention in a post, a comment or a chat message                           | The mentioned member                               | 1      | Rhythm                                            | "Marc mentioned you in <space>: <text>"                                      | "Marc mentioned you."                                  | 1     |
| 4   | New comment in a thread that I follow                                    | Followers of the thread                            | 2      | none                                              | "2 new replies in <title>"                                                   | "You follow this thread (you replied)."                | 2     |
| 5   | Reply in a chat thread that I follow                                     | Followers of the thread                            | 2      | none                                              | "Ana replied in a thread in <space>"                                         | "You follow this thread."                              | 2     |
| 6   | Announcement                                                             | Everyone who can see the space and did not mute it | 2      | none                                              | "<Owner> posted in Announcements: <title>"                                   | "Announcements go to every member. Change this."       | 2     |
| 7   | New post in a space at "Everything"                                      | Members at "Everything"                            | 2      | none                                              | "3 new posts in <space>" (one line per space)                                | "You chose Everything for <space>."                    | 2     |
| 8   | New messages in a chat space at "Everything"                             | Members at "Everything"                            | 2      | none                                              | "New messages in <space>" (one line per space)                               | "You chose Everything for <space>."                    | 2     |
| 9   | New lesson in a course that I started                                    | Members with progress in the course                | 2      | none                                              | "Lesson 4 of <course> is out: <one sentence>"                                | "You started this course."                             | 2     |
| 10  | Reaction to my post or comment                                           | Nobody                                             | 3      | none                                              | none                                                                         | none                                                   | none  |
| 11  | Direct message                                                           | The other participants                             | 1      | Rhythm                                            | "Marc: <text>"                                                               | "Marc wrote to you."                                   | 4     |
| 12  | Announcement marked "important"                                          | Members with "Important news" on                   | 1      | The next 08:00 local, if not seen                 | "Important: <title>. <first 300 characters>"                                 | "<Owner> marked this important. Stop: Important news." | 4     |
| 13  | Weekly summary                                                           | Members with the summary on                        | none   | The hour of the member (5.7)                      | The subject names the member's own item                                      | "You get a weekly summary."                            | 5     |
| 14  | Welcome                                                                  | The new member                                     | 0      | At once, one time                                 | "Welcome. Here is what you will get from us."                                | "You joined today."                                    | 5     |
| 15  | Role changed                                                             | The member                                         | 0      | At once                                           | "You are now a moderator of <community>."                                    | "A role changed on your account."                      | 6     |
| 16  | Access added                                                             | The member                                         | 0      | At once, one per 24 hours at most                 | "Your spaces are open: 4 new spaces. Start with <first space of the group>." | "Your access changed."                                 | 6     |
| 17  | Access removed, or removed from the community                            | The member                                         | 0      | At once                                           | "You no longer have access to <space>."                                      | "Your access changed."                                 | 6     |
| 18  | Ownership transfer                                                       | The new and the former owner                       | 0      | At once (the mail exists)                         | "You are the new owner of <community>."                                      | "The ownership changed."                               | 6     |
| 19  | Security: sign-in from a new device, password, email address, two-factor | The account                                        | 0      | At once                                           | "New sign-in to your account."                                               | "This protects your account."                          | 6     |
| 20  | Report on content (#1394)                                                | Moderators with `delete_content`, admins, owner    | 1      | At once                                           | "Report: <reason> in <space>"                                                | "You moderate <community>."                            | 6     |
| 21  | Result of a report                                                       | The reporter and the author                        | 0      | At once                                           | "Your report was handled: <result>"                                          | "You sent a report."                                   | 6     |
| 22  | Invitation accepted                                                      | The inviter                                        | 2      | none                                              | "Ana joined with your invitation."                                           | "You invited Ana."                                     | 6     |
| 23  | Care mail                                                                | The owner, and managers who turned it on           | none   | 09:00 local, only when the Care list is not empty | "2 new members wait for a first reply"                                       | "You manage <community>."                              | 6     |
| 24  | Return line after the move                                               | Each imported member with replies they did not see | 2      | none                                              | "2 people replied to you before the move."                                   | "You wrote these posts."                               | 7     |
| 25  | Event created                                                            | Members of the event space who did not mute it     | 2      | none                                              | "Tue 18 Nov, 17:00 Central European Time: <title>"                           | "You follow <space>."                                  | 8     |
| 26  | Event moved or cancelled                                                 | Members who said Yes or Replay only                | 1      | At once                                           | "Cancelled: <title>."                                                        | "You said yes to this event."                          | 8     |
| 27  | Event reminder, 24 hours                                                 | Yes                                                | 1      | T-24 h                                            | "Tomorrow 17:00 Central European Time: <title>"                              | "You said yes."                                        | 8     |
| 28  | Event reminder, 1 hour                                                   | Yes                                                | 1      | T-1 h                                             | "In 1 hour: <title>. Join"                                                   | "You said yes."                                        | 8     |
| 29  | Replay ready                                                             | Replay only: level 1. Yes: level 2                 | 1 or 2 | When the replay is published                      | "The replay of <title> is ready."                                            | "You asked for the replay."                            | 8     |
| 30  | First answer to my question                                              | The author                                         | 1      | Rhythm                                            | "Anna answered your question: <text>"                                        | "You asked this question."                             | 9     |

Rows that the draft had and that are gone: the first-week mail of day 3, the "Keep these emails?" mail, the weekly health mail, the follow-up question after an event, the reminder at T-3 days, the pinned post line, the "show likes" option, and the email for each new lesson. Row 30 is row 1 with other words. It exists only after posts can carry a "Question" tag (#1389).

## 4. Journeys

Times are local to the member. "Never" lists what the person does not get.

### 4.A First week of a new member (Anna joins on a Tuesday at 10:00 in Singapore)

1. 10:00. Sign-up. The browser gives the time zone. The platform asks no question about it.
2. 10:02. One screen: "How much do you want to hear from us?" (5.12). "What is for me, plus a weekly summary" is selected. She clicks "Continue".
3. 10:03. One welcome email from the owner by name: what will reach her, what never will, how to stop each thing, and one button, "Introduce yourself". The list shows one line with the same button.
4. 10:20. She posts an introduction. The Care list of the managers shows it under "To welcome" if no person replied after 24 hours (7.3).
5. Day 1, 09:00. Someone replies. The bell shows 1 at once. If she does not see the line, one email goes after 10 minutes, outside quiet hours.
6. The Monday after day 6, 08:00 Singapore time: her first weekly summary, if the rule of 5.8 says send.
7. Never: a day-3 mail, a "complete your profile" mail, likes, lists of new members, points, streaks, reminders for events she did not answer, upsell, an email between 22:00 and 08:00.

### 4.B A normal day of an active member (Marc, Paris)

1. 08:30. He opens the app. The bell shows "2". He opens the panel. "Needs you" has one reply and one mention with their text. "Following" has 3 lines. After one second the number is gone.
2. 08:32. He opens the reply. The view goes to the comment and the focus goes there. The line leaves the list.
3. 13:00, app closed. Someone replies to his post. At 13:10 one email with the full reply and one button. It is his first email of the day.
4. 15:00 and 16:20. Two more replies. They wait. At 17:00 one email holds both.
5. 22:00 to 08:00. Replies are held. At 08:00 one email holds them, only if he did not see them in the app.
6. Never: a mail for a text that he saw in the list, a third email on the same day, a mail for a like, a mail at night.

### 4.C The member who reads once a week (Lea, Saturday morning)

1. Monday 08:00. Her weekly summary arrives. It has 5 items at most. She reads it in 2 minutes.
2. Monday to Saturday. Nothing, unless a person writes to her.
3. Saturday 09:00. She opens the app. The bell has no number. "Following" shows the lines of the last 14 days. She clicks "I am caught up".
4. Never: a mail for "new posts in spaces", a reminder, a count of things she missed.

### 4.D A member who comes back after a long absence (Tom)

1. Day 0 to 30. Level 1 items reached him by email, inside the budget. The weekly summary came when it had something (5.8).
2. After day 30. The summary comes every second week at most, in a short form: items about him, the announcements, the next event.
3. Day 90. He opens the app. The first screen is the "Catch up" card (5.5): 5 lines at most. The bell counts only the lines of the last 14 days. Older "Needs you" lines are folded under "Older".
4. The browser reports another time zone than the stored one. The card asks once: "Show times in Lisbon time? Yes, or keep Paris".
5. If he had not come for 180 days, the summary is paused without a message. On his return the card says: "Your weekly summary is paused. Turn on". Important announcements and messages from people still reached him.
6. Never: a mail about his absence, a question with a deadline, a number "37", a backlog of 3 months.

### 4.E The owner and a moderator

1. 09:00 local, the owner: one care mail, only if the Care list is not empty: "2 new members wait for a first reply", with the two names and links.
2. At once: a report on content (level 1 for the managers who can delete content).
3. The owner writes an announcement. Before the send the screen says: "This goes to the notifications of 547 members. It will be in the next weekly summary of 431 members." The counts hold only members who can see the space.
4. If he ticks "Important": "Also by email tomorrow at 08:00 to the members who did not see it (up to 410). You used 1 of 2 this month."
5. The post waits 5 minutes after publication, so that edits do not notify again. "Send now" skips the wait.
6. Never: a mail per post, comment or new member. A weekly report. A list of who read what.

### 4.F An event from announcement to replay (after #1388)

The same moment is 17:00 in Paris, 11:00 in New York and 00:00 in Singapore. Each message shows the time of the member with the zone in words.

1. Creation. A line under "Following" for the members of the event space, and the "Coming up" block of the next summary. No email of its own. If the event starts before the next summary date of a member, the summary goes this week (5.8).
2. The member answers on the event page or from the summary: "Yes", "Replay only" or "No". A link in an email opens the event page with the answer selected and one button to confirm. A link alone never changes an answer, because mail scanners open links.
3. T-24 h, for a Yes: a line and one email with a calendar file, the local time and the link "I cannot come, send me the replay".
4. T-1 h, for a Yes: a line and one email (a phone alert in its place when alerts exist). This reminder passes quiet hours.
5. Replay published: level 1 for "Replay only", level 2 for "Yes". The same text for both. The platform has no attendance data, so it never says "You could not join".
6. Moved or cancelled: at once to "Yes" and "Replay only", with the new time. It passes quiet hours when the start is within 12 hours.
7. Never: a reminder for an event that the member did not answer, a count of people who come, a follow-up question, a time without the zone.

### 4.G A question that gets its first answer (after tags, #1389)

1. 14:00. Ana posts with the tag "Question". Nobody gets a line, except members at "Everything".
2. 14:20. Ben answers. Ana gets the bell at once. The words are "Ben answered your question".
3. 14:30. If she did not see it: the email, by the rhythm of 6.2. No shorter delay exists for answers.
4. If nobody answers after 12 hours, the question enters "To answer" in the Care list. A question is only a post with the tag. A question mark in a title means nothing.
5. Never: a message to Ana that nobody answered.

### 4.H A paid member who gets access to new spaces

1. At once, one line and one email (level 0): "Your spaces are open: 4 new spaces. Start with <first space of the group>." The first space of the group in the navigation order is used. No "most used" value exists.
2. The new spaces start at the default of their kind. No line per space.
3. Never: a mail after 7 days without a visit, an upsell, a message about spaces that the member does not have.

### 4.I A member who is invited after months of silence (the move to the platform)

Paul was a member of the community. He did not visit for 6 months. The community moves.

1. The invitation goes to members of the community and to buyers who never joined. It never goes to a person who left or who asked to be removed.
2. The invitation is one email from the owner by name, in plain words. It says what moved, gives 3 concrete things that changed, and has one button. It has no "Welcome" and no "We miss you". One reminder goes after 7 days. Then nothing.
3. The import runs silenced (6.10). The history creates no notification and no email.
4. Paul clicks. His first screen is the "Catch up" card, not the welcome of a new member. He gets no welcome email.
5. If Paul got replies in the old tool that he never saw, he gets one line: "2 people replied to you before the move." It is level 2: no number, no email.
6. His earlier choices move with him when the export holds them. A member who had the weekly email off starts with the summary off.
7. With no known choice, the question of 5.12 has "Only what is for me" selected for a member who was not seen for 90 days or more, and "What is for me, plus a weekly summary" for the others (decision 15).
8. Never: a second reminder, a mail for each old reply, a summary that he did not accept, a count of what he missed.

## 5. Surfaces

### 5.1 The Notifications panel behind the bell

1. Purpose: what needs me and what I follow. A list that empties, not a log.
2. Content: two headings, "Needs you" (levels 0 and 1) and "Following" (level 2, last 14 days). Each line has the avatar, one sentence ("Anna and 2 others replied to <title>"), a 2-line excerpt, the space, and a time. Lines are grouped by thing, not by event.
3. A line that I did not see yet is "New": a bold sentence and the word "New". When the line was on screen for one second, it is seen (6.1). It stays in the list.
4. "Needs you" lines stay until I open them or click "Done". After 14 days they fold into "Older" at the end of the list. "Older" is closed by default and is never counted.
5. The line is a link. Opening it goes to the exact reply, marks the line read, and moves the chat cursor in the same action.
6. Buttons on a "Needs you" line: "Done". Buttons on a "Following" line: "Stop following". A menu on each line holds "Mute this thread", "Mute <space>" and "Why did I get this?". The last one shows the reason and a link named after the setting ("Notification settings for Open discussion").
7. "Done" shows "Done. Undo" for 8 seconds.
8. "I am caught up" is visible at the top when "Following" has lines. It clears the "Following" lines. It never touches "Needs you".
9. No filter tabs, no Snooze, no Save, no key shortcuts in the first version. The page `/notifications` shows the same list plus "Earlier": the lines that I opened or marked done, for 60 days.
10. A line that arrives while the panel is open does not enter the list by itself. A button at the top says "2 new. Show".
11. Direct messages are not in this panel. They live in "Messages", which has its own number today (decision 10).
12. States. Empty: "Nothing needs you. Everything is quiet. That is fine." Loading: 4 skeleton lines. Error: "We could not load your notifications. Retry." Many: 20 lines per page, "Load older". A group shows 3 names and "and N others". A deleted author shows as "Deleted member".

### 5.2 Markers in the navigation: one truth with the panel

1. One source per thing. New chat messages: `chat_reads`. Direct messages: the cursor of the conversation. Everything else: notification rows.
2. The bell number is the count of level 0 and level 1 rows that I did not see, and that are 14 days old at most. It shows 1 to 9, then "9+". Zero shows no badge.
3. A space shows a dot when it has content that is newer than my last visit of that space. A chat space shows a number only when I chose "Everything" for it. A muted space shows nothing.
4. "Messages" keeps its number of conversations with new messages.
5. A mention in a chat is a row and is also behind the chat cursor. Reading the chat marks the row seen and read in the same action. Opening the row moves the cursor.
6. The server value replaces the client value on every event, on focus, on reconnection and on page load. The client never adds 1.
7. Test: open two tabs and a phone, look at one line. All three show the same number within 5 seconds.

### 5.3 The pop-up in the app (slice 3)

1. A pop-up shows only for a level 1 line, only when the tab is visible, and never for the thread or the conversation that is open.
2. One at a time. A second one within 30 seconds replaces the first with "3 new for you".
3. It stays 8 seconds plus 1 second per 20 characters, 20 seconds at most. The timer stops while the pointer is on it and while the tab is hidden.
4. It has a close button with a name. It never takes the focus. It holds no action that the panel does not hold.
5. The account has a switch "Show pop-ups: on or off".

### 5.4 The browser tab title

1. The title starts with the bell number plus the number of "Messages": "(3) Knowii Community". No number at zero. "(9+)" above 9.
2. No animation, no flashing, no change of the icon. The badge of the installed app comes with slice 10.

### 5.5 The "Catch up" card

1. Shown as the first card of the community home and at the top of the panel, when the last visit is 7 days old or more. It is also in the menu as "Catch up".
2. Content, 5 lines at most, 2 lines of text each: up to 2 lines that need me, up to 2 announcements since my last visit, and the next event (before events exist: the thread with the most new replies in a space that I follow).
3. When more exists, one link says "Show all" and opens the panel. The card never shows a count of missed things.
4. After 30 days away the content is the same. Announcements are never left out because of their age.
5. "I am caught up" is on the card. It hides the card and the "Following" lines.
6. The card is a plain region in the page. It is not a dialog and not a pop-up.

### 5.6 The notification email

1. Sender name: "Anna on <community name>" for a message from a person. "<Community name>" for the summary and for account mail. Invitation and welcome: "<Owner first name> from <community name>".
2. Subject: "<Actor> <verb> <thing>: <title>". Example: "Anna replied to your post: Notes workflow". A bundle: "3 replies for you in <community name>". A direct message: "Marc sent you a message".
3. When the space is restricted (visibility `groups`, or a private community), the subject has no title and no text: "Anna replied to your post in <space name>".
4. Body: the author, the space, the text (at least 300 characters, cut at the end of a word, the whole text when it is under 1 500), and 2 lines of the parent in a quote.
5. One button, named after its target: "Open the reply from Anna". Under it: "Open the thread to answer." The link goes to the exact reply. It does not sign the member in (decision 11). A member who is signed out sees the sign-in page with the address filled in and "Email me a sign-in link". After the sign-in the thread opens at the reply.
6. The reason: "You got this because you wrote this post."
7. Footer, three links: "Stop emails like this" (one click, no login), "Weekly summary only", "Settings". Then the postal address.
8. "Stop emails like this" stops one group: replies and mentions, direct messages, important news, the weekly summary, or event emails. The page after the click says in words what stopped and what still arrives, and it has "Undo", "Pause all email for 2 weeks", "Pause all email for 1 month" and "Stop all email from <community>". A pause ends by itself, with no message.
9. Headers: `List-Unsubscribe` and `List-Unsubscribe-Post: List-Unsubscribe=One-Click`. They stop the same group as the footer link. Account mail has no such header.
10. No tracking pixel, no link that counts a person, system fonts, a plain-text part.

Example in text:

```
From: Anna on Knowii Community
Subject: Anna replied to your post: Notes workflow

Anna replied to your post (Open discussion):

"I use one inbox note per day and a weekly review on Sunday. The only
rule: nothing stays in the inbox longer than 7 days. For me this
removed the guilt of a full inbox. I also tag only at review time..."

[Open the reply from Anna]
Open the thread to answer.

You got this because you wrote this post.
Stop emails like this | Weekly summary only | Settings
Knowii Community, <postal address>
```

### 5.7 The weekly summary

Shared rules:

1. One email per account. Default: Monday 08:00 in the time zone of the account. The member can choose daily, weekly or off (#191), and the day and hour.
2. 5 items at most for one community. Each item has a title, 2 to 3 lines and one link that names its target.
3. Order of the blocks: "About you" (level 1 items that I did not see), "From <owner name>" (announcements), "Coming up" (events, after #1388), "New" (threads that pass the quality rule of 8.4).
4. No statistics, no "most popular", no list of new members, no count of people, no "question you could answer", no promotion.
5. A thing that I saw in the app is removed. Each item is checked against my access when the mail is built.
6. Announcements: 3 at most. The newest has its first 300 characters, the others have their title. An announcement that I did not see and did not get stays for the next summary, whatever its age.
7. The subject comes from my own item when I have one ("Ben replied to your post"), else from the newest announcement. For a restricted space the subject has no title.
8. The owner can add one intro line of 300 characters, signed with their name. It is optional.
9. When a bundle of level 1 items is due in the same hour, its items go into "About you" and only the summary is sent.
10. Footer: "Stop the weekly summary" (one click), "Settings", the reason, the address.

Example 1, an active week:

```
Subject: Ben replied to your post, and 1 announcement

About you
- Ben replied to your post "Which tool for weekly reviews?"
  "I use a plain checklist note. It takes 10 minutes and..."
  [Read the reply from Ben]
From Sébastien
- "The spring course opens" (first 300 characters ...)
  [Read the announcement]
New
- "A simple weekly review that takes 10 minutes" in Open discussion
  [Read the thread]

You got this because you get a weekly summary.
Stop the weekly summary | Settings
```

Example 2, a quiet week with one announcement:

```
Subject: Sébastien: "The spring course opens"

From Sébastien
- "The spring course opens" (first 300 characters ...)
  [Read the announcement]
Nothing else needs you this week.
```

Example 3, a member who was away for 6 weeks (short form, every second week):

```
Subject: Marc replied to your post

About you
- Marc replied to "How do you handle read-it-later?": "I cap the list at 20 and..."
  [Read the reply from Marc]
From Sébastien
- "The spring course opens" (first 300 characters ...)
- "New office hours on Thursdays"
  [Read the announcements]
```

### 5.8 The rule for sending or not sending the summary

1. Send when one of these is true: an item about me that I did not see, an announcement that I did not see and did not get, an event that I can join and did not answer and that starts within 14 days, or 3 threads that pass the quality rule.
2. In every other case send nothing. No "quiet week" mail exists.
3. One announcement alone sends the summary. This replaces the rule "under 2 items, no mail" of #191 (decision 5).
4. After 30 days without a visit: the short form (About you, announcements, next event), every second week at most. In the week between, the summary goes only when an event that I can join starts before the next summary date.
5. After 180 days without a visit: the summary is paused. No message says so. The card of 5.5 says it on the return. Level 0 and level 1 email continue.
6. A second run of the scheduler cannot send twice (one row per account and period).

### 5.9 The settings page (#841)

1. The first screen holds two things only: the three presets as radio buttons, and the preview "What you will get".
2. Presets: Quiet: "Only what is for me. No weekly summary." Balanced (the default): "What is for me, plus a weekly summary." Close: "Also every new post in the post spaces I can see, in my notifications."
3. A preset never changes a choice that the member made on a space. Every preset keeps "Important news" on, and the preview says so.
4. The preview is text made from the real settings: "A reply to you: in the app at once, and one email if you are away. A new post in Open discussion: a dot in the menu and the weekly summary. An important announcement: one email, 2 a month at most. No email from 22:00 to 08:00, Paris time."
5. "More settings" opens the rest: "Email me when I am away" (Soon, then together / Twice a day, 08:00 and 17:00 / Never), the weekly summary (daily, weekly, off, day and hour, communities included), "Important news" per community, quiet hours, time zone, "Show pop-ups", "Pause all email" (2 weeks or 1 month), "Your spaces", and later "Devices".
6. "Your spaces": one group per space group, one choice per space with the three words, and "Same for all spaces of this group".
7. Account mail is listed as text with its reason ("Required for the safety of your account"). It is not a switch.
8. A change is saved at once and the page says "Saved". On an error the old value stays and the page says "Not saved. Retry."
9. An address that bounced: a box says "We cannot reach <address>. Fix your address." The email choices are shown as not available, with the reason as text.

### 5.10 Phone alerts in the installed web app (#115, slice 10)

1. The platform never asks at the first load. One card in the panel, one time, after a first reply that the member got, and only when the app is installed.
2. Text: "Get a short alert on your phone when someone writes to you. Only that. No likes, no news." The button "Turn on alerts" opens the browser prompt. "Not now" hides the card for 30 days. After 2 times "Not now", the card never comes back.
3. Level 1 only. One alert per thread per 5 minutes. 8 a day at most. Held in quiet hours, except the reminder of 1 hour for a Yes.
4. The alert holds the actor and the verb ("Ben replied to your post"). It never holds the text of the message, so a locked screen shows nothing private.
5. A click on the alert opens the exact item and puts the focus on it.
6. If the browser says "denied", the card says "Alerts are blocked in your browser. Email still works."
7. Nothing in section 3 depends on phone alerts.

### 5.11 Presets in detail

1. Quiet: summary off, "Email me when I am away" on for replies, mentions and direct messages, "Important news" on, spaces at their defaults.
2. Balanced: Quiet plus the weekly summary.
3. Close: Balanced plus "Everything" for the post spaces that the member can see. The lines go under "Following". They are never counted and never sent alone by email.

### 5.12 The first choice

1. One screen, one question: "How much do you want to hear from us?"
2. Three radio buttons with the words of the presets. Balanced is selected. "Continue" works at once. No "Skip" exists, because "Continue" already takes one click.
3. Under the choices, one sentence: "You can change this at any time. Every email has a link that stops it."
4. Reason for the selected choice: a button that is blocked until a click is a wall on the first day, and the weekly budget limits the mail in every case. The overwhelmed member asked for this. If counsel wants consent for the summary (decision 1), only the summary line becomes a box that is not ticked.
5. A member who comes through the move keeps an earlier choice (4.I).

## 6. Delivery rules

### 6.1 Definitions

1. Seen: the line was on screen in the visible app for one second, or the content itself was on screen (the comment in its thread, the message in its chat), or the line was opened or marked done. The server stores `seen_at`.
2. Read: the line was opened or marked done. The server stores `read_at`.
3. Presence is never used, because a hidden member looks offline (ADR 0027). Email opens are never measured.

### 6.2 The ladder for level 1

1. T+0: write the row, send the event on `user.{cuid}` (identifiers only), show the pop-up if the rules of 5.3 allow it.
2. Email rhythm "Soon, then together" (the default): the first item of the local day that I did not see after 10 minutes goes by email. Later items of the same day wait for the 17:00 mail. Items of the night wait for the 08:00 mail, and that mail is then the first of the day.
3. The result is 2 emails of this kind per local day at most. With "Twice a day" the mails go at 08:00 and 17:00 only.
4. Kinds with their own moment (rows 12, 20, 26 to 29) go at that moment and count in the weekly budget.
5. At the last moment before each send, the job reads the row again and stops if one of these is true: seen, read, deleted, hidden, under an open report, access lost, a block, the group switched off, the address suppressed, a pause, quiet hours.
6. One mail holds every waiting item of the account. Items of the same thread merge.
7. An item that I did not see after 24 hours, and that no email carried, goes into "About you" of the next summary.
8. With phone alerts on (slice 10): an alert goes after 2 minutes if I did not see the line. The alert replaces the fast email only. An item that I still did not see goes in the next 08:00 or 17:00 mail.

### 6.3 Level 2 and level 3

1. Level 2: write the row. No email, no alert. The summary and the "Catch up" card use it. The list hides it after 14 days.
2. Level 3: no row. The dot in the navigation comes from the content itself.

### 6.4 Email moments

| Kind                                    | Moment of the email, if I did not see the line |
| --------------------------------------- | ---------------------------------------------- |
| Reply, comment, mention, direct message | By the rhythm of 6.2                           |
| Important announcement                  | The next 08:00 local                           |
| Report, for managers                    | At once                                        |
| Event reminders (after #1388)           | T-24 h and T-1 h                               |
| Event moved or cancelled                | At once                                        |
| Replay, for "Replay only"               | When it is published                           |
| Account (level 0)                       | At once, seen or not                           |

### 6.5 The weekly budget and the other caps

1. The budget: 7 emails per account in any 7 days, for all communities and all kinds together, level 0 excluded.
2. One place of the 7 is kept for the weekly summary.
3. An event that is moved or cancelled for my Yes always goes. It is counted.
4. When the budget is used, level 1 items stay in the app and wait for "About you" of the next summary. Nothing is lost.
5. The code enforces the budget in the one place that sends mail. A test proves it.
6. Other caps per account: 2 emails by the rhythm per local day, 1 summary per period, 1 pop-up per 30 seconds, 1 care mail per day for a manager. Lines in the app have no cap.

### 6.6 Bundling in the list

1. One row per recipient and per thing while I did not read it. A new comment on the same post updates the row (count, last actor, date). It does not add a row.
2. A row that I read is not opened again. A new comment makes a new row.
3. The row points at the thread. The link goes to the first reply that I did not see.

### 6.7 Quiet hours and time zones

1. Default: 22:00 to 08:00 in the time zone of the account. They hold email and phone alerts. The app always works.
2. Exceptions: level 0 mail, an event that is moved or cancelled when the start is within 12 hours, and the reminder of 1 hour for a Yes.
3. Held items go at 08:00 as one mail. If the summary is due in the same hour, it takes them (5.7).
4. The zone comes from the browser at sign-up, and at the first visit for older accounts. With no known zone the platform uses the zone of the instance and the settings say "Set your time zone".
5. When the browser reports another zone than the stored one, the app asks one time per new zone: "Show times in <zone>? Yes, or keep <old zone>". The member decides. The platform never changes the zone alone.

### 6.8 When to stay silent, and the access rule

1. No notification for the member's own action, for a muted space, thread or community, for a level 3 event, or for an edit.
2. Access is checked four times: when the row is written, when the list and the count are read, at the last moment before an email or an alert, and when the summary is built.
3. A member who cannot view the item gets nothing, and an existing row is deleted. This covers a member who left an access group, who left the community or was removed, and a space that changed its visibility.
4. Rows are also pruned when access changes. The hooks `AccessGroupsChanged` and `CommunityMembersChanged` exist.
5. The count is computed on the server after these checks. A count that holds a row that I cannot read is a leak.
6. A restricted space never gives a title or a text to a subject line (5.6). The body can hold the text, because the check at the last moment passed.
7. The counts that a manager sees before a broadcast hold only members who can see the space (7.1).
8. Deleted, hidden or reported content is not sent. A mail that already left cannot be called back. The policy says so (8.5). The delay of 10 minutes is the window for a deletion or a report.
9. The mention picker of a restricted space lists only members who can see the space. A name of another member stays plain text. The author is never told that a member muted the space, lost access or has a bad address.
10. No group mention (`@everyone`) exists. If one is added later, it uses the "important" budget of 7.1.

### 6.9 Blocks, per kind

1. A member who blocked another member (ADR 0028) gets no notification of any kind that this person causes: no comment line, no reply line, no mention, no message.
2. A grouped line ("Anna and 3 others") never names and never counts a blocked person.
3. The summary never shows content of a blocked author under "About you" or "New".
4. The block is checked when the row is written and at the last moment before a send. A block that comes after the row deletes the row.
5. The blocked person is never told.
6. Direct messages follow ADR 0028: no new message can be sent in either direction, so no notification exists.

### 6.10 Storms

1. A busy chat: 40 messages in 5 minutes give no line to members at "Only what is for me". At "Everything" the space has one line, which is updated.
2. Mentions: 3 lines per sender and per member in one hour at most. More fold into "Marc mentioned you 4 times".
3. Imports and bulk actions run inside `Notify::silenced(fn)`. A second guard skips person kinds when the item is older than 15 minutes at the moment of the event.
4. The return line of the move (row 24) is built by its own command after the import. It is the only notification about imported history.

### 6.11 A manager who edits a post five times

1. A new announcement or post waits 5 minutes before the fan-out. "Send now" skips the wait. Edits inside the wait change the content and tell nobody.
2. After the fan-out an edit tells nobody. A change of the time of an event is the only edit that notifies.
3. A deleted post removes its rows (cascade).

### 6.12 Reading on one device

1. The server sets `seen_at` or `read_at` and sends one event to the whole `user.{cuid}` channel, the origin tab included.
2. The event holds the identifier of the notification and of the community, nothing else. The client then loads the count and the line through the API.
3. When the connection comes back, the client loads the list again without a message. A line that has the focus is never removed under it. It stays, marked "Done on another device", until the focus leaves.

### 6.13 Failure rules for email

1. The Resend webhook (signed, checked, rate limited) reports bounces and complaints.
2. A hard bounce or a complaint sets `email_suppressed_at` on the account and stops all email except level 0. The settings page says why.
3. A soft bounce suppresses nothing. Three soft bounces in a row count as a hard bounce.
4. A bounce of an invitation is stored on the invitation. It never suppresses an account.
5. The unsubscribe route takes a POST without a session, with a signed token. A GET shows a page with one confirm button and changes nothing.
6. The existing mails (sign-in link, invitation, ownership) use the same transport. They are level 0 and are never held.
7. Without live updates, without the scheduler or without a mail provider (self-hosted): the bell works on page load, and nothing breaks.

### 6.14 Technical shape (for the ADR of slice 1)

1. Table `notifications`, one row per recipient: `user_id` (cascade), `community_id` (cascade), `space_id` (scope for the access check, nullable, cascade), `kind` (enum), `actor_id` (null on delete, shown as "Deleted member"), `actor_count`, `dedupe_key`, `seen_at`, `read_at`, timestamps.
2. The subject follows ADR 0014: one nullable foreign key per kind of item (`post_id`, `comment_id`, `chat_message_id` in slice 1, a later kind adds its column), each with cascade on delete, and a check that exactly one is set. No polymorphic columns. `dedupe_key` is only a text key for bundling and idempotency, with a unique index on `(user_id, dedupe_key)`.
3. No text is stored in a row. Titles and excerpts are built from the live content at read time and at send time.
4. A partial index on `(user_id)` where `seen_at` is null serves the count.
5. One action, `Notify`, behind a contract. Callers never write rows. The fan-out reads recipients in chunks, inserts in batches, and checks access per chunk with the memberships and groups loaded one time.
6. Events carry identifiers only. A fan-out sends its events as queued jobs, never in a loop inside the request.
7. Queues, named in the first pull request that needs them: `broadcasts` (live updates), `notifications` (fan-out), `default`, and from slice 4 `mail` with a rate limiter as job middleware. Slice 1 changes the worker command to read `broadcasts,notifications,default` in this order. Slice 4 adds a second worker for `mail`. Both change `docker/8.5/supervisord.conf`, `SELF_HOSTING.md` and `.env.example`.
8. Delayed mail jobs read the state again when they run. A unique index on `(notification_id, channel)` in the delivery log makes a retry harmless.
9. The summary is built from one query per community for the content, then filtered per member. It runs in batches by time zone, with one unique row per account and period.
10. API: abilities `notifications:read` and `notifications:write`, not in the default set. A Bruno request, the OpenAPI document and Pest tests for every route. The unsubscribe route and the webhook route have no ability, and the ability test lists them by name.
11. Leaving a community, a removal and the deletion of an account delete the rows, the follows, the preferences, the delivery log and the devices of that member.
12. Mail volume, as an estimate to check against the plan of the mail provider: about 3 500 emails a month for 547 members at the expected use, and about 16 000 in the worst case that the budget allows. An alert fires at 80% of the monthly quota.

## 7. What managers control, and what they cannot

### 7.1 Managers can

1. Set the default level of a space for members who did not choose.
2. Mark an announcement "important" (owner and admins). 2 a month per community. The screen shows "1 of 2 used this month". A third one is refused until the next month.
3. See real counts before a broadcast (4.E). The counts hold only members who can see the space, and never names.
4. Add one signed intro line to the weekly summary.
5. Turn the care mail on or off for themselves.

### 7.2 Managers can never

1. Send an email to a member who turned that group off.
2. Turn on phone alerts for a member.
3. Change the quiet hours, the time zone, the rhythm or the pause of a member.
4. Break a mute.
5. Know who saw a message, who opened an email, who muted what, or whose address bounced.
6. Put promotion in an important announcement or in the summary (decision 5).

### 7.3 The Care list: what needs a human

1. A view for the owner, admins and moderators. It has "To welcome" (a first post of a new member with no reply from another person after 24 hours) and "Reports" (#1394). After tags exist it also has "To answer" (posts with the tag "Question" and no reply after 12 hours).
2. One action per line. "Welcome" opens the post with an empty reply box. Nothing is posted automatically.
3. One care mail per day at 09:00 local, to the owner and to the managers who turned it on, only when the list is not empty.
4. Cut from the draft: the duty rotation, the claim of 4 hours, the weekly health mail and the list of paid members with no visit. One owner and about 30 writers do not need them, and the last one needs data about the visits of members that the owner did not approve.

## 8. Trust and wellbeing

### 8.1 Reasons and control

1. Every email shows its reason. Every line in the app has "Why did I get this?" in its menu.
2. When a member did not open the lines of a followed thread for 14 days, the list offers one time: "You did not open replies in <thread> for 2 weeks. Stop following?" After "Keep", the offer never comes back for that thread.

### 8.2 No guilt

1. The bell counts new lines only and forgets them after 14 days.
2. No total exists for levels 2 and 3.
3. No mail speaks about absence. The summary slows down and pauses without a message.
4. No count of people is used to move a member ("5 people said yes").

### 8.3 Honest email

1. The subject and the first line say what is inside: the real text, no teaser, no false urgency, no promotion.
2. No email offers a reply address that goes nowhere. The mail says "Open the thread to answer."
3. The stop link names what it stops, and the page after it says what still arrives.

### 8.4 Quality rule for the "New" block of the summary

1. A thread enters only when it has replies from 2 different people, in a space that the member follows or wrote in during the last 30 days.
2. The order is the date of the last reply, not the number of likes.
3. Announcements and events have their own blocks and do not pass through this rule.

### 8.5 Privacy: what is stored and for how long

Each row below needs an entry in `config/retention.php`, a step in `retention:prune` with a test, an update of `RetentionScheduleTest`, a placeholder in the policy, a line in the register of processing and in the member export. The policy text changes only after the owner approves the words.

| Data                                                                     | Why                                                       | Kept (proposal)                                                                   |
| ------------------------------------------------------------------------ | --------------------------------------------------------- | --------------------------------------------------------------------------------- |
| Notification rows (who is told about what, by whom, seen and read dates) | The list and the count                                    | 60 days after reading. 90 days when never read                                    |
| Follows, mutes, space levels, presets and email choices                  | To respect the choices                                    | While the member is in the community. The account choices while the account lives |
| `users.time_zone`                                                        | Quiet hours, the hour of the summary, the times in emails | While the account lives. Never shown to other members by this feature             |
| `users.last_visit_on` (a date, no hour)                                  | The "Catch up" card and the slower summary                | Replaced at each visit. Never shown to anyone, managers included                  |
| Delivery log (kind, channel, status, date, no text)                      | To avoid a second send and to trace a bounce              | 30 days                                                                           |
| Summary runs                                                             | To avoid a second send                                    | 90 days                                                                           |
| Suppression flag and its reason                                          | To stop mail to a bad address                             | While the address stays                                                           |
| Email pause date                                                         | To respect the pause                                      | Until the pause ends                                                              |
| Devices for phone alerts (slice 10)                                      | To send alerts                                            | 90 days without use, or until the member removes the device                       |

1. Not stored: the text of a notification, email opens, clicks per member, location, attendance.
2. A sent email holds text of members. It stays at the mail provider for its own retention time and in the mailbox of the recipient. The platform cannot call it back. The policy must say so.
3. Third parties: the mail provider (already listed). The alert services of the browsers join the list with slice 10, with the notice of 14 days.

## 9. Accessibility

### 9.1 The panel

1. The bell is a button with `aria-expanded` and `aria-controls`. On a desktop it opens a panel that is not modal. At 360 px it opens the `Drawer` component. Both give the focus back to the bell (`useReturnFocus`).
2. On opening, the focus goes to the heading of the panel. Tab leaves the desktop panel. Escape and a click outside close it.
3. The list is a real list. A line is a list item with a link and real buttons. Button names hold the line: "Done: Anna replied to Notes workflow".
4. When a line leaves (Done, Stop following, I am caught up), the focus goes to the main control of the next line, else of the line before, else to the heading. The buttons never use `disabled` or `loading`. They use `aria-disabled` or `aria-busy` with a guard.
5. "Load older" puts the focus on the first new line and says "20 more loaded". The list has `aria-busy` while it loads. On an error the focus goes to "Retry".
6. A line is never removed under the focus (6.12).
7. The address `/notifications` opens the full page with the same rules.

### 9.2 The bell name and the badge

1. The name is "Notifications, 3 new", "Notifications, 9 or more new", or "Notifications".
2. The badge is hidden from assistive technology, because the name holds the number. It has a border and a contrast of 3:1 against the bell and against the page, in both themes.

### 9.3 Live regions

1. One polite region per page exists from the load. It holds level 0 and level 1 arrivals only, once per 30 seconds at most, with the actor and the verb: "Ben replied to your post". Several in the window become "3 new for you".
2. It never announces level 2, chat messages, a change of a count, or a reconnection. Nothing uses `assertive`, except `role="alert"` for an error after an action of the member.
3. Numbers and dots in the navigation are never inside a live region. Their label is static text that is read on focus: "Open discussion, new posts".

### 9.4 Pop-ups

1. The rules of 5.3 apply. The text of the pop-up goes through the one polite region.
2. The pop-up does not cover the composer or the bottom navigation at 360 px.

### 9.5 The "New" state and the dots

1. "New" is a bold sentence plus the word "New". Color alone never carries it.
2. A dot has a contrast of 3:1 in both themes, is drawn with a border or `currentColor` so that forced colors keep it, and has a text alternative.
3. A muted space shows no dot, so no low-contrast marker exists.

### 9.6 Settings

1. The presets are a `radiogroup`. Each description is tied to its radio with `aria-describedby`.
2. "Your spaces": one `fieldset` with a `legend` per space group. Each choice has the label "Notifications for <space name>". After "Same for all spaces of this group", the page says "Applied to 6 spaces".
3. Quiet hours: two labelled time fields, "Quiet hours start" and "Quiet hours end", in one `fieldset`. The result is written as text: "No email from 22:00 to 08:00, Paris time".
4. One column at 360 px, the label above the control, targets of 44 px, no sideways scrolling, usable at 400% zoom.
5. "Saved" goes through a status region. An error is `role="alert"` next to the control, and the focus stays on the control.
6. The preview is its own region. It is announced only after an action of the member.
7. A choice that is not available (bounced address, account mail) uses `aria-disabled` and shows its reason as text, not as a tooltip.

### 9.7 Cards

1. The "Catch up" card, the phone alert card and the "Stop following?" offer are regions in the page. They are not dialogs.
2. When a card is closed, the focus goes to the heading of the list. After "Turn on alerts", the result ("Alerts on" or "Blocked") is status text.

### 9.8 Motion and sound

1. With `prefers-reduced-motion`, nothing slides or shimmers on any notification surface. The jump to a reply is instant.
2. After the jump the focus is on the reply, and the reply has a visible outline that stays until the focus leaves.
3. No sound, ever, in the first version.

### 9.9 Times and languages

1. A relative time ("2 h ago") is a `time` element with the full date in its accessible text.
2. A zone is written in words the first time ("Central European Time").
3. An excerpt in another language than the page carries a `lang` attribute when the language is known.

### 9.10 Emails

1. `lang` and `dir` on the root element. Real headings for the blocks of the summary. Layout tables have `role="presentation"`.
2. Every link names its target ("Read the reply from Ben"). No "Click here", no "[Open]" alone.
3. Text of 16 px or more, a contrast of 4.5:1, `color-scheme` declared for dark mode, no pure black on pure white. Tested in the dark mode of Gmail, Apple Mail and Outlook.
4. Avatars have an empty `alt`. The name of the author is text. No information is only in an image.
5. Footer links are a list, each one a target of 24 px or more, before the postal address.
6. The plain-text part has the same order, with each address on its own line after a label.
7. The page after "Stop emails like this" works with the keyboard and a screen reader, confirms in text, and has "Undo".
8. A calendar file has a name, and the date and time are also in the body.

### 9.11 Release test, part of the definition of done of each slice

1. Automated: axe in the Vitest tests of the panel, the settings and the pop-up.
2. Keyboard: a written script for 9.1 (open, move, Done, focus after removal, Load older, close).
3. Screen readers: NVDA with Firefox or Chrome on a desktop, and VoiceOver on a phone.
4. Display: 360 px and 1920 px, 400% zoom, both themes, forced colors.
5. Email: each mail read in plain text and in the three dark modes of 9.10.
6. Pass: no axe violation, every step of the script works without a pointer, and every announcement of 9.3 is heard once.

## 10. Measures of success without tracking pixels

### 10.1 What the platform counts

1. Totals per kind and per week: lines written, lines opened, "Done", "Stop following", mutes, visits that start from an email (a `ref` value in the address that names the kind, not the member).
2. Deliveries by status, and changes of settings (preset, summary, stop links, pauses), as totals.
3. Care measures: time to the first reply, share of new members with a reply within 24 hours.
4. Not counted: email opens, who saw what, time on a page, any score per member.

### 10.2 Target values (to set again after 8 weeks)

1. New members with a reply from a person within 24 hours: 90% or more.
2. A visit within 24 hours after a level 1 email: 40% or more.
3. Summary: a visit after 15% to 25% of the sends, and "Stop the weekly summary" under 0.5% per send.
4. Members seen in a month: from 137 toward 200 in two quarters.
5. Members who come back within 14 days after the invitation of the move or after a reply to an old post: measured as a total from day one, target set after the first month.

### 10.3 Warning signs

1. More than 3 emails per week for the median active member.
2. The Quiet preset above 50% of members, or stop links above 1% per send.
3. A kind with an open rate of lines under 2% for 4 weeks: remove it or lower its level.
4. Spam complaints above 0.3%.

## 11. Build slices

Each slice is one or more issues. The accessibility test of 9.11 belongs to each slice.

1. Slice 1, "I see when someone answers me". The ADR (levels, one source of truth per thing, the table shape of 6.14). The table, the `Notify` action, kinds 1 to 3, the access and block checks, the bell with its number, the panel with "Needs you", open and "Done", the seen rule, the event with identifiers only, the API with its abilities, retention, export and deletion, the worker command. In the app only. No email, no time zone, no pop-up. Issue: #1392 (first part). The policy line for the new data needs the owner's approval of the words.
2. Slice 2, "I choose what I follow". Follows and the three space levels, the defaults per space kind, Follow, Stop following and Mute, the "Following" heading with kinds 4 to 9 (announcements included), "I am caught up", the dots in the navigation, the reason menu, settings with the presets and "Your spaces". Issues: #1392 (rest), #841 (part), #1435.
3. Slice 3, "It knows where I am". `users.time_zone`, `users.last_visit_on`, the "Catch up" card, the tab title, the pop-up, the page `/notifications` with "Earlier". Issue: #1423 (part).
4. Slice 4, "I learn by email when I am away". First pull request: the `mail` queue, the rate limiter, the second worker. Then the webhook and the suppression, the stop route and its page, the ladder with the check at the last moment, the rhythm, quiet hours, the weekly budget, the pause, direct messages (kind 11), important announcements (kind 12) with their budget and the counts before a broadcast, the email settings, the policy text per kind of email. Issues: #1393, #841 (rest). It needs decisions 1, 3, 5 and 7.
5. Slice 5, "A summary that is about me". The builder, the runs, the send rule with the announcement promise, the short form and the pause for absent members, the welcome mail and the first choice. Issue: #191. It needs decisions 2, 4 and 6.
6. Slice 6, "Account and care". Level 0 kinds (15 to 19, 21), reports for managers (20, with #1394), invitation accepted, the Care list and the care mail. It needs decision 16.
7. Slice 7, "The move". The invitation mail, the carried choices, the return line, with the import (#1396). It needs decision 15.
8. Slice 8, "Events people answer". After #1388 exists, with its own ADR: the three answers, kinds 25 to 29.
9. Slice 9, "Questions". After tags exist (#1389): kind 30 and "To answer".
10. Slice 10, "The app on my phone". Manifest, service worker, phone alerts with the soft ask, devices, sub-processors. Issue: #115. It needs decision 14.
11. Slice 11, "Reply from my mail". Not planned (decision 13). It needs an inbound mail domain.

## 12. Decisions of the owner (2026-11-15)

1. Legal basis per kind of email. Account mail (security, role, access, ownership, result of a report): contract and legal duty, no stop. Welcome, one time: contract. A person wrote to me (reply, mention, direct message): legitimate interest, one-click stop. Things I said yes to (event reminders, a replay I asked for): contract, stopped by removing the yes. Weekly summary: legitimate interest, on by default, one-click stop. Important announcement: legitimate interest, on by default, one-click stop. Invitation to the platform: legitimate interest for members, contract for buyers, one reminder at most. Care mail and reports for managers: contract, the care mail can be stopped. A check by counsel comes before slice 4.
2. The first choice: "What is for me, plus a weekly summary" is selected and "Continue" is active at once.
3. Email when the member is away: the first email of the day after 10 minutes, the rest together at 17:00 and 08:00, 2 such emails a day at most, 7 emails in 7 days per account.
4. Weekly summary: Monday 08:00 local, 5 items at most, one email per account for all communities.
5. Announcements: one announcement alone sends the summary. "Important" announcements go by email to members who did not see them, 2 a month, a third one is refused. No promotion in an important announcement or in the summary.
6. Members who never come: the summary goes every second week after 30 days without a visit and pauses without a message after 180 days. No mail speaks about absence.
7. `users.time_zone`: yes, with slice 3.
8. `users.last_visit_on` (one date, no hour, shown to nobody): yes, with slice 3.
9. Retention: the periods of 8.5 are accepted. The policy words are proposed to the owner before the text changes.
10. Four product rules: the bell number means "new"; mute is full, a mention included; direct messages stay in "Messages"; chat spaces show a dot, and a number only at "Everything".
11. A link in a notification email opens the thread and asks for a sign-in when the member is signed out. It never signs the member in.
12. The owner and the admins get "Only what is for me" in restricted spaces that they see only because of their role.
13. Reply by email: not now (slice 11 stays an idea).
14. Phone alerts: later, with the installable app (#115), as slice 10.
15. The invitation to the platform: members and buyers who never joined, never a person who left; one invitation by name and one reminder after 7 days; a known choice moves with the member; with no known choice, members not seen for 90 days or more start with "Only what is for me"; the imported history sends no notification, only one return line.
16. Managers get the Care list ("To welcome", "Reports") and one mail a day at 09:00 only when the list is not empty. No duty rotation, no claims, no weekly health mail.

## 13. Check against the three voices

1. The member who left: "Never send a notification that I cannot trace to a person or a thing I chose." Answer: the reason on every item, level 3 sends nothing, no mail about absence, and the journey 4.I.
2. The overwhelmed member: "A bell that shows 37 is a reason to leave." Answer: the number counts new lines of 14 days at most and goes away when seen. The budget of 7. Three words in place of four. Five lines in the catch-up and in the summary.
3. The community builder: "Alert fatigue is the real risk." Answer: one care mail a day, only when the list is not empty. No rotation, no claims, no weekly report. The "important" budget of 2.

## 14. Rejected points and ideas for later

1. Announcements in slice 1 (hostile reader). Rejected: slice 1 stays the smallest thing that a member can feel. Announcements come in slice 2.
2. A hold for new or reported authors (hostile reader). Rejected: the delay of 10 minutes and the check at the last moment cover it, and a rule per author judges members.
3. Telling the author that a mentioned member will not be told (hostile reader). Rejected: it shows the access, the mute or the address state of another member.
4. One bell per community (hostile reader). Rejected: the header is global. One bell with a filter is one number less.
5. "Twice a day" as the default email rhythm (overwhelmed member). In part: the default has the same maximum of 2 a day, and the first mail is fast, because the member who left asked for a fast first reply.
6. A silent stop of the summary at 60 days (overwhelmed member) and a monthly rhythm after 30 days. In part: every second week after 30 days and a pause at 180 days, because an announcement must reach members who do not visit.
7. A link in the email that opens the reply box without a sign-in (member who left). Rejected: such a link gives the account to anyone who gets the mail and skips two-factor sign-in.
8. Reply by email before the summary (member who left). Rejected: it needs an inbound mail domain and checks against forged senders. It stays slice 11.
9. A topic list at the first choice (member who left). Rejected for now: one more question on day one and a new kind of personal data. Idea for later.
10. Names of known people on an event (member who left). Later, with the event design (#1388): it needs a record of who talked with whom.
11. A send day spread by a hash (member who left). Rejected: a member cannot predict it. The summary takes the bundle of the same hour, which removes the pile.
12. A key to jump to the pop-up (accessibility). Rejected: the pop-up holds nothing that the panel does not hold.
13. A setting to hide the alert text on a locked screen (accessibility). Not needed: the alert never holds the text.
14. The count in the event payload (feasibility). Not used: identifiers only, the client loads the count.
15. The time zone column in slice 1 (feasibility). Moved to slice 3: slice 1 sends nothing at a set hour.
