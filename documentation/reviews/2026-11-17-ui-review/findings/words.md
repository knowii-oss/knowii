# Findings: words

## Scope covered

1. The words that a person reads, in the code of `apps/knowii/src/Pages`, `Components`, `Layouts`, in `libs/common/src/lib` (labels, schema messages, the fallback messages of `api/knowii-api-client.ts`), and the server messages in `app/` (validation, business exceptions, `bootstrap/app.php`, `app/Traits/ApiResponses.php`). I listed all string literals and JSX texts with the TypeScript parser (about 1,400 texts), then read them by screen.
2. Pictures (1920, light) that I opened to see the texts in place: `auth/login--empty`, `auth/login--wrong-password`, `auth/register--errors`, `auth/forgot-password--unknown-address`, `public/home--full--member`, `dashboard/user-menu--open--member`, `community/home--full--member`, `notifications/page--full--member`, `settings/delete-community--dialog--owner`, `account/profile--full--member`, `discussion/composer--open--member`.
3. Not looked at: the 360 px and dark pictures (the words are the same), the legal pages, the seed data, the email templates in `resources/views` (only their subjects in `app/Mail`), the API documentation texts. I did not run a browser: where a text comes from the server at run time, I say so.

## What works

1. The delete dialogs of a post, a comment, a resource, a space, a chat message, a lesson and a course material say what goes and that it cannot be undone (`Pages/Posts/Show.tsx:197-198`, `Components/Comments/CommentSection.tsx:444-448`, `Pages/Resources/Show.tsx:164-165`, `Components/Spaces/SpaceTreeEditor.tsx:254`, `Components/Chat/ChatRoom.tsx:438-439`, `Components/SpacePages/CourseMaterials.tsx:503-505`). Their button says "Delete", not "OK".
2. The "Remove a member" dialog says what the person loses, what stays and how they can come back (`Components/Communities/CommunityMembersManager.tsx:419-420`).
3. The "Transfer the ownership" section says what the new owner can do and what the old owner becomes (`Pages/Communities/Partials/TransferOwnershipForm.tsx:128`).
4. "Why did I get this?" answers in one plain sentence per reason (`libs/common/src/lib/types/notification.schema.ts:207-222`).
5. The accessible names of repeated buttons name their object: "Delete the comment by Lena Fischer", "Move "Pruning" up", "Role of Maya Moderator" (`CommentSection.tsx:318`, `PageList.tsx:149`, `CommunityMembersManager.tsx:308`).
6. The search engines setting says what becomes visible and what never does (`Components/Communities/IndexingSetting.tsx:52`).
7. The two-factor, connected accounts, time zone, online status and quiz texts are short, active and exact (`Pages/Profile/Partials/TwoFactorAuthenticationForm.tsx`, `ConnectedAccountsForm.tsx`, `TimeZoneForm.tsx`, `OnlineStatusForm.tsx`, `Components/SpacePages/LessonQuiz.tsx`).
8. The newer client validation messages say what to do: "Write the question", "Mark exactly one choice as correct", "Enter a whole number" (`libs/common/src/lib/types/quiz.schema.ts:109-148`).
9. The image description prompt explains why it matters: "Screen readers read this text. Describe what matters in the image." (`Components/ui/ImageInsertPanel.tsx:215`).
10. The error boundary text says what happened and what to do (`Components/ui/ErrorBoundary.tsx:91-92`).

## Glossary: one thing, several names

| Thing                                         | Names found                                                                                                             | Where                                                                                                                                                                                                                                                                                                                                                                                                                                     |
| --------------------------------------------- | ----------------------------------------------------------------------------------------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Go into an account                            | "Sign in", "Log in", "Login"                                                                                            | "Sign in": `Layouts/AppLayout.tsx:107`, `Pages/Auth/MagicLink.tsx:24,38`, `Pages/Auth/TwoFactorChallenge.tsx:91`, `Pages/Communities/Public.tsx:45`, `Components/Communities/JoinCommunity.tsx:34`, `PostList.tsx:152`, `ConnectedAccountsForm.tsx:114`, mail subject `app/Mail/MagicLink.php:27`. "Log in": `Pages/Auth/Login.tsx:51,164`, `Components/PageHeader.tsx:91`. Server: "You are already logged in" (`bootstrap/app.php:302`) |
| Leave the account                             | "Log out", "Log Out"                                                                                                    | `Layouts/AppLayout.tsx:209`, `PageHeader.tsx:79`, `VerifyEmail.tsx:50`, `LogoutOtherBrowserSessionsForm.tsx:99,105`                                                                                                                                                                                                                                                                                                                       |
| Make an account                               | "Sign up", "Register", "Sign up to join", "Need an account?", "Already registered?", "registration"                     | `AppLayout.tsx:109`, `Public.tsx:47`, `JoinCommunity.tsx:36`; `Register.tsx:68,208,218`, `PageHeader.tsx:96`, `Login.tsx:157`, `VerifyEmail.tsx:36`                                                                                                                                                                                                                                                                                       |
| The start page after sign-in                  | "Dashboard", "Home", "My Communities"                                                                                   | `AppLayout.tsx:137`, `PageHeader.tsx:69`, `Dashboard.tsx:98` (tab); `Dashboard.tsx:66`, `BreadcrumbHome.tsx:10` (breadcrumb); `Dashboard.tsx:99` (heading)                                                                                                                                                                                                                                                                                |
| The account page                              | "Edit profile", "Profile", "Profile Information"                                                                        | `AppLayout.tsx:179`, `Pages/Profile/Show.tsx:31,34`, `UpdateProfileInformationForm.tsx:200`. The page also holds password, two-factor, sessions and account deletion                                                                                                                                                                                                                                                                      |
| The notification settings page                | "Notification settings", "Notifications"                                                                                | menu `AppLayout.tsx:190`, hidden h1 `Pages/Profile/Notifications.tsx:55`; tab and breadcrumb `Pages/Profile/Notifications.tsx:51,54` (same tab title as the Notifications page, `Pages/Notifications/Index.tsx:30`)                                                                                                                                                                                                                       |
| The Notifications page                        | "Notifications", "Catch up", "the Notifications page"                                                                   | `Pages/Notifications/Index.tsx:30`; user menu `AppLayout.tsx:167`; `NotificationsPanel.tsx:865`                                                                                                                                                                                                                                                                                                                                           |
| A part of a community                         | "space"; and a personal community is named "<Name>'s Space"                                                             | `app/Actions/Fortify/CreateNewUser.php:120,127`                                                                                                                                                                                                                                                                                                                                                                                           |
| Owner, admins, moderators together            | "the team", "managers", "a manager", "the curator", "moderators" (meaning all three), "a member who manages the spaces" | `About.tsx:149`, `Communities/Show.tsx:122`; `JoinPolicySetting.tsx:13`, `app/Actions/CommunityMembers/JoinCommunity.php` ("Ask its managers"); `ResourceDetails.tsx:118`; `space.schema.ts:49`, `SpaceTreeEditor.tsx:174`; `Spaces/Show.tsx:61`                                                                                                                                                                                          |
| Members at the level "Everything"             | "the followers of this space"                                                                                           | `Components/Posts/PostComposer.tsx:165`                                                                                                                                                                                                                                                                                                                                                                                                   |
| A short message that appears and goes (toast) | "notification", "pop-up"                                                                                                | `Components/ui/Toaster.tsx:64,81`; `Pages/Profile/Partials/PopupsForm.tsx:46` (this one is the notification pop-up)                                                                                                                                                                                                                                                                                                                       |
| Private messages                              | "Messages", "Direct messages", "Conversations", "private messages"                                                      | `CommunityShell.tsx:159`, `DirectMessagesSetting.tsx:36,47`, `Messages/Index.tsx:95`. Acceptable: each word is on its own level, but "private messages" is a fourth word                                                                                                                                                                                                                                                                  |
| Profile picture                               | "Profile picture", "picture", "avatar"                                                                                  | `UpdateAvatarForm.tsx:55,80`; `DeleteUserForm.tsx:52`                                                                                                                                                                                                                                                                                                                                                                                     |
| Copy a link                                   | "Copy link", "Copy the link"                                                                                            | `CommentSection.tsx:306`; `ResourceDetails.tsx:182`                                                                                                                                                                                                                                                                                                                                                                                       |
| Close a toast or pop-up                       | "Dismiss notification", "Close the notification"                                                                        | `Toaster.tsx:64`; `NotificationPopup.tsx:103`                                                                                                                                                                                                                                                                                                                                                                                             |

