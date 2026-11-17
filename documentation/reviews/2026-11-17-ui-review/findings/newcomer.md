# Findings: newcomer

## Scope covered

- Eyes: a person who never used the product, not technical, who arrives from a link, often on a phone.
- Screens (pictures of the atlas): public home page (visitor); public page of a community (visitor, new-member); public discussion space, public pages space, page of a public pages space, post of a public space (visitor, new-member); members-only space and private community as a visitor; sign-up (empty, errors), sign-in (empty, wrong password, link sent), verify your email address; dashboard of new-member, new community dialog, user menu, main menu; personal community and its settings (new-member); notification settings and notifications page of new-member; account settings of new-member; time zone question; "Atlas Empty": community home (member, owner), empty discussion, chat, resources, course, messages; forbidden screens of new-member; as `member`: post without comments, post with the reply form, composer, chat space, about page, profile of another member.
- Widths and themes: 1920 and 360 light for nearly all; 360 dark for the personal community (same result as light). Dark mode not checked in detail: another reviewer covers it.
- Code read for the texts: `Pages/Welcome.tsx`, `Pages/Auth/Register.tsx`, `Pages/Auth/Login.tsx`, `Pages/Auth/VerifyEmail.tsx`, `Pages/Dashboard.tsx`, `Pages/Communities/Public.tsx`, `Pages/Communities/Show.tsx`, `Pages/Error.tsx`, `Components/Communities/CreateCommunityDialog.tsx`, `Components/Communities/JoinCommunity.tsx`, `Components/Spaces/SpaceNavigation.tsx`, `Components/Posts/PostComposer.tsx`, `Components/Comments/CommentSection.tsx`, `Components/Comments/CommentForm.tsx`, `Components/Chat/ChatComposer.tsx`, `Components/TimeZone/TimeZoneAsk.tsx`, `Components/ui/MarkdownEditor.tsx`, `Pages/Profile/Partials/UpdateProfileInformationForm.tsx`, `libs/common/src/lib/types/community.schema.ts`, `app/Actions/Fortify/*`.
- Not looked at: the browser (my brief does not allow it), so no flow after a real sign-up (email, return to the community, first sign-in); the composer dialog at 360 px at full size (the picture is too long to read); courses, quizzes, resources pages, settings of a shared community beyond the first look; screen readers and keyboard (accessibility reviewer).

## What works

1. Public page of a community (`public/community--full--visitor`): the name, a one-line description and two clear buttons, "Sign in" and "Sign up to join". A visitor knows where they are.
2. Public discussion space (`public/space-discussion--full--visitor`): the line "News from the team. Everybody can read, the team writes." and the chips "Public" and "Only moderators can post" tell a visitor who reads and who writes.
3. Page of a public pages space at 360 px (`public/page--full--visitor--360`): "Page 1 of 4", a clear title and a large "Next" card. Easy to read on a phone.
4. Sign-in by link (`Pages/Auth/Login.tsx:65`, `auth/login-link--sent--visitor`): "It works once, for 15 minutes" in plain words; the message after sending does not tell a stranger whether an account exists.
5. Sign-up: "I am 16 or older and I agree to the Terms of Service and Privacy Policy" is one plain checkbox, and each error shows under its field (`auth/register--errors--visitor--360`).
6. Empty discussion space (`discussion/space--empty--member`): "No posts yet. Start the conversation: write the first post." with the "New post" button next to it. This is what an empty screen must do.
7. Empty resources space (`resources/space--empty--member`): "Share the first useful article with the community." and "Add a resource".
8. Empty chat (`chat/space--empty--member--360`): "No messages yet. Write the first message." and the field "Message Empty chat" right below it.
9. Notifications page of new-member (`notifications/page--empty--new-member--360`): "Nothing needs you. Everything is quiet. That is fine." A calm, clear empty state.
10. Notification settings (`notifications/settings--new--new-member`): "Times show in Central European Standard Time (Europe/Brussels)" and "Only you can see it and change it."
11. Account settings (`account/profile--full--new-member`): each of Bio, Location, Phone has its own "Visible to the members of my communities" checkbox; phone is off by default; "Delete Account" says what stays and what goes.
12. About page of a community (`community/about--full--member`): "Team", "What moderators can do" and "What you can do" answer "who can do what here" in one screen.
13. Profile of another member (`community/member-profile--other--member`): the email address is not shown; "Send a message" and "Block" are both visible.
14. At 360 px, no screen I opened scrolls sideways; long breadcrumbs fold to "…"; the spaces go into a "Spaces" button.

## Findings

### N1 A link to a members-only space or to a private community ends on a bare "403: Forbidden" page