"Delete" and "Remove" are used well: "Delete" for content, "Remove" for a person or a picture. Keep that rule.

## Findings

### W1 The profile form says that all the information is public, which is false

- Weight: must
- Screen: Account settings, every section (`/user/profile`), member, 1920, light
- What is wrong: The text of "Profile Information" is "Update your account's profile information. Note that Apart from your username, all the information will be publicly visible to other Knowii users." Each of bio, location and phone has its own box "Visible to the members of my communities", and the email address is not shown to other members. The username is the one thing that others do see (@atlas-member in posts and mentions). Also a capital "A" in the middle of the sentence, and "users" where the product says "members".
- Why it matters: A person is misled about privacy, in both directions: they can think the phone number is public, or that the username is private.
- Proposed fix: In `UpdateProfileInformationForm.tsx:204`: "Your name, username and profile picture are visible to the members of your communities. For the bio, location and phone, you choose below. Your email address stays private." (Check the last sentence against the member resource before you ship it.)
- Evidence: `apps/knowii/src/Pages/Profile/Partials/UpdateProfileInformationForm.tsx:62,204-205`; `account/profile--full--member--1920--light.png`
- Seen in: picture, code
- Confidence: high

### W2 The "Delete account" dialog promises more than the box deletes

- Weight: must
- Screen: Delete account, dialog (`/user/profile`), member, code
- What is wrong: The dialog says "Delete what you want removed before you delete your account, or tick the box below." The box says "Also delete my posts, comments and messages". The code (`app/Actions/Users/DeleteMemberContent.php:19-26`) keeps resources, pages and lessons under "Deleted member", and keeps a post or comment that has replies as an empty marker. The section text above lists "your avatar" and does not say that messages stay; the dialog says they do.
- Why it matters: A person who wants their content gone ticks the box and believes it is done. Their resources stay.
- Proposed fix: In `DeleteUserForm.tsx:80,107`, box label: "Also delete my posts, comments and messages". Help under the box: "Your resources, pages and lessons stay, under the name "Deleted member". A post or a comment that has replies stays as an empty marker." Section text (`:52`): replace "avatar" with "profile picture", and say "posts, comments, resources and messages".
- Evidence: `apps/knowii/src/Pages/Profile/Partials/DeleteUserForm.tsx:52,80,107`; `app/Actions/Users/DeleteMemberContent.php:19-26`
- Seen in: code
- Confidence: high

### W3 Error toasts show framework and HTTP texts instead of the product messages

- Weight: should
- Screen: every screen that saves through the API (settings, members, spaces, posts, resources, messages), code
- What is wrong: `apiRequest` uses the `message` of the server when there is one, and the client message ("Failed to delete the post.") only when there is none (`libs/common/src/lib/api/api-result.ts:62-67`). The server always sends one: "Internal Server Error" (`app/Traits/ApiResponses.php:166`), "Not found" (`:90`), the HTTP status text such as "Too Many Requests" (`:152`), "This action is unauthorized." and "The name field is required. (and 1 more error)" from the framework (`bootstrap/app.php:169-201`). About 40 places show `result.message` as the toast title (`showToast({ severity: 'error', summary: result.message })`, for example `Pages/Posts/Show.tsx:92`).
- Why it matters: The roughly 130 written client messages do not show in the cases that matter. A member reads "Internal Server Error" or "This action is unauthorized." and does not know what to do.
- Proposed fix: In `api-result.ts`, keep the client message for status 0, 401, 403, 404, 405, 429 and 5xx, and use the server message only for business errors (category Business) and as field errors for 422. Give 429 one text: "Too many tries. Wait a minute, then try again." Not verified in a browser.
- Evidence: `libs/common/src/lib/api/api-result.ts:62-67`; `app/Traits/ApiResponses.php:90,152,166`; `bootstrap/app.php:169-201`
- Seen in: code
- Confidence: high

### W4 "Sign in" and "Log in", "Sign up" and "Register" name the same actions

- Weight: should
- Screen: Sign-in page (`/login`), Sign-up page (`/register`), public pages (`/`, `/community/atlas-garden`), visitor
- What is wrong: A visitor clicks "Sign in" in the header of a public space (`AppLayout.tsx:107`) and lands on a page whose button says "Log in" (`Login.tsx:164`, tab "Log in"). The home page header says "Log in" and "Register" (`PageHeader.tsx:91,96`); the shell says "Sign in" and "Sign up". The sign-in link mail, the two-factor page and the magic link page say "Sign in". The sign-up page has no heading and its link back says "Already registered?".
- Why it matters: One name per thing is the rule of the product. A non-native reader can think that these are two different accounts or steps.
- Proposed fix: Use "Sign in", "Sign out", "Sign up" everywhere: `Login.tsx:51,164` "Sign in"; `Login.tsx:157` "No account yet? Sign up"; `Register.tsx:68,218` "Sign up"; `Register.tsx:208` "Already have an account? Sign in"; `PageHeader.tsx:79,91,96`; `AppLayout.tsx:209` and `VerifyEmail.tsx:50` "Sign out"; `LogoutOtherBrowserSessionsForm.tsx` "Sign out of other browsers"; `bootstrap/app.php:302` "You are already signed in". Give both pages a visible h1 ("Sign in to Knowii", "Create your Knowii account").
- Evidence: `auth/login--empty--visitor--1920--light.png`, `auth/register--errors--visitor--1920--light.png`, `public/home--full--member--1920--light.png`; the files in the glossary
- Seen in: picture, code
- Confidence: high

### W5 The sign-in, sign-up and password pages show the default framework messages

- Weight: should
- Screen: Sign-in page, wrong password; Sign-up page, sent empty; Forgot password page, address without an account; visitor, 1920, light
- What is wrong: The texts are the defaults of the framework, in another voice and with field keys: "These credentials do not match our records.", "We can't find a user with that email address.", "The name field is required.", "The terms field must be accepted.", "The provided password does not match your current password." (`app/Actions/Communities/TransferCommunityOwnership.php:48`, `app/Actions/Fortify/UpdateUserPassword.php:28`), "The provided two factor authentication code was invalid." (`app/Http/Controllers/API/UserApiController.php:176`). There is no `lang/en` folder to override them (`lang/` holds only `vendor/backup`).
- Why it matters: These are the first error texts a newcomer meets. "credentials", "records", "user" and "terms field" are jargon; none says what to do.
- Proposed fix: Add `lang/en/auth.php`, `passwords.php` and `validation.php` (or a `lang/en.json`) with product texts: "The email address or the password is not correct. Try again, or use "Forgot your password?"."; "No account uses this email address. Check it, or sign up."; "Enter your name."; "Tick the box to accept the terms of service and the privacy policy."; "The current password is not correct."; "This code is not correct. Enter the code that your app shows now." Also put `attributes` names ("email address", not "email").
- Evidence: `auth/login--wrong-password--visitor--1920--light.png`, `auth/forgot-password--unknown-address--visitor--1920--light.png`, `auth/register--errors--visitor--1920--light.png`
- Seen in: picture, code
- Confidence: high

### W6 A personal community is named "<Name>'s Space"

- Weight: should
- Screen: Dashboard (`/dashboard`), member, 1920, light; Personal community (`/community/marc-member-s-space`)
- What is wrong: At sign-up the personal community gets the name "Marc Member's Space" and the same text as description (`CreateNewUser.php:120,127`). In the product a space is a part of a community. The dashboard card shows "Marc Member's Space" next to "Atlas Garden", with the badge "Personal", and the description repeats the name.
- Why it matters: The core word "space" now names two things. A newcomer opens "my space", sees spaces inside it and cannot build a model of the product.
- Proposed fix: Name it "<Name>'s notes" or "<Name> (personal)", and set the description to "Your personal community. Only you can see it." Change `app/Actions/Fortify/CreateNewUser.php:120,127` and the OIDC path (`app/Actions/Auth/SignInWithOpenIdConnect.php:39`). The slug of existing accounts can stay.
- Evidence: `dashboard/user-menu--open--member--1920--light.png`; `app/Actions/Fortify/CreateNewUser.php:120,127`
- Seen in: picture, code
- Confidence: high

### W7 The start page has three names, and the account and settings pages have names that do not match their menu items

- Weight: should
- Screen: Dashboard, User menu open, Notification settings, Account settings; member, 1920, light
- What is wrong: The start page is "Dashboard" in the menu and the tab, "Home" in the breadcrumb, "My Communities" as heading. The menu item "Edit profile" opens a page with password, two-factor, sessions and account deletion; its tab says "Profile". The menu item "Notification settings" opens a page whose tab and breadcrumb say "Notifications", the same as the Notifications page. The menu item "Catch up" opens the Notifications page. The Notifications page has no menu item of its own.
- Why it matters: Two browser tabs named "Notifications - Knowii" for two different pages. A member who wants to change the password does not look under "Edit profile".
- Proposed fix: Start page: "Home" everywhere (menu `AppLayout.tsx:137`, `PageHeader.tsx:69`, tab `Dashboard.tsx:98`); heading "Your communities". Account page: menu and breadcrumb "Account settings", tab "Account settings" (`AppLayout.tsx:179`, `Profile/Show.tsx:31,34`). Notification settings: tab and breadcrumb "Notification settings" (`Pages/Profile/Notifications.tsx:51,54`), and make the h1 visible. User menu: "Notifications" instead of "Catch up" (`AppLayout.tsx:167`), shown always.
- Evidence: `dashboard/user-menu--open--member--1920--light.png`, `account/profile--full--member--1920--light.png`; `Layouts/AppLayout.tsx:137,167,179,190`; `Pages/Profile/Notifications.tsx:51-55`
- Seen in: picture, code
- Confidence: high

### W8 "Delete the community" dialog does not say what goes, and its button says "Go ahead!"