- Weight: must
- Screen: Space of a community, not a member (`/community/atlas-garden/space/garden-talk`), new-member, 1920 and 360, light; Community of other people (`/community/atlas-empty`), new-member, 360, light
- What is wrong: a signed-in person who opens a shared link to a members-only space of an open community gets an error page, not the community. Outside `local` the page is `Pages/Error.tsx`: "403: Forbidden", "Sorry, you are forbidden from accessing this page. ⛔" and one button "Go back to the homepage", which goes to the "Coming soon" page. No "Join Atlas Garden", no link to the public page of the community, no "This community is by invitation: ask the person who sent you the link".
- Why it matters: a link from a friend is the most common way in. The newcomer is one click from joining (the community is open) and the product shows a stop sign and an accusation instead. Many people stop here.
- Proposed fix: for a space or a community the person cannot read, render a dedicated page (not `Error.tsx`) with the name of the community, the reason in one line ("This space is for the members of Atlas Garden"), and the next step: `JoinCommunity` when `join_policy` is `open`, else "This community is by invitation. Ask a member for an invitation." and a link to its public page. Keep the 403 status.
- Evidence: `discussion/space--forbidden--new-member--1920--light.png`, `errors/forbidden--full--new-member--360--light.png`, `Pages/Error.tsx:24-29`, `Pages/Error.tsx:52`, `app/Http/Controllers/Inertia/SpaceController.php:40`, `bootstrap/app.php:307-308`
- Seen in: picture (local error page) and code (production page)
- Confidence: high

### N2 The account settings say that almost all profile data is public to everybody, which is not true

- Weight: must
- Screen: Account settings, every section (`/user/profile`), new-member, 1920, light
- What is wrong: the text beside "Profile Information" says "Note that Apart from your username, all the information will be publicly visible to other Knowii users." The same form has the checkboxes "Visible to the members of my communities" (phone is off), the profile of another member shows no email address, and the username is exactly the one field that IS shown on public pages ("@atlas-moderator" on the public community page). The sentence also has a capital "Apart" in the middle.
- Why it matters: a careful newcomer reads that their email address and phone are public to all users. They do not fill in the profile, or they leave. The text is wrong in both directions: it hides that the username and the name show to visitors on public spaces.
- Proposed fix: replace the text with what is true, for example: "Your name, user name and picture show next to what you write. In a public space, visitors see them too. Your email address is never shown. Bio, location and phone: you choose below."
- Evidence: `account/profile--full--new-member--1920--light.png`, `Pages/Profile/Partials/UpdateProfileInformationForm.tsx:204`, `community/member-profile--other--member--1920--light.png`, `public/community--full--visitor--1920--light.png`
- Seen in: picture and code
- Confidence: high

### N3 The home page says "Coming soon" and "Join the waitlist", but "Register" works

- Weight: should
- Screen: Home page (`/`), visitor, 1920 and 360, light
- What is wrong: the title is "Coming soon 👋", the main card is "Join the waitlist", and at the top a pink "Register" button opens a working sign-up. The page does not say what Knowii does beyond "a place for your community's Knowledge, Ideas and Inspiration" and "a revolutionary knowledge-sharing experience".
- Why it matters: a newcomer who arrives from a community link and presses the logo lands here. "Coming soon" tells them the product is not ready, so the community they just saw looks like a test. Two opposite main actions (wait vs. register) on one screen confuse.
- Proposed fix: when registration is open, drop "Coming soon" and the waitlist, and say in two lines what a person can do (join a community, read, ask, learn). If the product is truly not open, hide "Register". `Pages/Welcome.tsx`.
- Evidence: `public/home--full--visitor--1920--light.png`, `public/home--full--visitor--360--light.png`, `Pages/Welcome.tsx:43-58`
- Seen in: picture and code
- Confidence: high

### N4 Two sets of words for the same two actions: "Log in / Register" and "Sign in / Sign up"

- Weight: should
- Screen: Home page (`/`), Public page of a community, Sign-in page, Sign-up page; visitor; all widths
- What is wrong: the home page header says "Log in" and "Register"; the community pages say "Sign in", "Sign up" and "Sign up to join"; the sign-in page button says "Log in" under a link "Email me a sign-in link instead"; the sign-up button says "Register" and its link says "Already registered?".
- Why it matters: a newcomer presses "Sign up to join" and lands on a page whose button is "Register": they wonder if they are on the right page. Small, but it is on the first path of every person.
- Proposed fix: one pair everywhere, for example "Sign in" and "Create an account". `Components/PageHeader.tsx:91,96`, `Layouts/AppLayout.tsx:107,109`, `Pages/Auth/Login.tsx:164`, `Pages/Auth/Register.tsx:208,218`.
- Evidence: `public/home--full--visitor--1920--light.png`, `public/community--full--visitor--1920--light.png`, `auth/login--empty--visitor` (via `public/community-private--sign-in--visitor--1920--light.png`), `auth/register--empty--visitor--1920--light.png`
- Seen in: picture and code
- Confidence: high

### N5 A visitor with a link to a members-only space gets the sign-in form with no reason