- Weight: should
- Screen: Delete the community, dialog (`/community/atlas-garden/settings`), owner, 1920, light
- What is wrong: Title "Are you sure you want to delete this community?". Body "Please enter the full community name to confirm you would like to permanently delete it: "Atlas Garden"." The list of what is deleted is only on the page behind the backdrop, and it is vague: "its spaces, posts, resources and interactions". The field has a lock icon, as if it asks for a password. The button is "Go ahead!". The section title is "Delete Community" (Title Case).
- Why it matters: This is the most destructive action of the product. The dialog must say what goes, who is affected, and that it cannot be undone, in the dialog itself.
- Proposed fix: In `DeleteCommunityForm.tsx`: title "Delete Atlas Garden?"; body "All its spaces, posts, comments, chat messages, direct messages, pages, courses, resources and members' progress are deleted. Its members lose access. This cannot be undone."; label "Type the name of the community to confirm: Atlas Garden"; no lock icon; button "Delete the community"; section title "Delete the community"; toast "Atlas Garden deleted" instead of "Community deleted successfully".
- Evidence: `settings/delete-community--dialog--owner--1920--light.png`; `apps/knowii/src/Pages/Communities/Partials/DeleteCommunityForm.tsx:47,79-118`
- Seen in: picture, code
- Confidence: high

### W9 Four dialogs confirm with "Go ahead!"

- Weight: should
- Screen: New community dialog, Delete the community, Delete account, Log out other browser sessions
- What is wrong: The confirm button is "Go ahead!" in `CreateCommunityDialog.tsx:103`, `DeleteCommunityForm.tsx:106`, `DeleteUserForm.tsx:73`, `LogoutOtherBrowserSessionsForm.tsx:112`. Every other dialog names the action ("Delete", "Leave", "Remove", "Transfer").
- Why it matters: The label does not say what happens; in two of the four the action cannot be undone.
- Proposed fix: "Create the community", "Delete the community", "Delete my account", "Sign out of other browsers".
- Evidence: the four lines above
- Seen in: code
- Confidence: high

### W10 "Leave the community" says that coming back needs an invitation, which is false for an open community

- Weight: should
- Screen: Leave the community, dialog (`/community/atlas-garden/about`), member
- What is wrong: "To come back, you need a new invitation." (`LeaveCommunityButton.tsx:46`, `About.tsx:221`). Leaving creates no join block (`app/Actions/CommunityMembers/LeaveCommunity.php`; only `RemoveCommunityMember.php:39` does), so in an open community the person can press "Join" again.
- Why it matters: A member is misled about what the action does; it can stop someone from coming back.
- Proposed fix: Say both cases: "To come back, join again if the community is open, or ask for a new invitation." Or pass the join policy and show one sentence.
- Evidence: `apps/knowii/src/Components/Communities/LeaveCommunityButton.tsx:46`; `apps/knowii/src/Pages/Communities/About.tsx:221`; `app/Actions/CommunityMembers/LeaveCommunity.php`
- Seen in: code
- Confidence: high

### W11 The home page says "Coming soon" and "Join the waitlist" to a signed-in member and next to "Register"

- Weight: should
- Screen: Home page (`/`), member and visitor, 1920, light
- What is wrong: The h1 is always "Coming soon", then "Get ready for a revolutionary knowledge-sharing experience!" and "Join the waitlist ... one email when Knowii opens". The same page shows "Dashboard" to a member and "Register" to a visitor. The h2 capitalizes "Knowledge, Ideas and Inspiration"; the footer says "knowledge, ideas and inspiration".
- Why it matters: The text says that the product is not open while it is. "revolutionary" is a promise, not a fact.
- Proposed fix: In `Pages/Welcome.tsx:43-52`, show "Coming soon" and the waitlist only when registration is closed. Otherwise: h1 "A place for your community's knowledge, ideas and inspiration", and a button "Sign up" (or "Open your dashboard" for a member). Drop "revolutionary". Waitlist button: "Join the waitlist" instead of "Subscribe" (`WaitlistForm.tsx:112`).
- Evidence: `public/home--full--member--1920--light.png`; `apps/knowii/src/Pages/Welcome.tsx:43-52`
- Seen in: picture, code
- Confidence: high

### W12 "Verify your email address" sends the person to a page where they cannot edit anything

- Weight: should
- Screen: Verify your email address (`/email/verify`), unverified account
- What is wrong: The page offers "Edit profile" (`VerifyEmail.tsx:47`). On that page every field is disabled while the address is not verified, with "WARNING: You will not be able to edit your profile until your email address has been verified." (`UpdateProfileInformationForm.tsx:209,243,260`). A person who typed a wrong address cannot fix it. The texts are also long and passive: "Before continuing, could you verify your email address by clicking on the link we just emailed to you? If you didn't receive the email, we will gladly send you another."; "A new verification link has been sent to the email address you provided during registration."; tab "Email Verification".
- Why it matters: It is the first screen after sign-up. A wrong address is the common reason to be stuck here.
- Proposed fix: Either let the email field stay editable while unverified, or remove the link. Texts: tab "Verify your email address"; "We sent a link to <address>. Open it to finish your sign-up."; "No email? Check the spam folder, or send a new link."; button "Send a new link"; status "We sent a new link to <address>."; on the profile page drop "WARNING:" and say "Verify your email address to change your profile."
- Evidence: `apps/knowii/src/Pages/Auth/VerifyEmail.tsx:27-50`; `apps/knowii/src/Pages/Profile/Partials/UpdateProfileInformationForm.tsx:209,243,260`; `vendor/laravel/jetstream/routes/inertia.php:31-33` (the profile route has no `verified`)
- Seen in: code
- Confidence: medium (not verified in a browser that the email field stays disabled after a reload)

### W13 Error messages have five patterns

- Weight: should
- Screen: all, code
- What is wrong: The same kind of failure is said in five ways: "Failed to save the community." (API client, about 130 texts), "We could not mark it as done. Try again." (`NotificationsPanel.tsx:327`), "Not saved. Retry." (six places: `PopupsForm.tsx:52`, `SpaceLevelsForm.tsx:203,222`, `TimeZoneForm.tsx:70`, `SpaceLevelControl.tsx:110`, `ThreadFollowControl.tsx:140`, `TimeZoneAsk.tsx:116`), "Could not copy the link" (`CommentSection.tsx:167`), and summary plus "Please try again later." (`DeleteCommunityForm.tsx:57-58`, `CreateCommunityDialog.tsx:78-79`). "Failed to ..." does not say what to do. "Please" is in about 10 messages and absent from the others.
- Why it matters: A member learns one pattern and expects it. "Retry" is plain text, not a button (`PopupsForm.tsx:51-53`, `TimeZoneAsk.tsx:115-117`): it reads as an order and does not say how to retry.
- Proposed fix: One pattern: "<What> was not saved. Try again." or "We could not <verb> the <thing>. Try again." Replace "Failed to" in `knowii-api-client.ts` with "We could not". Drop "Please" from messages. For "Not saved. Retry.": "Not saved. Change it again to retry."
- Evidence: the lines above; `libs/common/src/lib/api/knowii-api-client.ts`
- Seen in: code
- Confidence: high

### W14 Success messages have two patterns

- Weight: later
- Screen: Account settings, Dashboard, Community settings
- What is wrong: Most toasts say "<Thing> saved" ("Community details saved", "Quiz saved"). Older ones say a title "Success" plus "Profile updated successfully." (`UpdateProfileInformationForm.tsx:188`), "Password updated successfully." (`UpdatePasswordForm.tsx:40`), "Logged you out of other browser sessions." (`LogoutOtherBrowserSessionsForm.tsx:44`), "Community created successfully" (`CreateCommunityDialog.tsx:66`), "Community deleted successfully" (`DeleteCommunityForm.tsx:47`).
- Why it matters: "Success" says nothing; "successfully" is filler.
- Proposed fix: "Profile saved", "Password changed", "You are signed out of the other browsers", "<Name> created", "<Name> deleted", no "Success" title.
- Evidence: the lines above
- Seen in: code
- Confidence: high

### W15 Capitals: Title Case and sentence case mix on the same pages

- Weight: later
- Screen: Account settings, Dashboard, Community home, Community settings, Sign-up page, footer
- What is wrong: Title Case: "Profile Information", "Update Password", "Current Password", "New Password", "Confirm Password", "Browser Sessions", "Log Out Other Browser Sessions", "Social Links", "Delete Account" (`UpdateProfileInformationForm.tsx:200,360`, `UpdatePasswordForm.tsx:59,72,90,109`, `LogoutOtherBrowserSessionsForm.tsx:57,105`, `DeleteUserForm.tsx:50,59`), "Delete Community" (`DeleteCommunityForm.tsx:79,86`), "My Communities" (`Dashboard.tsx:99`), "Recent Resources" (`Communities/Show.tsx:84`), "Terms of Service", "Privacy Policy" (`Register.tsx:163,167`, `OpenIdConnectButton.tsx:32,36`; the footer says "Terms of service"), tabs "Confirm Password", "Forgot Password", "Reset Password", "Email Verification", "Contact Us" (`Pages/Auth/*.tsx`, `Contact.tsx:12`). The rest of the product uses sentence case ("Profile picture", "Online status", "Two-factor authentication").
- Why it matters: It shows two products glued together and makes the older parts look unfinished.
- Proposed fix: Sentence case everywhere. Add a lint rule or a test on the `title` props.
- Evidence: `account/profile--full--member--1920--light.png`, `community/home--full--member--1920--light.png`; the lines above
- Seen in: picture, code
- Confidence: high

### W16 The texts of the older account and password pages are long and in another voice

- Weight: should
- Screen: Forgot password page, Confirm password page, Account settings (password, sessions)
- What is wrong: "Forgot your password? No problem. Just let us know your email address and we will email you a password reset link that will allow you to choose a new one." (`ForgotPassword.tsx:36`); "This is a secure area of the application. Please confirm your password before continuing." (`ConfirmPassword.tsx:35`; the dialog says it better: "For your security, confirm your password to continue.", `ConfirmsPassword.tsx:26`); "Ensure your account is using a long, random password to stay secure." (`UpdatePasswordForm.tsx:60`); "If necessary, you may log out of all of your other browser sessions across all of your devices. Some of your recent sessions are listed below; however, this list may not be exhaustive. If you feel your account has been compromised, you should also update your password." (`LogoutOtherBrowserSessionsForm.tsx:59`); "Please enter your password to confirm you would like to log out of your other browser sessions across all of your known devices." (`:119`); "Please enter your password to confirm you would like to permanently delete your account." (`DeleteUserForm.tsx:83`); "Click here to re-send the verification email." (`UpdateProfileInformationForm.tsx:275`).
- Why it matters: Long sentences, "exhaustive", "compromised", "secure area of the application" are hard on one read for a non-native reader.
- Proposed fix: "Enter your email address. We send you a link to choose a new password." / button "Send the link"; confirm page: same text as the dialog; "Use a long password that you use nowhere else."; "These browsers are signed in to your account. If you do not know one of them, sign them all out and change your password."; "Enter your password to confirm."; "Send the verification email again".
- Evidence: the lines above; `auth/forgot-password--unknown-address--visitor--1920--light.png`
- Seen in: picture, code
- Confidence: high

### W17 "Public" and "Private" community promise a privacy that the setting does not give, and a list that does not exist

- Weight: should
- Screen: New community dialog, Community settings (details), Dashboard cards
- What is wrong: Community visibility options are "Personal", "Private", "Public". Their help says: "Not shown in a list of communities. Each space says who can read it ..." and "For a list of public communities, which comes later. ..." (`libs/common/src/lib/types/community.schema.ts:40-42`). So the setting does nothing for who can read, and the "list" does not exist. The dashboard shows the badge "Private" or "Public" on each card, and offers "Filter by visibility".
- Why it matters: An owner reads "Private" and believes the content is hidden; a public space in a "Private" community is readable by anyone. The text also promises a future feature.
- Proposed fix: Until the list exists, remove the choice (or rename it "Listed" / "Not listed" when the list ships). Remove "which comes later". On the cards, show who can join ("Open", "By invitation") instead, since that is what the member needs.
- Evidence: `libs/common/src/lib/types/community.schema.ts:26-42`; `Components/Communities/CreateCommunityDialog.tsx:161`; `dashboard/user-menu--open--member--1920--light.png`
- Seen in: picture, code
- Confidence: high