- Weight: should
- Screen: Space for members only, as a visitor (`/community/atlas-garden/space/garden-talk`), visitor, 360 and 1920, light; Private community, as a visitor (`/community/atlas-empty`)
- What is wrong: the page is the plain sign-in form: logo, Email, Password, "Log in". No line says why ("Garden talk is for the members of Atlas Garden. Sign in, or create an account to join."), no name of the community, no way back to its public page.
- Why it matters: a visitor who clicked a link from a friend does not know if they have an account, or if they need one. "Need an account?" in small text is the only clue.
- Proposed fix: pass the intended page to the sign-in page and show one line above the form with the community name and the reason, plus "Create an account" as a visible button. `Pages/Auth/Login.tsx` (the `status` slot exists at line 53).
- Evidence: `public/space-members-only--sign-in--visitor--360--light.png`, `public/community-private--sign-in--visitor--1920--light.png`
- Seen in: picture
- Confidence: high

### N6 The sign-in and sign-up pages have no title, and the sign-up page forgets the community

- Weight: should
- Screen: Sign-up page (`/register`), Sign-in page (`/login`), visitor, 1920 and 360, light
- What is wrong: both pages show only the logo and the fields. There is no "Create your account" or "Sign in to Knowii" heading. A person who pressed "Sign up to join" on Atlas Garden sees nothing about Atlas Garden on the sign-up page.
- Why it matters: the two forms look almost the same; the only visible difference is the field list. The newcomer loses the thread "I am joining the garden community".
- Proposed fix: give `AuthenticationCard` a `title` (and an optional line under it); on sign-up from a community, show "Create an account to join Atlas Garden".
- Evidence: `auth/register--empty--visitor--1920--light.png`, `public/community-private--sign-in--visitor--1920--light.png`, `Components/AuthenticationCard.tsx`, `Pages/Auth/Register.tsx:68-70`
- Seen in: picture and code
- Confidence: high

### N7 The sign-up form does not say the password rule or that the name is shown to others

- Weight: should
- Screen: Sign-up page and Sign-up page, sent empty (`/register`), visitor, 360, light
- What is wrong: the server asks for at least 8 characters (`Password::default()`), but the form says nothing before an error. The "Name" field does not say that it shows next to everything the person writes, also to visitors in public spaces. The server message "The terms field must be accepted." uses the word "field" and a name that is not on the screen.
- Why it matters: a newcomer finds the rule only by failing. A person who types a full legal name does not know it will be on public pages.
- Proposed fix: a hint under "Password": "At least 8 characters." A hint under "Name": "Shown next to what you write." Message for the checkbox: "Tick the box to agree to the terms." `Pages/Auth/Register.tsx:74`, `Pages/Auth/Register.tsx:111`, the validation messages in `lang/`.
- Evidence: `auth/register--errors--visitor--360--light.png`, `app/Actions/Fortify/PasswordValidationRules.php:16`
- Seen in: picture and code
- Confidence: high

### N8 The verify-your-address page does not show the address and gives no help if the email does not come

- Weight: should
- Screen: Verify your email address (`/email/verify`), new-member (unverified account), 360, light
- What is wrong: the text is "Before continuing, could you verify your email address by clicking on the link we just emailed to you? If you didn't receive the email, we will gladly send you another." It does not show which address, does not say to look in the spam folder, and the way to fix a wrong address is a link "Edit profile". No heading.
- Why it matters: a typo in the email address at sign-up is common. The newcomer waits for an email that never comes and does not know why.
- Proposed fix: heading "Check your email"; "We sent a link to nina@…. Open it to continue. Not there? Look in the spam folder, or send it again." and "Wrong address? Change it" instead of "Edit profile". `Pages/Auth/VerifyEmail.tsx:27-50`.
- Evidence: `auth/verify-email--full--new-member--360--light.png`, `Pages/Auth/VerifyEmail.tsx:30`
- Seen in: picture and code
- Confidence: high

### N9 The first dashboard has no welcome and no way to find a community

- Weight: should
- Screen: Dashboard (`/dashboard`), new-member, 1920 and 360, light
- What is wrong: the page shows "My Communities", a search field, a "Filter by visibility" list, "Clear filters", one card "Nina Newcomer's Space / Nina Newcomer's Space / Personal" and a "New community" card. Nothing says what a personal space is for, nothing says how to find or join a community. The only big action is to create a community.
- Why it matters: most newcomers came to join a community, not to start one. They see an empty personal space and a button to build something. Search and filter for one card add noise, and at 360 px they push the cards down.
- Proposed fix: for a person with only the personal community, a short welcome card: what the personal space is ("Only you. A place for your own notes and resources"), and "Have a link to a community? Open it to join." Hide search and filter under 4 communities. `Pages/Dashboard.tsx:99-139`.
- Evidence: `dashboard/home--full--new-member--1920--light.png`, `dashboard/home--full--new-member--360--light.png`
- Seen in: picture and code
- Confidence: high

### N10 The new community dialog asks "Personal, Private or Public" without saying what they mean, and picks "Public"

- Weight: should
- Screen: New community dialog (`/dashboard`), new-member, 1920 and 360, light
- What is wrong: "Community visibility" is a three-part switch with "Public" selected by default and no text. The product has a text for each choice (`COMMUNITY_VISIBILITY_DESCRIPTIONS`), shown only later in the settings and on the About page. There, "Private" means only "Not shown in a list of communities" and "Public" means "For a list of public communities, which comes later": the content is open or closed per space, not per community. The dialog also says "Provide the details of your new community.", the button is "Go ahead!", and the legal line "you accept the data processing agreement" has no plain word on why.
- Why it matters: a person who picks "Private" thinks that nothing can be read from outside; a person who leaves "Public" thinks that all will be on the web. Both are wrong. That is a trust problem on the first thing a new owner does.
- Proposed fix: show the description of the selected choice under the switch, rewritten for a newcomer (for example "Private: people find it only through an invitation or a link. Each space chooses who can read it."). Button "Create the community". One plain line before the DPA link: "You will hold the data of your members; this agreement says how." `Components/Communities/CreateCommunityDialog.tsx:46,96,103,161-190`, `libs/common/src/lib/types/community.schema.ts:40-43`.
- Evidence: `dashboard/new-community--dialog--new-member--1920--light.png`, `dashboard/new-community--dialog--new-member--360--light.png`, `community/about--full--member--1920--light.png`
- Seen in: picture and code
- Confidence: high

### N11 After "Go ahead!" the new owner stays on the dashboard

- Weight: should
- Screen: New community dialog (`/dashboard`), new-member
- What is wrong: on success the dialog closes, a toast says "Community created successfully" and a new card is added to the list. The person is not taken into the new community and gets no next step.
- Why it matters: the next thing a new owner must do (add a first space) is hidden in "Settings" of the community. The person must find the card, open it, find "Settings", then learn "space groups" (see N14).
- Proposed fix: go to the new community after creation, and show there a first-steps card for the owner (see N14).
- Evidence: `Components/Communities/CreateCommunityDialog.tsx:63-74`
- Seen in: code (not verified in the browser)
- Confidence: medium

### N12 The personal community is a dead end: "No spaces yet." and "No resources yet." with no action

- Weight: should
- Screen: Personal community of a new member (`/community/nina-newcomer-s-space`), new-member, 1920 and 360, light and dark
- What is wrong: the left column says "Messages", "No spaces yet.", "Online now (1)" with the person's own face. The main card is "Recent Resources / No resources yet." The community name is not a visible heading (only in the breadcrumb; the `h1` is screen-reader only). Nothing says what this place is for or what to do.
- Why it matters: this is the first community a newcomer opens, because it is the only one. It looks broken. "Messages" in a place that is "Only you" raises the question "messages to whom?".
- Proposed fix: show the name and description as a heading; replace the two "No … yet." lines with an empty state that explains the personal space and offers the first action ("Add a space for your notes", link to the settings). Use `EmptyState`. `Pages/Communities/Show.tsx:75,83-103`, `Components/Spaces/SpaceNavigation.tsx:48`.
- Evidence: `community/personal--empty--new-member--1920--light.png`, `community/personal--empty--new-member--360--light.png`, `community/personal--empty--new-member--360--dark.png`
- Seen in: picture and code
- Confidence: high

### N13 A "personal" community has the settings of a shared one

- Weight: should
- Screen: Settings of the personal community of a new member (`/community/nina-newcomer-s-space/settings`), new-member, 1920, light
- What is wrong: the personal community is described elsewhere as "Only you." Its settings show "Direct messages: Members can write private messages to each other", "Moderator rights", "Members: Invite by email", "Pending invitations" and "Access groups". The visibility line is "Personal. A personal community stays personal." which explains nothing.
- Why it matters: the newcomer cannot tell if this space is private. If they invite someone, is it still "only you"? A careful person distrusts a place whose privacy is not clear.
- Proposed fix: hide the sections that do not apply to a personal community, or, if inviting is allowed, say what happens ("People you invite can read the spaces you open to members"). Replace the circular line with the real description. `Components/Communities/UpdateCommunityForm.tsx`, `Pages/Communities/Settings.tsx`.
- Evidence: `settings/personal--new--new-member--1920--light.png`
- Seen in: picture (whether the server accepts an invitation to a personal community: not verified)
- Confidence: medium

### N14 The owner of an empty community gets no next step, and the first space needs a "space group" first

- Weight: should
- Screen: Community home without content (`/community/atlas-empty`), owner, 360, light; Settings of the personal community, new-member, 1920, light
- What is wrong: the home of an empty community shows "Recent Resources / No resources yet." and a members count; the only way forward is the small "Settings" link. In the settings the empty state says "No space groups yet. Create a space group (for example “Start Here”), then add spaces to it." The newcomer must learn two new words (space group, space) before the first space exists.
- Why it matters: a new owner who does not get to a first space in the first minutes leaves an empty community behind.
- Proposed fix: on the community home, for an owner with no spaces, an empty state with "Add your first space"; create a default group behind the scenes (or offer a first group and space in one step) so that "space group" can be learned later. `Pages/Communities/Show.tsx`, `Components/Spaces/SpaceTreeEditor.tsx:101-102`.
- Evidence: `community/home--empty--owner--360--light.png`, `settings/personal--new--new-member--1920--light.png`
- Seen in: picture and code
- Confidence: high