### W18 "Only moderators can post" is false: owner and admins post too

- Weight: should
- Screen: New space and Edit a space dialogs, Community settings (spaces list), owner
- What is wrong: The option is "Only moderators can post" (`space.schema.ts:49`) and the list shows " · Moderators post" (`SpaceTreeEditor.tsx:174`). The policy lets the owner, admins and moderators post (`app/Policies/SpacePolicy.php:63-67,101-107`). Elsewhere the product calls these three "the team" (`About.tsx:149`, `Communities/Show.tsx:122`).
- Why it matters: An admin can think they cannot post in an announcements space.
- Proposed fix: "Only the team can post (owner, admins, moderators)" and " · Team posts".
- Evidence: `libs/common/src/lib/types/space.schema.ts:48-49`; `apps/knowii/src/Components/Spaces/SpaceTreeEditor.tsx:174`; `app/Policies/SpacePolicy.php:63-67,101-107`
- Seen in: code
- Confidence: high

### W19 The team has five more names: manager, managers, curator, "a member who manages the spaces", moderators

- Weight: should
- Screen: Community settings (who can join), Resource, Link space, Join messages
- What is wrong: "People join when a manager invites them." (`JoinPolicySetting.tsx:13`); "This community is by invitation only. Ask its managers for an invitation" and "You cannot join this community by yourself. Ask its managers for an invitation." (`app/Actions/CommunityMembers/JoinCommunity.php`); "the curator kept only the link" (`ResourceDetails.tsx:118`); "A member who manages the spaces can fix it." (`Spaces/Show.tsx:61`). The roles are Owner, Admin, Moderator, Member (`member.schema.ts:35-38`).
- Why it matters: A member looks for a "manager" in the team list and finds none.
- Proposed fix: Use the role names or "the team": "People join when the team invites them."; "Ask the team for an invitation."; "The member who added it kept only the link."; "The team can fix it."
- Evidence: the lines above
- Seen in: code
- Confidence: high

### W20 The notification for a new lesson reads as broken English

- Weight: should
- Screen: Notifications page (`/notifications`), member, 1920, light
- What is wrong: "Lesson Pruning of Balcony course is out". The title is not set apart, and "is out" is an idiom.
- Why it matters: A non-native reader cannot parse it on one read; the line is on a main path.
- Proposed fix: In `notification.schema.ts:177`: "New lesson in Balcony course: Pruning" and "New lesson in Balcony course". Same pattern as "Maya Moderator posted in Announcements: The garden hall is closed next week".
- Evidence: `notifications/page--full--member--1920--light.png`; `libs/common/src/lib/types/notification.schema.ts:177`
- Seen in: picture, code
- Confidence: high

### W21 Two buttons "I am caught up" on one page do different things

- Weight: should
- Screen: Notifications page (`/notifications`), member, 1920, light
- What is wrong: The "Catch up" card has "I am caught up" (clears the card of one community, `CatchUpCard.tsx:158`). The "Following" section has "I am caught up" too (clears all of Following, `NotificationsPanel.tsx:761`; its error says "We could not clear Following."). The same label, two scopes, on one screen.
- Why it matters: The member cannot know what a click clears.
- Proposed fix: Card: "Hide this summary". Following: "Mark all as read" (and its error "We could not mark Following as read. Try again.").
- Evidence: `notifications/page--full--member--1920--light.png`; `Components/Notifications/CatchUpCard.tsx:158`; `Components/Notifications/NotificationsPanel.tsx:574,761`
- Seen in: picture, code
- Confidence: high

### W22 The resources space is described as "curated articles", but members add any kind of link

- Weight: should
- Screen: Resources space, Resources space without resources, New space type resources, visitor on a resources space
- What is wrong: The type says "Curated links to articles" (`space.schema.ts:35`); the empty state says "Curated articles will show here." (`ResourceList.tsx:118`, `Spaces/Show.tsx:60`). In "Reading list" members add, and the types include video, book, podcast, tool (`resource.schema.ts:13-19`). The visitor prompt says "Sign in to post" (`ResourceList.tsx:111`) while the action is "Add a resource".
- Why it matters: The text says what the space does not do; "curated" is a hard word.
- Proposed fix: Type: "Useful links: articles, videos, books, tools". Empty state: "Useful links will show here." Visitor prompt: "Sign in to add a resource".
- Evidence: the lines above
- Seen in: code
- Confidence: high

### W23 "Tell the followers of this space now" names a group that does not exist

- Weight: should
- Screen: New post, composer open (moderator), code
- What is wrong: "Tell the followers of this space now" and "Without it, the post tells them after 5 minutes. An edit or a deletion in that time tells nobody." (`PostComposer.tsx:165,167`). Members do not follow a space; they choose a level ("Everything", "Only what is for me", "Muted"). "the post tells them" is a figure of speech.
- Why it matters: The moderator cannot know who gets the notification.
- Proposed fix: Box: "Notify now". Help: "Members whose level is Everything get a notification after 5 minutes. Tick this to send it now. If you edit or delete the post in those 5 minutes, nobody gets it."
- Evidence: `apps/knowii/src/Components/Posts/PostComposer.tsx:165-167`
- Seen in: code
- Confidence: high

### W24 Dates: "Member since 3 months ago", and four date formats

- Weight: should
- Screen: Member profile, About the community, Account settings (connected accounts), API tokens
- What is wrong: "Member since" and "Created" are followed by a relative time: "Member since about 1 year ago" (`Members/Show.tsx:96`, `About.tsx:134` with `RelativeTime`). The absolute dates use three formats: date-fns `PPP` "November 17th, 2026" (`ConnectedAccountsForm.tsx:18`, `EntitlementsCard.tsx:11`), the browser locale (`API/Index.tsx:70`, so a French browser shows "17 novembre 2026" inside English text), and `en-US` "Nov 17, 2026, 10:00:00 AM CET" in the tooltips (`libs/common/src/lib/utils/time-zone.fn.ts:79`).
- Why it matters: "since ... ago" is wrong English; a date in another language inside an English sentence confuses.
- Proposed fix: "Member since" and "Created" with an absolute date ("November 2025"). One date function in `libs/common` with a fixed `en` locale and one format for dates ("17 November 2026" or "November 17, 2026"), used by all four places. Drop the seconds in the tooltip.
- Evidence: the lines above
- Seen in: code
- Confidence: high