### N15 The first screen after "Join" shows less than the public page before it

- Weight: should
- Screen: Community home (`/community/atlas-garden`, `/community/atlas-empty`), member, 1920, light; compare Public page of a community, new-member
- What is wrong: before joining, the newcomer sees the name, the description and "Newest posts". After "Join", the page reloads into the member home: no visible name or description (the `h1` is screen-reader only), and the main card is "Recent Resources". No welcome, no "Start here", no pointer to the Welcome space. In "Atlas Empty" the card is "No resources yet." next to six spaces.
- Why it matters: the moment after joining is the moment to guide a person. The page changes under them and the posts they were reading are gone.
- Proposed fix: after a join, show a short welcome (community name, "Start with: <first space>"), keep the name and description visible on the member home, and show the newest posts there too. `Pages/Communities/Show.tsx:75-105`, `Components/Communities/JoinCommunity.tsx:55-56`.
- Evidence: `public/community--full--new-member--1920--light.png`, `community/home--empty--member--1920--light.png`, `Pages/Communities/Show.tsx:75`
- Seen in: picture and code (the full join flow: not verified in the browser)
- Confidence: medium

### N16 The "Join" button does not say what joining does

- Weight: should
- Screen: Public page of a community and Public pages space (`/community/atlas-garden`, `/community/atlas-garden/space/welcome`), new-member, 1920 and 360, light
- What is wrong: one button "Join" with a door icon. On a space page it reads as "join this space". Nothing says that the person joins Atlas Garden, that the members will see their name and profile, or that they can leave. After the click the page reloads with no message.
- Why it matters: a careful person hesitates before a one-click action whose effect they cannot see.
- Proposed fix: label "Join Atlas Garden" and one small line under it: "Members see your name and profile. You can leave at any time." Show a short confirmation after the join. `Components/Communities/JoinCommunity.tsx:59-70`.
- Evidence: `public/community--full--new-member--1920--light.png`, `public/space-pages--full--new-member--360--light.png`
- Seen in: picture and code
- Confidence: high

### N17 On a public post, a signed-in non-member gets no way to comment, and a visitor only "Sign in to reply"

- Weight: should
- Screen: Post of a public space (`/community/atlas-garden/space/announcements/post/welcome-to-atlas-garden`), new-member and visitor, 360 and 1920, light
- What is wrong: as new-member, the comments end with "No comments yet." and nothing else: no "Join to comment", no Join button on the post page. As a visitor, the only action is "Sign in to reply", with no path for a person without an account.
- Why it matters: a post is the most shared kind of link. The person who wants to answer has no visible way to do it.
- Proposed fix: in `CommentSection`, for a signed-in non-member of an open community, show `JoinCommunity` with "Join to comment"; for a visitor, "Sign in or create an account to reply". `Components/Comments/CommentSection.tsx:430-438`.
- Evidence: `public/post--full--new-member--360--light.png`, `public/post--full--visitor--1920--light.png`
- Seen in: picture and code
- Confidence: high

### N18 A community by invitation offers "Sign up" to a visitor and says nothing to a signed-in person

- Weight: should
- Screen: Public page of a community with `join_policy` "invitation" (not in the atlas)
- What is wrong: for a visitor, the page shows "Sign in" and "Sign up" even when an account does not let them in. For a signed-in non-member, `JoinCommunity` returns nothing and no text says "By invitation".
- Why it matters: a newcomer signs up, verifies the address, comes back, and still cannot get in, with no reason given. That is the strongest "this product wasted my time" moment.
- Proposed fix: show "This community is by invitation. Ask a member for an invitation." to both, and do not offer "Sign up" as the main action there. `Pages/Communities/Public.tsx:42-53`, `Components/Communities/JoinCommunity.tsx:26-28`.
- Evidence: `Pages/Communities/Public.tsx:42-49`, `Components/Communities/JoinCommunity.tsx:26`
- Seen in: code (not verified in a picture: the atlas has only an open public community)
- Confidence: medium

### N19 The public page of a community does not say what members get

- Weight: later
- Screen: Public page of a community (`/community/atlas-garden`), visitor, 1920 and 360, light
- What is wrong: the visitor sees two spaces ("Welcome", "Announcements") and the newest posts. The members-only spaces (talk, chat, course, reading list) are hidden, with no count and no hint.
- Why it matters: the visitor cannot judge if joining is worth an account.
- Proposed fix: one line, for example "Members also get 6 more spaces: Garden talk, Lounge, Balcony course …" (names only if the owner allows it), or a count. `Pages/Communities/Public.tsx`.
- Evidence: `public/community--full--visitor--1920--light.png`
- Seen in: picture
- Confidence: medium

### N20 For a member, a lock sits next to every space they can open

- Weight: should
- Screen: Community home and spaces (`/community/atlas-empty`, `/community/atlas-garden/...`), member, 1920, light
- What is wrong: the navigation shows a lock icon beside "Empty discussion", "Garden talk", "Lounge", "Balcony course" and others, for a member who can open all of them. The lock means "members only" (text only for screen readers). Pink dots beside spaces have no visible meaning either.
- Why it matters: a lock tells a newcomer "closed to you". They do not click, or they think they lack a right.
- Proposed fix: for a member, do not show the lock for "members only" (all members see it); keep an icon only for "Access groups only", with a visible tooltip. Explain the dot once (legend or tooltip "New since your last visit"). `Components/Spaces/SpaceNavigation.tsx:28-36,96-103`.
- Evidence: `community/home--empty--member--1920--light.png`, `discussion/post--reply-open--member--1920--light.png`
- Seen in: picture and code
- Confidence: high

### N21 Every writing field shows Markdown code as help

- Weight: should
- Screen: New post, Post (comment form), Chat space (`/community/atlas-garden/space/...`), member, 1920 and 360, light
- What is wrong: under each editor: "Markdown: **bold**, _italic_, [link](https://…), `code`, - list, > quote, @name to mention a member. A YouTube or Vimeo link alone on its line becomes a video." At 360 px it takes three lines in every composer.
- Why it matters: a non-technical person does not know "Markdown" or backticks. The toolbar above already does bold, link and list. The text makes writing look hard on the very first post.
- Proposed fix: keep only the two useful tips in plain words ("Type @ to mention someone. Paste a YouTube link to show the video.") and move the Markdown syntax behind a "Formatting help" link. `Components/ui/MarkdownEditor.tsx:732`.
- Evidence: `discussion/post-short--full--member--360--light.png`, `chat/space--full--member--360--light.png`, `chat/space--empty--member--360--light.png`
- Seen in: picture and code
- Confidence: high

### N22 The comment field is a blank box with no visible label

- Weight: should
- Screen: Short post without comments (`/community/atlas-garden/space/garden-talk/post/short-one-is-rain-water-better`), member, 360, light
- What is wrong: under "No comments yet." there is an empty editor and a pale "Comment" button. The label "Write a comment" exists only for screen readers; no placeholder.
- Why it matters: the newcomer must guess that the box is for them. The chat field has a placeholder ("Message Lounge"); the comment field does not.
- Proposed fix: a visible label or placeholder "Write a comment". `Components/Comments/CommentForm.tsx:74-76`.
- Evidence: `discussion/post-short--full--member--360--light.png`, `Components/Comments/CommentSection.tsx:433`
- Seen in: picture and code
- Confidence: high

### N23 "Not following Follow Mute this thread": the state looks like a button

- Weight: should
- Screen: Post (`/community/atlas-garden/space/garden-talk/post/...`), member, 360 and 1920, light
- What is wrong: under the title, three items in a row: "Not following" (bold text), "Follow" (grey button) and "Mute this thread" (bold text, also a button). Nothing says that "follow" means "get notified about new comments".
- Why it matters: the newcomer cannot tell which of the three is a status and which is an action, nor what follow does.
- Proposed fix: one control: a button "Follow" with a hint "Get a notification for new comments"; when on, "Following ✓" with a menu for "Stop following" and "Mute". `Components/Notifications/ThreadFollowControl.tsx`.
- Evidence: `discussion/post-short--full--member--360--light.png`, `discussion/post--reply-open--member--1920--light.png`
- Seen in: picture
- Confidence: high

### N24 The new post dialog does not say who will read the post

- Weight: should
- Screen: New post, composer open (`/community/atlas-garden/space/garden-talk`), member, 1920, light
- What is wrong: the dialog title is "New post in Garden talk", then "Title", "Post" and "Publish". The page behind it has a chip "Members only" or "Public", but the dialog covers it. In a public discussion space the post, with the person's name, goes on the open web.
- Why it matters: the first post is the moment a careful newcomer asks "who sees this?".
- Proposed fix: one line in the dialog under the title: "Members of Atlas Garden can read this." or "Everybody on the web can read this." `Components/Posts/PostComposer.tsx:93-107`. Same for the chat composer and the comment form in public spaces.
- Evidence: `discussion/composer--open--member--1920--light.png`, `Components/Posts/PostComposer.tsx:97`
- Seen in: picture and code (a public space where members post is not in the atlas)
- Confidence: medium

### N25 On a phone, the chat shows one or two messages in a small box