### W25 The "notification" word also names the toasts

- Weight: later
- Screen: every toast (for example Post, toast after "Copy the link" of a comment)
- What is wrong: The toast region has the accessible name "Notifications" and each close button "Dismiss notification" (`Toaster.tsx:64,81`). The notification pop-up has "Close the notification" (`NotificationPopup.tsx:103`). A screen reader user hears "Notifications" for two different things on the same page.
- Why it matters: One name per thing, also for accessible names.
- Proposed fix: Toast region "Messages about your actions" (or "Status messages"), button "Close the message". Use "Close" in both, not "Dismiss".
- Evidence: `apps/knowii/src/Components/ui/Toaster.tsx:64,81`; `Components/Notifications/NotificationPopup.tsx:103`
- Seen in: code
- Confidence: high

### W26 Internal words in the screens

- Weight: later
- Screen: Quiz editor, Add a resource, Resource, Access groups dialogs, Post and chat composers, error page
- What is wrong: "The server grades their answers" (`QuizEditor.tsx:153`); "capture", "Capture again", "Capture failed", "Capturing the page…", "captured" (`CaptureStatus.tsx:24-36`, `ResourceDetails.tsx:109,188,195`, `CapturedArticle.tsx:45`); "Entitlement keys, one per line", "A member who holds one of these keys at the sign-in provider is in this group" (`AccessGroupsManager.tsx:143,163`); the editor help "Markdown: **bold**, _italic_, [link](https://…), `code`, - list, > quote" (`MarkdownEditor.tsx:732`); server text "The follow could not be saved"; error page titles "403: Forbidden", "500: Server Error" (`Error.tsx:18-21`).
- Why it matters: A member does not know what a server, a capture, an entitlement key or Markdown is.
- Proposed fix: "Knowii checks the answers"; "Save a copy of the page" / "Save the copy again" / "Copy not saved" / "Saving a copy of the page…"; "Product codes from <provider name>, one per line" with an example; editor help "Formatting: **bold**, _italic_, - list, > quote. Paste a YouTube or Vimeo link on its own line to show the video."; error titles without the number ("Page not found", "You cannot open this page", "Something went wrong").
- Evidence: the lines above
- Seen in: code
- Confidence: medium (the right word for "capture" is a product decision)

### W27 The error page texts use emojis, "Whoops" and "forbidden"

- Weight: later
- Screen: Error page (`Pages/Error.tsx`), not in the atlas (the local environment shows the framework page)
- What is wrong: "Whoops, something went wrong on our servers. 😔", "Sorry, the page you are looking for could not be found. 😭", "Sorry, you are forbidden from accessing this page. ⛔", "Sorry, we are doing some maintenance. Please check back soon. 😔". The 403 text does not say why or what to do (for example sign in, or ask for an invitation).
- Why it matters: The tone differs from the rest of the product; screen readers read the emoji names; "forbidden from accessing" is hard English.
- Proposed fix: "This page does not exist, or it moved." / "You cannot open this page. It may be for the members of a community, or for the team." / "Something went wrong on our side. Try again in a minute." / "Knowii is under maintenance. Come back in a few minutes." No emojis.
- Evidence: `apps/knowii/src/Pages/Error.tsx:18-28,53,57`
- Seen in: code
- Confidence: high

### W28 Client validation messages: "arobases", missing characters, two voices

- Weight: later
- Screen: New community dialog with errors, Add a resource dialog with errors, Profile information
- What is wrong: "Please use only letters, numbers, spaces, hyphens, or arobases" (`community.schema.ts:54`, `new-community-resource.schema.ts:24`). "arobase" is French for "@". The rule also allows the apostrophe, and for resources the comma (`constants.ts:130,202`); the message does not say so. Other messages: "Please choose a name for your new community (between 3 and 64 characters)" next to "Use between 3 and 64 characters" (`space.schema.ts:59`) and "Write something". "Please select the visibility level for your community" (`community.schema.ts:12`). "Invalid username" (`UpdateProfileInformationForm.tsx:119`). "The URL is too long" (`space.schema.ts:88`) next to "The address is too long" (`course-material.schema.ts:47`).
- Why it matters: A word a reader cannot know, and a message that hides allowed characters.
- Proposed fix: "Use only letters, digits, spaces, hyphens (-), apostrophes (') and the at sign (@)." (add the comma for resources). "Enter a name of 3 to 64 characters." everywhere. "Choose who can see the community." "The address is too long (maximum N characters)." Drop "Please".
- Evidence: the lines above
- Seen in: code
- Confidence: high

### W29 The same text is written by hand in several places, in different words

- Weight: later
- Screen: Spaces without content, comments, chat, account
- What is wrong: The empty states exist twice: `Pages/Spaces/Show.tsx:56-60` and the lists (`PostList.tsx:166-167`, `ResourceList.tsx:117-118`, `PageList.tsx:202-211`, `ChatRoom.tsx:359-360`), with small differences ("The pages of this space will show here, in order." against "The pages of this space will show here."). "Deleted member" is written 10 times. "Not saved. Retry." 6 times. "Link copied" / "Could not copy the link" twice. "Please wait for the bot check to finish." twice. The confirm-password text is written twice in two voices (`ConfirmPassword.tsx:35`, `ConfirmsPassword.tsx:26`). The "Everything" rule is said four ways: "Everything is only for a discussion space." (`SpaceDialog.tsx:481`), "Everything exists only for discussion and chat spaces" (`SpaceLevelsForm.tsx:156`), "Everything only exists for discussion spaces and chat spaces" and "Everything is the default of a discussion space only. A chat space is never at Everything by default" (`app/Actions/Spaces/UpdateSpace.php:47`).
- Why it matters: The copies drift; a fix in one place misses the others.
- Proposed fix: A `texts.ts` in `libs/common/src/lib` with the shared strings (deleted member, status texts, empty states per space type, copy link), used by every component.
- Evidence: the lines above
- Seen in: code
- Confidence: high

### W30 The "Delete a space group" dialog always says the group must be empty

- Weight: later
- Screen: Delete a space group, dialog (`/community/atlas-garden/settings`), owner
- What is wrong: The text is always "Only an empty space group can be deleted. Move or delete its spaces first.", also for an empty group, and the "Delete" button shows also for a group with spaces (the server then refuses).
- Why it matters: The owner does not know whether the click will work.
- Proposed fix: Empty group: "The group "<name>" is deleted. This cannot be undone." Group with spaces: no Delete button in the dialog, the text "Move or delete its N spaces first.", and one button "Close".
- Evidence: `apps/knowii/src/Components/Spaces/SpaceTreeEditor.tsx:249-262`
- Seen in: code
- Confidence: high

### W31 Small wording fixes: comments, notification levels, chat

- Weight: later
- Screen: Post with comments (moderator), Notification level menu, Chat
- What is wrong: The accessible name "Delete the deleted comment" (`CommentSection.tsx:318`) for the marker of a deleted account; "Muted: You will get nothing from this space, also when someone mentions you." (`notification-follow.schema.ts:61`, "also when" means "even when"); "Everything: You get a line for every new post here" ("a line" is internal; and in a course it is a lesson); "Several people are typing…" is fine; visible "Copy link" (`CommentSection.tsx:306`) against "Copy the link" (`ResourceDetails.tsx:182`); "Search communities..." with three dots (`Dashboard.tsx:106`) against "…" elsewhere.
- Why it matters: Each is small; together they cost a second read.
- Proposed fix: "Delete this empty comment"; "Muted: You get nothing from this space, even when someone mentions you."; "Everything: You get a notification for each new post or lesson."; one label "Copy the link"; "Search communities…".
- Evidence: the lines above
- Seen in: code
- Confidence: high

### W32 Mixed British and American spelling

- Weight: later
- Screen: footer, Community settings
- What is wrong: British: "Licence (AGPL-3.0)" (`footer-links.ts:41`), "Invitation to ... cancelled" (`CommunityMembersManager.tsx:218`). American: "Space groups organize the spaces" (`SpaceGroupDialog.tsx:58`), "Search engines may index" (fine in both).
- Why it matters: Small, but visible in the footer of each page.
- Proposed fix: Choose one (American is the common default for "simple English" tools) and note it in the style guide: "License", "canceled".
- Evidence: the lines above; `dashboard/user-menu--open--member--1920--light.png` (footer)
- Seen in: picture, code
- Confidence: medium

### W33 The API tokens page names a browser extension that a member cannot find

- Weight: later
- Screen: API tokens (`/user/api-tokens`), code only (the page answers 500 in this build)
- What is wrong: "Scripts, tools and the browser extension use a token instead of your password." (`API/Index.tsx:152`) and the placeholder "For example: browser extension" (`:166`). The extension is only planned in the ADRs (`documentation/adr/0022-api-token-abilities.md`); the product has no link to it.
- Why it matters: The text points to something the product does not offer.
- Proposed fix: "Scripts and other tools use a token instead of your password." Placeholder "For example: my backup script". Add the extension back when it ships, with a link.
- Evidence: `apps/knowii/src/Pages/API/Index.tsx:152,166`
- Seen in: code
- Confidence: low (not verified whether an extension exists outside this repository)

### W34 The sign-up button is disabled with no word why

- Weight: later
- Screen: Sign-up page (`/register`), visitor, 1920, light
- What is wrong: "Register" stays disabled until the bot check returns a token (`Register.tsx:215`). No text says so; the message "Please wait for the bot check to finish." exists only in the newsletter and waitlist forms, and only after a click. In the atlas the check cannot load, so the button stays pale and silent.
- Why it matters: When the check is slow or blocked (privacy add-ons), a newcomer cannot sign up and does not know why.
- Proposed fix: Under the check: "Wait a moment: we check that you are not a robot." If it fails to load: "The robot check did not load. Turn off the blocker for this site, or reload the page."
- Evidence: `auth/register--errors--visitor--1920--light.png`; `apps/knowii/src/Pages/Auth/Register.tsx:192-218`
- Seen in: picture, code
- Confidence: medium

## Missing building blocks

1. A shared texts module (`libs/common/src/lib/texts.ts`): "Deleted member" (10 copies, for example `PostCard.tsx:34`, `ChatMessageItem.tsx:48`, `CommentSection.tsx:246`, `notification.schema.ts:126`), the status texts "Saving", "Saved", "Not saved. Retry." (`PopupsForm.tsx`, `SpaceLevelsForm.tsx`, `TimeZoneForm.tsx`, `SpaceLevelControl.tsx`, `ThreadFollowControl.tsx`, `TimeZoneAsk.tsx`), the empty states per space type (`Pages/Spaces/Show.tsx:56-60` and the four lists), the copy-link toasts (`CommentSection.tsx:165-167`, `ResourceDetails.tsx:53-55`).
2. A `SaveStatus` component for the "Saving / Saved / Not saved" text that six forms build by hand (files in point 1).
3. A `ConfirmDeleteDialog` that takes the object name, the list of what goes and the undo sentence, so that every destructive dialog has the same three parts (now built by hand in `DeleteCommunityForm.tsx`, `DeleteUserForm.tsx`, `SpaceTreeEditor.tsx`, `CourseMaterials.tsx`, `PageList.tsx`, `Posts/Show.tsx`, `Resources/Show.tsx`, `ChatRoom.tsx`, `CommentSection.tsx`, `AccessGroupsManager.tsx`).
4. One date component or function for absolute dates (`ConnectedAccountsForm.tsx:18`, `EntitlementsCard.tsx:11`, `API/Index.tsx:70`), next to `RelativeTime`.
5. A `lang/en` folder for the server messages of the framework (W5).

## Proposals for the owner

1. Write a short word list (the glossary above, with one chosen word per row) and a style sheet (sentence case, no "Please", no "successfully", the error pattern, American or British spelling, the date format). Most findings here come from the older Jetstream screens that never met these rules; a list stops the drift.