- Weight: should
- Screen: Chat space (`/community/atlas-garden/space/lounge`), member, 360, light
- What is wrong: below the title, the description, "Notifications: Everything", the chips and "1 here", the messages sit in a box about 230 px high; the composer and the long footer follow, and the page scrolls as a whole. The newcomer sees one and a half messages.
- Why it matters: chat is where a newcomer says hello first. On a phone it looks empty and awkward, with two scroll areas.
- Proposed fix: at small widths, let the message list take the height of the screen minus the composer, fold the description and chips, and do not show the footer under a chat. `Pages/Spaces/Show.tsx`, `Components/Chat/ChatRoom.tsx`.
- Evidence: `chat/space--full--member--360--light.png`
- Seen in: picture
- Confidence: high

### N26 Chat hints talk about a keyboard that a phone does not have

- Weight: should
- Screen: Chat space (`/community/atlas-garden/space/lounge`), member, 360, light
- What is wrong: under the field: "Enter sends, Shift+Enter adds a line, Up arrow edits your last message". The code sends on Enter on every device; a phone keyboard has no Shift+Enter, so a person on a phone cannot write a second line.
- Why it matters: the hint is noise on a phone, and the person cannot write a message with two lines.
- Proposed fix: on touch devices, make Enter add a line and use the "Send" button to send; hide the keyboard hint there. `Components/Chat/ChatComposer.tsx:95,139`.
- Evidence: `chat/space--full--member--360--light.png`, `Components/Chat/ChatComposer.tsx:95`
- Seen in: picture and code (behaviour on a real phone: not verified)
- Confidence: medium

### N27 The time zone question shows raw zone codes and no reason

- Weight: should
- Screen: Time zone question at the top of the page (`/dashboard`), member, 360, light
- What is wrong: "Show times in America/New_York?" with buttons "Yes, use America/New_York" and "Keep Europe/Brussels". The codes have a slash and an underscore. No line says why it asks (times of posts, events and emails).
- Why it matters: a non-technical person reads it as a technical prompt and may press either button at random.
- Proposed fix: show the readable name, as the notification settings already do: "You seem to be in New York time. Show times in New York time (EST)?" with "Yes" / "No, keep Brussels time". `Components/TimeZone/TimeZoneAsk.tsx:103-112`.
- Evidence: `account/time-zone-question--open--member--360--light.png`, `notifications/settings--new--new-member--1920--light.png`
- Seen in: picture and code
- Confidence: high

### N28 The About page explains "Public" with a feature that does not exist, and "Leave" with a wrong rule

- Weight: should
- Screen: About the community (`/community/atlas-garden/about`), member, 1920, light
- What is wrong: under "Visibility: Public" the text is "For a list of public communities, which comes later. Each space says who can read it, and "Who can join" says how people join." Under "Leave the community": "To come back, you need a new invitation." Atlas Garden is open: anyone can come back with "Join".
- Why it matters: the person learns nothing from the visibility line, and may be afraid to leave a community they could rejoin in one click (or, the other way, leave a by-invitation community thinking it is easy to return).
- Proposed fix: describe what the visibility does today; make the leave text depend on `join_policy` ("You can join again at any time." vs. "To come back, you need a new invitation."). `libs/common/src/lib/types/community.schema.ts:42`, `Pages/Communities/About.tsx:221`, `Components/Communities/LeaveCommunityButton.tsx:46`.
- Evidence: `community/about--full--member--1920--light.png`
- Seen in: picture and code
- Confidence: high

### N29 The menus offer "API tokens" to everybody and no help

- Weight: should
- Screen: User menu open (`/dashboard`), new-member, 360, light; Main menu open, member, 360, light
- What is wrong: the user menu holds "Edit profile", "Notification settings", "API tokens", "Log out". The main menu holds one item: "Dashboard". There is no "Help" or "How Knowii works" anywhere; the footer offers "Self-hosting guide", "Source First", "Licence (AGPL-3.0)", "Contributing".
- Why it matters: "API tokens" is a developer word in the first menu a newcomer opens. A person who is lost has no place to ask.
- Proposed fix: move "API tokens" into the account settings page (a section at the end); add a "Help" entry to the user menu; put the help link first in the footer. `Layouts/AppLayout.tsx:179-202`, `Components/MainMenu.tsx`, `Components/Footer.tsx`.
- Evidence: `dashboard/user-menu--open--new-member--360--light.png`, `dashboard/main-menu--open--member--360--light.png`, `public/home--full--visitor--360--light.png`
- Seen in: picture and code
- Confidence: high

### N30 The error page sends a signed-in person to "Coming soon"

- Weight: later
- Screen: Error page (`Pages/Error.tsx`), any role, outside `local`
- What is wrong: the only button is "Go back to the homepage", which is `/`, the "Coming soon" page (N3), also for a signed-in person. The 404 text uses "😭" and the 403 text "⛔".
- Why it matters: a lost newcomer is sent further away from their communities.
- Proposed fix: for a signed-in person, the button goes to the dashboard ("Go to my communities"); a plain tone without emoji. `Pages/Error.tsx:24-29,52`.
- Evidence: `Pages/Error.tsx:52`
- Seen in: code
- Confidence: high

### N31 Notification settings of a new member: "This community has no space with notifications yet."

- Weight: later
- Screen: Notification settings of a new member (`/user/notifications`), new-member, 1920, light
- What is wrong: under "Your spaces", the sentence names "This community" but no community is on the screen (it is the personal one).
- Why it matters: the newcomer wonders which community and whether something is broken.
- Proposed fix: "When you join a community, its spaces show here." `Pages/Profile/Partials/SpaceLevelsForm.tsx:172`.
- Evidence: `notifications/settings--new--new-member--1920--light.png`
- Seen in: picture and code
- Confidence: high

### N32 Direct messages do not say who can read them

- Weight: later
- Screen: Direct messages, no conversation (`/community/atlas-empty/messages`), member, 360, light
- What is wrong: "Conversations", "New message", "No conversations yet." Nothing says that only the people in a conversation can read it (the atlas shows that even the owner cannot open the conversation of others: `messages/conversation--forbidden--owner`).
- Why it matters: "can the moderators read my messages?" is a common first question. The product protects the messages but does not say so.
- Proposed fix: an empty state: "Write privately to one member or a small group. Only the people in the conversation can read it." `Components/Messages/ConversationList.tsx:19`.
- Evidence: `messages/index--empty--member--360--light.png`
- Seen in: picture and code
- Confidence: high

### N33 After "Email me a link", the form goes back to the password and empties the address

- Weight: later
- Screen: Sign-in page, link by email, sent (`/login`), visitor, 360, light
- What is wrong: the green message is good, but the form below switches back to "Email / Password / Log in" with an empty Email field, as if nothing happened.
- Why it matters: a newcomer may think they must still type a password.
- Proposed fix: after sending, show only the message, "Open the email on this device", and a "Send again" link. `Pages/Auth/Login.tsx:39`.
- Evidence: `auth/login-link--sent--visitor--360--light.png`
- Seen in: picture and code
- Confidence: high

### N34 The first post of the Welcome page names spaces without links

- Weight: later
- Screen: Page of a public pages space (`/community/atlas-garden/space/welcome/page/start-here`), visitor, 360, light
- What is wrong: "Say hello in Garden talk", "Start the Balcony course", "ask in the Lounge" are in italics, not links. This is seed text, but the product gives no easy way to link a space from a page (not verified in the editor).
- Why it matters: the "Start here" page is the best guide a newcomer gets; each step should be one tap.
- Proposed fix: let the editor link a space by name (like @ for members, for example # for spaces). `Components/ui/MarkdownEditor.tsx`.
- Evidence: `public/page--full--visitor--360--light.png`
- Seen in: picture (the editor feature: not verified)
- Confidence: low

## Missing building blocks

1. A plain "nothing here yet" line is written by hand in several places instead of `Components/ui/EmptyState.tsx` with an action: `Pages/Communities/Show.tsx:102`, `Components/Spaces/SpaceNavigation.tsx:48`, `Pages/Communities/Public.tsx:100`, `Components/Comments/CommentSection.tsx:356`, `Components/Messages/ConversationList.tsx:19`, `Pages/Profile/Partials/SpaceLevelsForm.tsx:172`. The empty spaces (`Components/Posts/PostList.tsx:166`, `Components/Resources/ResourceList.tsx:117`) show what the good version looks like.
2. An "audience notice" ("Members of X can read this" / "Everybody on the web can read this") for composers: needed in `Components/Posts/PostComposer.tsx`, `Components/Comments/CommentForm.tsx`, `Components/Chat/ChatComposer.tsx`. Today the audience is only a chip in the space header (`Pages/Spaces/Show.tsx`).
3. A "no access" panel with a next step (join, ask for an invitation, sign in), to replace both `Pages/Error.tsx` for 403 on community content and the bare sign-in redirect. Pieces exist in `Components/Communities/JoinCommunity.tsx` and `Components/Reader/Reader.tsx` (`SignInPrompt`).
4. A title for `Components/AuthenticationCard.tsx`: every auth page (`Pages/Auth/Login.tsx`, `Register.tsx`, `VerifyEmail.tsx`, `ForgotPassword.tsx`) renders without a visible heading.
5. `Components/ui/SegmentedControl.tsx` has no slot for a description of the selected option; `CreateCommunityDialog.tsx` and `UpdateCommunityForm.tsx:146` each solve it differently (the first not at all).

## Proposals for the owner

1. A first-run path for a new account. Today the product has good parts (Welcome pages, empty states in spaces, About page) but nothing ties them together for the first ten minutes. Proposal: a small "Getting started" card on the dashboard and on the community home after a join, with three steps that tick themselves (complete your profile, read the "Start here" space, write your first post or message), hidden once done. Reason: N9, N12, N14 and N15 are the same gap seen from four screens.
2. Keep the person in the community they came for. The personal community is the only card on the first dashboard, and every error or logo click leads to "Coming soon". Proposal: remember the community a person signed up from, and land them there after the address check; show the personal space as secondary. Reason: N1, N3, N6, N9, N30.
