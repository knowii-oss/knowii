# Screen atlas of the Knowii Platform

Made on 2026-11-17 from the commit `382ded2d` of `knowii-oss/knowii` (branch `main`), for the UI review (issue 1523).
The application ran in the development containers, with the production build of the front-end (`npm run build`).

270 rows (one screen, in one state, for one role). 1068 pictures of 1076.

## How to read the file names

`<area>/<screen>--<state>--<role>--<width>--<theme>.png`, in the output folder of the tool (the pictures are not in the repository). The table gives the base file name:
add `--1920--light.png`, `--360--light.png`, `--1920--dark.png` or `--360--dark.png`.
Every picture is a full-page screenshot. Width 1920: viewport 1920 x 1080. Width 360: viewport 360 x 800.

## Accounts

The password of the accounts is in `storage/app/atlas-manifest.json`, which the seed writes (not in the repository). Sign in at `http://localhost:4200/login`.

| Role       | Account                       | Who                                                                                                               | In "Atlas Garden" and "Atlas Empty" |
| ---------- | ----------------------------- | ----------------------------------------------------------------------------------------------------------------- | ----------------------------------- |
| visitor    | no account                    | Not signed in                                                                                                     | -                                   |
| new-member | `atlas-new@example.com`       | Nina Newcomer. Signed up, address verified. Only the personal community, no user name chosen, no time zone chosen | not a member                        |
| member     | `atlas-member@example.com`    | Marc Member. Posts, comments, chat, course progress, notifications, direct messages. Was away for nine days       | member                              |
| moderator  | `atlas-moderator@example.com` | Maya Moderator. In the access group "Patrons"                                                                     | moderator                           |
| admin      | `atlas-admin@example.com`     | Adam Admin. The role between the owner and the moderator                                                          | admin                               |
| owner      | `atlas-owner@example.com`     | Olivia Owner. Owns both communities                                                                               | owner                               |

The application has three stored roles (`admin`, `moderator`, `member`, in `App\Enums\KnowiiCommunityMemberRole`) and the owner of the community.
Other accounts of the seed: `atlas-tomas@example.com` and `atlas-lena@example.com` (members who write content),
`atlas-unverified@example.com` (address not verified: the "verify your email address" page), `atlas-2fa@example.com` (two-factor authentication is on: the challenge page).

## How to make it again

1. The seed (safe to run again; it also puts back the read and unread state that a visit changes), from the root of the repository:

   ```sh
   echo "docker compose exec -T -u sail laravel.test php artisan tinker tools/screen-atlas/atlas-seed.php" | newgrp docker
   ```

2. The front-end build, when the code changed (the pictures need the build, not the dev server), from the root of the repository:

   ```sh
   echo "docker compose exec -T -u sail laravel.test npm run build" | newgrp docker
   ```

3. The tool, from `tools/screen-atlas` (it runs the seed again before each width and theme):

   ```sh
   npm ci
   node atlas.mjs
   ```

   One part only: `node atlas.mjs --only settings/ --widths 1920 --themes light`. The options are at the top of `atlas.mjs`.
   The screens are in `screens.json`: one entry per screen and state.

4. To remove the data of the seed (not run): `php artisan tinker tools/screen-atlas/atlas-unseed.php` in the container.

## What to know before you look

- The application ran in the `local` environment. There, a 403, a 404 and a 500 show the error page of the framework, not `Pages/Error.tsx`: `bootstrap/app.php` renders that page only outside `local` and `testing`. The pictures of the area "errors" and every "forbidden" state show what the local environment shows.
- Requests to hosts outside this machine are refused by the tool. The bot check widget (sign-up page, waitlist form of the home page) comes from an outside service: its place is empty in the pictures, and the "Subscribe" button of the waitlist form stays disabled.
- The forms of the sign-in pages and of the account settings use the validation bubbles of the browser for empty required fields. A page screenshot does not hold these bubbles. For the state "errors" of these forms the tool sends the form without the check of the browser: the pictures show the messages of the server.
- The dialogs with a disabled submit button (new community, space, space group, post, page) cannot be sent empty. Their state "errors" shows the messages of a field that was filled and emptied.
- The states "loading" and "error" exist only where the screen asks for its data after the page loads (the tool holds that request, or answers it with a 500). The screens that get their data with the page have no such state.
- A "collection" of resources is a resources space (ADR 0015 of the repository): "Reading list" (members add) and "Tools" (the team curates).
- The list "Online now" and the counts "here" depend on which roles the tool had open at the same time: they are not the same on each run. Relative times ("3 minutes ago") change too.
- Every picture is a full-page screenshot. A layer that is fixed to the viewport (dialog, drawer, menu, image viewer, toast) covers the first 1080 px (800 px at the width 360) only: below that line the page shows without the backdrop. This comes from the screenshot, not from the product.
- The account of a new member has no time zone. The application saves the zone of the browser at the first page load, without a question. The question "Show times in ...?" shows when the zone of the browser is not the zone of the account: see `account/time-zone-question--open--member`.
- The tool asks for reduced motion and stops the animations before each picture.
- The role "admin" is in the atlas for the screens where it differs from the owner and the moderator (settings, community home, dashboard).
- What a visit changes (read notifications, read chat, the dot "New content", the "Catch up" card) is put back by the seed, which the tool runs before each width and theme. The order of the screens of one role is the order of `screens.json`, so the counts in the header change from one picture to the next in the same way on each run.

## Public pages

| Screen                               | Address                                                                    | Role       | State   | Page component                 | Base file name                                 | Note |
| ------------------------------------ | -------------------------------------------------------------------------- | ---------- | ------- | ------------------------------ | ---------------------------------------------- | ---- |
| Home page                            | `/`                                                                        | visitor    | full    | `Pages/Welcome.tsx`            | `public/home--full--visitor`                   |      |
| Home page                            | `/`                                                                        | member     | full    | `Pages/Welcome.tsx`            | `public/home--full--member`                    |      |
| Contact page                         | `/contact`                                                                 | visitor    | full    | `Pages/Contact.tsx`            | `public/contact--full--visitor`                |      |
| Terms of service                     | `/terms-of-service`                                                        | visitor    | full    | `Pages/Legal/Show.tsx`         | `public/legal-terms-of-service--full--visitor` |      |
| Privacy policy                       | `/privacy-policy`                                                          | visitor    | full    | `Pages/Legal/Show.tsx`         | `public/legal-privacy-policy--full--visitor`   |      |
| Data processing agreement            | `/dpa`                                                                     | visitor    | full    | `Pages/Legal/Show.tsx`         | `public/legal-dpa--full--visitor`              |      |
| Content policy                       | `/content-policy`                                                          | visitor    | full    | `Pages/Legal/Show.tsx`         | `public/legal-content-policy--full--visitor`   |      |
| Imprint                              | `/imprint`                                                                 | visitor    | full    | `Pages/Legal/Show.tsx`         | `public/legal-imprint--full--visitor`          |      |
| Security page                        | `/security`                                                                | visitor    | full    | `Pages/Legal/Show.tsx`         | `public/legal-security--full--visitor`         |      |
| Public page of a community           | `/community/atlas-garden`                                                  | visitor    | full    | `Pages/Communities/Public.tsx` | `public/community--full--visitor`              |      |
| Public page of a community           | `/community/atlas-garden`                                                  | new-member | full    | `Pages/Communities/Public.tsx` | `public/community--full--new-member`           |      |
| Public pages space                   | `/community/atlas-garden/space/welcome`                                    | visitor    | full    | `Pages/Spaces/Show.tsx`        | `public/space-pages--full--visitor`            |      |
| Public pages space                   | `/community/atlas-garden/space/welcome`                                    | new-member | full    | `Pages/Spaces/Show.tsx`        | `public/space-pages--full--new-member`         |      |
| Page of a public pages space         | `/community/atlas-garden/space/welcome/page/start-here`                    | visitor    | full    | `Pages/SpacePages/Show.tsx`    | `public/page--full--visitor`                   |      |
| Page of a public pages space         | `/community/atlas-garden/space/welcome/page/start-here`                    | new-member | full    | `Pages/SpacePages/Show.tsx`    | `public/page--full--new-member`                |      |
| Public discussion space              | `/community/atlas-garden/space/announcements`                              | visitor    | full    | `Pages/Spaces/Show.tsx`        | `public/space-discussion--full--visitor`       |      |
| Public discussion space              | `/community/atlas-garden/space/announcements`                              | new-member | full    | `Pages/Spaces/Show.tsx`        | `public/space-discussion--full--new-member`    |      |
| Post of a public space               | `/community/atlas-garden/space/announcements/post/welcome-to-atlas-garden` | visitor    | full    | `Pages/Posts/Show.tsx`         | `public/post--full--visitor`                   |      |
| Post of a public space               | `/community/atlas-garden/space/announcements/post/welcome-to-atlas-garden` | new-member | full    | `Pages/Posts/Show.tsx`         | `public/post--full--new-member`                |      |
| Space for members only, as a visitor | `/community/atlas-garden/space/garden-talk`                                | visitor    | sign-in | `Pages/Spaces/Show.tsx`        | `public/space-members-only--sign-in--visitor`  |      |
| Private community, as a visitor      | `/community/atlas-empty`                                                   | visitor    | sign-in | `Pages/Communities/Show.tsx`   | `public/community-private--sign-in--visitor`   |      |

## Sign-up, sign-in, password and two-factor

| Screen                                           | Address                                                      | Role       | State           | Page component                      | Base file name                                      | Note                                                                  |
| ------------------------------------------------ | ------------------------------------------------------------ | ---------- | --------------- | ----------------------------------- | --------------------------------------------------- | --------------------------------------------------------------------- |
| Sign-in page                                     | `/login`                                                     | visitor    | empty           | `Pages/Auth/Login.tsx`              | `auth/login--empty--visitor`                        |                                                                       |
| Sign-in page, sent empty                         | `/login`                                                     | visitor    | errors          | `Pages/Auth/Login.tsx`              | `auth/login--errors--visitor`                       |                                                                       |
| Sign-in page, wrong email address or password    | `/login`                                                     | visitor    | wrong-password  | `Pages/Auth/Login.tsx`              | `auth/login--wrong-password--visitor`               |                                                                       |
| Sign-in page, link by email                      | `/login`                                                     | visitor    | empty           | `Pages/Auth/Login.tsx`              | `auth/login-link--empty--visitor`                   |                                                                       |
| Sign-in page, link by email, sent empty          | `/login`                                                     | visitor    | errors          | `Pages/Auth/Login.tsx`              | `auth/login-link--errors--visitor`                  |                                                                       |
| Sign-in page, link by email, sent                | `/login`                                                     | visitor    | sent            | `Pages/Auth/Login.tsx`              | `auth/login-link--sent--visitor`                    | The address has no account: no email is written.                      |
| Sign-up page                                     | `/register`                                                  | visitor    | empty           | `Pages/Auth/Register.tsx`           | `auth/register--empty--visitor`                     |                                                                       |
| Sign-up page, sent empty                         | `/register`                                                  | visitor    | errors          | `Pages/Auth/Register.tsx`           | `auth/register--errors--visitor`                    |                                                                       |
| Forgot password page                             | `/forgot-password`                                           | visitor    | empty           | `Pages/Auth/ForgotPassword.tsx`     | `auth/forgot-password--empty--visitor`              |                                                                       |
| Forgot password page, sent empty                 | `/forgot-password`                                           | visitor    | errors          | `Pages/Auth/ForgotPassword.tsx`     | `auth/forgot-password--errors--visitor`             |                                                                       |
| Forgot password page, address without an account | `/forgot-password`                                           | visitor    | unknown-address | `Pages/Auth/ForgotPassword.tsx`     | `auth/forgot-password--unknown-address--visitor`    |                                                                       |
| Reset password page                              | `/reset-password/atlas-token?email=atlas-member@example.com` | visitor    | empty           | `Pages/Auth/ResetPassword.tsx`      | `auth/reset-password--empty--visitor`               |                                                                       |
| Reset password page, sent empty                  | `/reset-password/atlas-token?email=atlas-member@example.com` | visitor    | errors          | `Pages/Auth/ResetPassword.tsx`      | `auth/reset-password--errors--visitor`              |                                                                       |
| Two-factor challenge                             | `/login`                                                     | visitor    | empty           | `Pages/Auth/TwoFactorChallenge.tsx` | `auth/two-factor-challenge--empty--visitor`         | Opens after the sign-in form, with the account atlas-2fa@example.com. |
| Two-factor challenge, sent empty                 | `/login`                                                     | visitor    | errors          | `Pages/Auth/TwoFactorChallenge.tsx` | `auth/two-factor-challenge--errors--visitor`        |                                                                       |
| Two-factor challenge, with a recovery code       | `/login`                                                     | visitor    | recovery-code   | `Pages/Auth/TwoFactorChallenge.tsx` | `auth/two-factor-challenge--recovery-code--visitor` |                                                                       |
| Sign-in link that is not valid                   | `/login/link/atlas-token`                                    | visitor    | invalid         | `Pages/Auth/MagicLink.tsx`          | `auth/magic-link--invalid--visitor`                 |                                                                       |
| Confirm password page                            | `/user/confirm-password`                                     | member     | empty           | `Pages/Auth/ConfirmPassword.tsx`    | `auth/confirm-password--empty--member`              |                                                                       |
| Confirm password page, sent empty                | `/user/confirm-password`                                     | member     | errors          | `Pages/Auth/ConfirmPassword.tsx`    | `auth/confirm-password--errors--member`             |                                                                       |
| Verify your email address                        | `/email/verify`                                              | new-member | full            | `Pages/Auth/VerifyEmail.tsx`        | `auth/verify-email--full--new-member`               | Account atlas-unverified@example.com (address not verified).          |

## Dashboard

| Screen                             | Address      | Role       | State    | Page component        | Base file name                                | Note |
| ---------------------------------- | ------------ | ---------- | -------- | --------------------- | --------------------------------------------- | ---- |
| Dashboard                          | `/dashboard` | new-member | full     | `Pages/Dashboard.tsx` | `dashboard/home--full--new-member`            |      |
| Dashboard                          | `/dashboard` | member     | full     | `Pages/Dashboard.tsx` | `dashboard/home--full--member`                |      |
| Dashboard                          | `/dashboard` | moderator  | full     | `Pages/Dashboard.tsx` | `dashboard/home--full--moderator`             |      |
| Dashboard                          | `/dashboard` | admin      | full     | `Pages/Dashboard.tsx` | `dashboard/home--full--admin`                 |      |
| Dashboard                          | `/dashboard` | owner      | full     | `Pages/Dashboard.tsx` | `dashboard/home--full--owner`                 |      |
| Dashboard, as a visitor            | `/dashboard` | visitor    | sign-in  | `Pages/Dashboard.tsx` | `dashboard/home--sign-in--visitor`            |      |
| Dashboard, search without a result | `/dashboard` | member     | no-match | `Pages/Dashboard.tsx` | `dashboard/home--no-match--member`            |      |
| New community dialog               | `/dashboard` | new-member | dialog   | `Pages/Dashboard.tsx` | `dashboard/new-community--dialog--new-member` |      |
| New community dialog               | `/dashboard` | member     | dialog   | `Pages/Dashboard.tsx` | `dashboard/new-community--dialog--member`     |      |
| New community dialog with errors   | `/dashboard` | member     | errors   | `Pages/Dashboard.tsx` | `dashboard/new-community--errors--member`     |      |
| Main menu open                     | `/dashboard` | member     | open     | `Pages/Dashboard.tsx` | `dashboard/main-menu--open--member`           |      |
| User menu open                     | `/dashboard` | member     | open     | `Pages/Dashboard.tsx` | `dashboard/user-menu--open--member`           |      |
| User menu open                     | `/dashboard` | new-member | open     | `Pages/Dashboard.tsx` | `dashboard/user-menu--open--new-member`       |      |

## Community: home, about, member profiles

| Screen                                                       | Address                                       | Role       | State        | Page component                | Base file name                                | Note                                                                  |
| ------------------------------------------------------------ | --------------------------------------------- | ---------- | ------------ | ----------------------------- | --------------------------------------------- | --------------------------------------------------------------------- |
| Community home                                               | `/community/atlas-garden`                     | member     | full         | `Pages/Communities/Show.tsx`  | `community/home--full--member`                | The member was away for nine days: the "Catch up" card is at the top. |
| Community home                                               | `/community/atlas-garden`                     | moderator  | full         | `Pages/Communities/Show.tsx`  | `community/home--full--moderator`             | The member was away for nine days: the "Catch up" card is at the top. |
| Community home                                               | `/community/atlas-garden`                     | admin      | full         | `Pages/Communities/Show.tsx`  | `community/home--full--admin`                 | The member was away for nine days: the "Catch up" card is at the top. |
| Community home                                               | `/community/atlas-garden`                     | owner      | full         | `Pages/Communities/Show.tsx`  | `community/home--full--owner`                 | The member was away for nine days: the "Catch up" card is at the top. |
| Community home, "Catch up" card, while the data loads        | `/community/atlas-garden`                     | member     | loading      | `Pages/Communities/Show.tsx`  | `community/home-catch-up--loading--member`    |                                                                       |
| Community home, "Catch up" card, when the data request fails | `/community/atlas-garden`                     | member     | error        | `Pages/Communities/Show.tsx`  | `community/home-catch-up--error--member`      |                                                                       |
| Community home without content                               | `/community/atlas-empty`                      | member     | empty        | `Pages/Communities/Show.tsx`  | `community/home--empty--member`               |                                                                       |
| Community home without content                               | `/community/atlas-empty`                      | owner      | empty        | `Pages/Communities/Show.tsx`  | `community/home--empty--owner`                |                                                                       |
| Personal community of a new member                           | `/community/nina-newcomer-s-space`            | new-member | empty        | `Pages/Communities/Show.tsx`  | `community/personal--empty--new-member`       |                                                                       |
| Personal community of a member                               | `/community/marc-member-s-space`              | member     | member       | `Pages/Communities/Show.tsx`  | `community/personal--member--member`          |                                                                       |
| Navigation drawer of a community                             | `/community/atlas-garden`                     | member     | drawer       | `Pages/Communities/Show.tsx`  | `community/navigation--drawer--member`        | Only at 360 px.                                                       |
| Navigation drawer of a community                             | `/community/atlas-garden`                     | owner      | drawer       | `Pages/Communities/Show.tsx`  | `community/navigation--drawer--owner`         | Only at 360 px.                                                       |
| About the community                                          | `/community/atlas-garden/about`               | member     | full         | `Pages/Communities/About.tsx` | `community/about--full--member`               |                                                                       |
| About the community                                          | `/community/atlas-garden/about`               | moderator  | full         | `Pages/Communities/About.tsx` | `community/about--full--moderator`            |                                                                       |
| About the community                                          | `/community/atlas-garden/about`               | owner      | full         | `Pages/Communities/About.tsx` | `community/about--full--owner`                |                                                                       |
| Leave the community, dialog                                  | `/community/atlas-garden/about`               | member     | leave-dialog | `Pages/Communities/About.tsx` | `community/about--leave-dialog--member`       |                                                                       |
| Member profile, the own one                                  | `/community/atlas-garden/member/atlas-member` | member     | own          | `Pages/Members/Show.tsx`      | `community/member-profile--own--member`       |                                                                       |
| Member profile of another member                             | `/community/atlas-garden/member/atlas-tomas`  | member     | other        | `Pages/Members/Show.tsx`      | `community/member-profile--other--member`     |                                                                       |
| Member profile of another member                             | `/community/atlas-garden/member/atlas-tomas`  | moderator  | other        | `Pages/Members/Show.tsx`      | `community/member-profile--other--moderator`  |                                                                       |
| Member profile of another member                             | `/community/atlas-garden/member/atlas-tomas`  | owner      | other        | `Pages/Members/Show.tsx`      | `community/member-profile--other--owner`      |                                                                       |
| Member profile that does not exist                           | `/community/atlas-garden/member/nobody-here`  | member     | not-found    | `Pages/Members/Show.tsx`      | `community/member-profile--not-found--member` |                                                                       |

## Community settings

| Screen                                             | Address                                     | Role       | State               | Page component                   | Base file name                                      | Note |
| -------------------------------------------------- | ------------------------------------------- | ---------- | ------------------- | -------------------------------- | --------------------------------------------------- | ---- |
| Community settings, every section                  | `/community/atlas-garden/settings`          | admin      | full                | `Pages/Communities/Settings.tsx` | `settings/community--full--admin`                   |      |
| Community settings, every section                  | `/community/atlas-garden/settings`          | owner      | full                | `Pages/Communities/Settings.tsx` | `settings/community--full--owner`                   |      |
| Community settings, not allowed                    | `/community/atlas-garden/settings`          | new-member | forbidden           | `Pages/Communities/Settings.tsx` | `settings/community--forbidden--new-member`         |      |
| Community settings, not allowed                    | `/community/atlas-garden/settings`          | member     | forbidden           | `Pages/Communities/Settings.tsx` | `settings/community--forbidden--member`             |      |
| Community settings, not allowed                    | `/community/atlas-garden/settings`          | moderator  | forbidden           | `Pages/Communities/Settings.tsx` | `settings/community--forbidden--moderator`          |      |
| Community settings, as a visitor                   | `/community/atlas-garden/settings`          | visitor    | sign-in             | `Pages/Communities/Settings.tsx` | `settings/community--sign-in--visitor`              |      |
| Settings of a community without content            | `/community/atlas-empty/settings`           | owner      | full                | `Pages/Communities/Settings.tsx` | `settings/community-empty--full--owner`             |      |
| Settings of a personal community                   | `/community/marc-member-s-space/settings`   | member     | full                | `Pages/Communities/Settings.tsx` | `settings/personal--full--member`                   |      |
| Settings of the personal community of a new member | `/community/nina-newcomer-s-space/settings` | new-member | new                 | `Pages/Communities/Settings.tsx` | `settings/personal--new--new-member`                |      |
| Community details with errors                      | `/community/atlas-garden/settings`          | owner      | errors              | `Pages/Communities/Settings.tsx` | `settings/details--errors--owner`                   |      |
| Add a space group, dialog                          | `/community/atlas-garden/settings`          | owner      | add-dialog          | `Pages/Communities/Settings.tsx` | `settings/space-group--add-dialog--owner`           |      |
| Add a space group, dialog with errors              | `/community/atlas-garden/settings`          | owner      | errors              | `Pages/Communities/Settings.tsx` | `settings/space-group--errors--owner`               |      |
| Rename a space group, dialog                       | `/community/atlas-garden/settings`          | owner      | rename-dialog       | `Pages/Communities/Settings.tsx` | `settings/space-group--rename-dialog--owner`        |      |
| Delete a space group, dialog                       | `/community/atlas-garden/settings`          | owner      | delete-dialog       | `Pages/Communities/Settings.tsx` | `settings/space-group--delete-dialog--owner`        |      |
| New space, type discussion                         | `/community/atlas-garden/settings`          | owner      | new                 | `Pages/Communities/Settings.tsx` | `settings/space-form-discussion--new--owner`        |      |
| New space, type chat                               | `/community/atlas-garden/settings`          | owner      | new                 | `Pages/Communities/Settings.tsx` | `settings/space-form-chat--new--owner`              |      |
| New space, type pages                              | `/community/atlas-garden/settings`          | owner      | new                 | `Pages/Communities/Settings.tsx` | `settings/space-form-pages--new--owner`             |      |
| New space, type course                             | `/community/atlas-garden/settings`          | owner      | new                 | `Pages/Communities/Settings.tsx` | `settings/space-form-course--new--owner`            |      |
| New space, type resources                          | `/community/atlas-garden/settings`          | owner      | new                 | `Pages/Communities/Settings.tsx` | `settings/space-form-resources--new--owner`         |      |
| New space, type link                               | `/community/atlas-garden/settings`          | owner      | new                 | `Pages/Communities/Settings.tsx` | `settings/space-form-link--new--owner`              |      |
| New space, dialog with errors                      | `/community/atlas-garden/settings`          | owner      | errors              | `Pages/Communities/Settings.tsx` | `settings/space-form--errors--owner`                |      |
| Edit a space, dialog                               | `/community/atlas-garden/settings`          | owner      | edit                | `Pages/Communities/Settings.tsx` | `settings/space-form--edit--owner`                  |      |
| Edit a space, dialog                               | `/community/atlas-garden/settings`          | admin      | edit                | `Pages/Communities/Settings.tsx` | `settings/space-form--edit--admin`                  |      |
| Edit a space of an access group, dialog            | `/community/atlas-garden/settings`          | owner      | edit-access-groups  | `Pages/Communities/Settings.tsx` | `settings/space-form--edit-access-groups--owner`    |      |
| Edit a link space, dialog                          | `/community/atlas-garden/settings`          | owner      | edit-link           | `Pages/Communities/Settings.tsx` | `settings/space-form--edit-link--owner`             |      |
| Delete a space, dialog                             | `/community/atlas-garden/settings`          | owner      | delete-dialog       | `Pages/Communities/Settings.tsx` | `settings/space--delete-dialog--owner`              |      |
| Role of a member, menu open                        | `/community/atlas-garden/settings`          | owner      | role-menu           | `Pages/Communities/Settings.tsx` | `settings/members--role-menu--owner`                |      |
| Role of a member, menu open                        | `/community/atlas-garden/settings`          | admin      | role-menu           | `Pages/Communities/Settings.tsx` | `settings/members--role-menu--admin`                |      |
| Remove a member, dialog                            | `/community/atlas-garden/settings`          | owner      | remove-dialog       | `Pages/Communities/Settings.tsx` | `settings/members--remove-dialog--owner`            |      |
| Members, search without a result                   | `/community/atlas-garden/settings`          | owner      | search-no-match     | `Pages/Communities/Settings.tsx` | `settings/members--search-no-match--owner`          |      |
| Members list, while the data loads                 | `/community/atlas-garden/settings`          | owner      | loading             | `Pages/Communities/Settings.tsx` | `settings/members--loading--owner`                  |      |
| Members list, when the data request fails          | `/community/atlas-garden/settings`          | owner      | error               | `Pages/Communities/Settings.tsx` | `settings/members--error--owner`                    |      |
| Invite a member, sent empty                        | `/community/atlas-garden/settings`          | owner      | errors              | `Pages/Communities/Settings.tsx` | `settings/invitation--errors--owner`                |      |
| Invite a member, role menu open                    | `/community/atlas-garden/settings`          | owner      | role-menu           | `Pages/Communities/Settings.tsx` | `settings/invitation--role-menu--owner`             |      |
| Access groups, while the data loads                | `/community/atlas-garden/settings`          | owner      | loading             | `Pages/Communities/Settings.tsx` | `settings/access-groups--loading--owner`            |      |
| Access groups, when the data request fails         | `/community/atlas-garden/settings`          | owner      | error               | `Pages/Communities/Settings.tsx` | `settings/access-groups--error--owner`              |      |
| Members of an access group, dialog                 | `/community/atlas-garden/settings`          | owner      | members-dialog      | `Pages/Communities/Settings.tsx` | `settings/access-group--members-dialog--owner`      |      |
| Space groups of an access group, dialog            | `/community/atlas-garden/settings`          | owner      | space-groups-dialog | `Pages/Communities/Settings.tsx` | `settings/access-group--space-groups-dialog--owner` |      |
| Edit an access group, dialog                       | `/community/atlas-garden/settings`          | owner      | edit-dialog         | `Pages/Communities/Settings.tsx` | `settings/access-group--edit-dialog--owner`         |      |
| Delete an access group, dialog                     | `/community/atlas-garden/settings`          | owner      | delete-dialog       | `Pages/Communities/Settings.tsx` | `settings/access-group--delete-dialog--owner`       |      |
| Create an access group, sent empty                 | `/community/atlas-garden/settings`          | owner      | errors              | `Pages/Communities/Settings.tsx` | `settings/access-group--errors--owner`              |      |
| Transfer the ownership, dialog                     | `/community/atlas-garden/settings`          | owner      | dialog              | `Pages/Communities/Settings.tsx` | `settings/transfer-ownership--dialog--owner`        |      |
| Delete the community, dialog                       | `/community/atlas-garden/settings`          | owner      | dialog              | `Pages/Communities/Settings.tsx` | `settings/delete-community--dialog--owner`          |      |

## Discussion spaces and posts

| Screen                                         | Address                                                                                        | Role       | State                 | Page component          | Base file name                                     | Note |
| ---------------------------------------------- | ---------------------------------------------------------------------------------------------- | ---------- | --------------------- | ----------------------- | -------------------------------------------------- | ---- |
| Discussion space                               | `/community/atlas-garden/space/garden-talk`                                                    | member     | full                  | `Pages/Spaces/Show.tsx` | `discussion/space--full--member`                   |      |
| Discussion space                               | `/community/atlas-garden/space/garden-talk`                                                    | moderator  | full                  | `Pages/Spaces/Show.tsx` | `discussion/space--full--moderator`                |      |
| Discussion space                               | `/community/atlas-garden/space/garden-talk`                                                    | owner      | full                  | `Pages/Spaces/Show.tsx` | `discussion/space--full--owner`                    |      |
| Discussion space without posts                 | `/community/atlas-empty/space/empty-discussion`                                                | member     | empty                 | `Pages/Spaces/Show.tsx` | `discussion/space--empty--member`                  |      |
| Discussion space without posts                 | `/community/atlas-empty/space/empty-discussion`                                                | owner      | empty                 | `Pages/Spaces/Show.tsx` | `discussion/space--empty--owner`                   |      |
| Discussion space where only the team writes    | `/community/atlas-garden/space/announcements`                                                  | member     | read-only             | `Pages/Spaces/Show.tsx` | `discussion/space--read-only--member`              |      |
| Discussion space that the member muted         | `/community/atlas-garden/space/off-topic`                                                      | member     | muted                 | `Pages/Spaces/Show.tsx` | `discussion/space--muted--member`                  |      |
| Space of a community, not a member             | `/community/atlas-garden/space/garden-talk`                                                    | new-member | forbidden             | `Pages/Spaces/Show.tsx` | `discussion/space--forbidden--new-member`          |      |
| Space of an access group                       | `/community/atlas-garden/space/patrons-lounge`                                                 | moderator  | full                  | `Pages/Spaces/Show.tsx` | `discussion/space-access-group--full--moderator`   |      |
| Space of an access group                       | `/community/atlas-garden/space/patrons-lounge`                                                 | owner      | full                  | `Pages/Spaces/Show.tsx` | `discussion/space-access-group--full--owner`       |      |
| Space of an access group, not in the group     | `/community/atlas-garden/space/patrons-lounge`                                                 | member     | forbidden             | `Pages/Spaces/Show.tsx` | `discussion/space-access-group--forbidden--member` |      |
| Notification level of a space, menu open       | `/community/atlas-garden/space/garden-talk`                                                    | member     | menu                  | `Pages/Spaces/Show.tsx` | `discussion/space-level--menu--member`             |      |
| New post, composer open                        | `/community/atlas-garden/space/garden-talk`                                                    | member     | open                  | `Pages/Spaces/Show.tsx` | `discussion/composer--open--member`                |      |
| New post, composer open                        | `/community/atlas-garden/space/garden-talk`                                                    | moderator  | open                  | `Pages/Spaces/Show.tsx` | `discussion/composer--open--moderator`             |      |
| New post, composer with errors                 | `/community/atlas-garden/space/garden-talk`                                                    | member     | errors                | `Pages/Spaces/Show.tsx` | `discussion/composer--errors--member`              |      |
| New post, preview                              | `/community/atlas-garden/space/garden-talk`                                                    | member     | preview               | `Pages/Spaces/Show.tsx` | `discussion/composer--preview--member`             |      |
| New post, add a video                          | `/community/atlas-garden/space/garden-talk`                                                    | member     | video-panel           | `Pages/Spaces/Show.tsx` | `discussion/composer--video-panel--member`         |      |
| New post, list of members to mention           | `/community/atlas-garden/space/garden-talk`                                                    | member     | mention-list          | `Pages/Spaces/Show.tsx` | `discussion/composer--mention-list--member`        |      |
| Post with comments, replies and reactions      | `/community/atlas-garden/space/garden-talk/post/my-tomatoes-have-yellow-leaves`                | member     | full                  | `Pages/Posts/Show.tsx`  | `discussion/post--full--member`                    |      |
| Post with comments, replies and reactions      | `/community/atlas-garden/space/garden-talk/post/my-tomatoes-have-yellow-leaves`                | moderator  | full                  | `Pages/Posts/Show.tsx`  | `discussion/post--full--moderator`                 |      |
| Post with comments, replies and reactions      | `/community/atlas-garden/space/garden-talk/post/my-tomatoes-have-yellow-leaves`                | owner      | full                  | `Pages/Posts/Show.tsx`  | `discussion/post--full--owner`                     |      |
| Post, comment threads collapsed                | `/community/atlas-garden/space/garden-talk/post/my-tomatoes-have-yellow-leaves`                | member     | collapsed             | `Pages/Posts/Show.tsx`  | `discussion/post--collapsed--member`               |      |
| Post, reply form open                          | `/community/atlas-garden/space/garden-talk/post/my-tomatoes-have-yellow-leaves`                | member     | reply-open            | `Pages/Posts/Show.tsx`  | `discussion/post--reply-open--member`              |      |
| Post, own comment in edit mode                 | `/community/atlas-garden/space/garden-talk/post/my-tomatoes-have-yellow-leaves`                | member     | comment-edit          | `Pages/Posts/Show.tsx`  | `discussion/post--comment-edit--member`            |      |
| Delete a comment, dialog                       | `/community/atlas-garden/space/garden-talk/post/my-tomatoes-have-yellow-leaves`                | member     | comment-delete-dialog | `Pages/Posts/Show.tsx`  | `discussion/post--comment-delete-dialog--member`   |      |
| Post in edit mode                              | `/community/atlas-garden/space/garden-talk/post/my-tomatoes-have-yellow-leaves`                | member     | edit                  | `Pages/Posts/Show.tsx`  | `discussion/post--edit--member`                    |      |
| Delete a post, dialog                          | `/community/atlas-garden/space/garden-talk/post/my-tomatoes-have-yellow-leaves`                | member     | delete-dialog         | `Pages/Posts/Show.tsx`  | `discussion/post--delete-dialog--member`           |      |
| Post, emoji picker open                        | `/community/atlas-garden/space/garden-talk/post/my-tomatoes-have-yellow-leaves`                | member     | emoji-picker          | `Pages/Posts/Show.tsx`  | `discussion/post--emoji-picker--member`            |      |
| Post, who reacted                              | `/community/atlas-garden/space/garden-talk/post/my-tomatoes-have-yellow-leaves`                | member     | who-reacted           | `Pages/Posts/Show.tsx`  | `discussion/post--who-reacted--member`             |      |
| Post, toast after "Copy the link" of a comment | `/community/atlas-garden/space/garden-talk/post/my-tomatoes-have-yellow-leaves`                | member     | toast                 | `Pages/Posts/Show.tsx`  | `discussion/post--toast--member`                   |      |
| Long post                                      | `/community/atlas-garden/space/garden-talk/post/a-full-guide-to-the-first-season-on-a-balcony` | member     | full                  | `Pages/Posts/Show.tsx`  | `discussion/post-long--full--member`               |      |
| Short post without comments                    | `/community/atlas-garden/space/garden-talk/post/short-one-is-rain-water-better`                | member     | full                  | `Pages/Posts/Show.tsx`  | `discussion/post-short--full--member`              |      |
| Post with a gallery                            | `/community/atlas-garden/space/garden-talk/post/four-weeks-of-my-raised-bed-in-pictures`       | member     | full                  | `Pages/Posts/Show.tsx`  | `discussion/post-gallery--full--member`            |      |
| Image viewer open                              | `/community/atlas-garden/space/garden-talk/post/four-weeks-of-my-raised-bed-in-pictures`       | member     | image-viewer          | `Pages/Posts/Show.tsx`  | `discussion/post-gallery--image-viewer--member`    |      |
| Post with a video                              | `/community/atlas-garden/space/garden-talk/post/video-how-to-prune-a-tomato-plant`             | member     | full                  | `Pages/Posts/Show.tsx`  | `discussion/post-video--full--member`              |      |
| Pinned post                                    | `/community/atlas-garden/space/garden-talk/post/read-this-before-you-post`                     | member     | full                  | `Pages/Posts/Show.tsx`  | `discussion/post-pinned--full--member`             |      |
| Pinned post                                    | `/community/atlas-garden/space/garden-talk/post/read-this-before-you-post`                     | moderator  | full                  | `Pages/Posts/Show.tsx`  | `discussion/post-pinned--full--moderator`          |      |
| Post that does not exist                       | `/community/atlas-garden/space/garden-talk/post/no-such-post`                                  | member     | not-found             | `Pages/Posts/Show.tsx`  | `discussion/post--not-found--member`               |      |

## Chat spaces

| Screen                                    | Address                                   | Role      | State         | Page component          | Base file name                        | Note |
| ----------------------------------------- | ----------------------------------------- | --------- | ------------- | ----------------------- | ------------------------------------- | ---- |
| Chat space                                | `/community/atlas-garden/space/lounge`    | member    | full          | `Pages/Spaces/Show.tsx` | `chat/space--full--member`            |      |
| Chat space                                | `/community/atlas-garden/space/lounge`    | moderator | full          | `Pages/Spaces/Show.tsx` | `chat/space--full--moderator`         |      |
| Chat space                                | `/community/atlas-garden/space/lounge`    | owner     | full          | `Pages/Spaces/Show.tsx` | `chat/space--full--owner`             |      |
| Chat space without messages               | `/community/atlas-empty/space/empty-chat` | member    | empty         | `Pages/Spaces/Show.tsx` | `chat/space--empty--member`           |      |
| Chat space without messages               | `/community/atlas-empty/space/empty-chat` | owner     | empty         | `Pages/Spaces/Show.tsx` | `chat/space--empty--owner`            |      |
| Chat with a thread panel open             | `/community/atlas-garden/space/lounge`    | member    | open          | `Pages/Spaces/Show.tsx` | `chat/thread--open--member`           |      |
| Chat with a thread panel open             | `/community/atlas-garden/space/lounge`    | moderator | open          | `Pages/Spaces/Show.tsx` | `chat/thread--open--moderator`        |      |
| Thread panel, while the data loads        | `/community/atlas-garden/space/lounge`    | member    | loading       | `Pages/Spaces/Show.tsx` | `chat/thread--loading--member`        |      |
| Thread panel, when the data request fails | `/community/atlas-garden/space/lounge`    | member    | error         | `Pages/Spaces/Show.tsx` | `chat/thread--error--member`          |      |
| Chat, members who are here                | `/community/atlas-garden/space/lounge`    | member    | open          | `Pages/Spaces/Show.tsx` | `chat/members-here--open--member`     |      |
| Chat, emoji picker open                   | `/community/atlas-garden/space/lounge`    | member    | open          | `Pages/Spaces/Show.tsx` | `chat/emoji-picker--open--member`     |      |
| Chat, own message in edit mode            | `/community/atlas-garden/space/lounge`    | member    | edit          | `Pages/Spaces/Show.tsx` | `chat/message--edit--member`          |      |
| Delete a message, dialog                  | `/community/atlas-garden/space/lounge`    | member    | delete-dialog | `Pages/Spaces/Show.tsx` | `chat/message--delete-dialog--member` |      |
| Chat, list of members to mention          | `/community/atlas-garden/space/lounge`    | member    | mention-list  | `Pages/Spaces/Show.tsx` | `chat/composer--mention-list--member` |      |
| Chat, preview of a message                | `/community/atlas-garden/space/lounge`    | member    | preview       | `Pages/Spaces/Show.tsx` | `chat/composer--preview--member`      |      |

## Direct messages

| Screen                           | Address                                                     | Role      | State       | Page component             | Base file name                              | Note |
| -------------------------------- | ----------------------------------------------------------- | --------- | ----------- | -------------------------- | ------------------------------------------- | ---- |
| Direct messages, list            | `/community/atlas-garden/messages`                          | member    | full        | `Pages/Messages/Index.tsx` | `messages/index--full--member`              |      |
| Direct messages, list            | `/community/atlas-garden/messages`                          | moderator | full        | `Pages/Messages/Index.tsx` | `messages/index--full--moderator`           |      |
| Direct messages, list            | `/community/atlas-garden/messages`                          | owner     | full        | `Pages/Messages/Index.tsx` | `messages/index--full--owner`               |      |
| Direct messages, no conversation | `/community/atlas-empty/messages`                           | member    | empty       | `Pages/Messages/Index.tsx` | `messages/index--empty--member`             |      |
| Conversation of two              | `/community/atlas-garden/messages/s9j7emw1rapol7zk66odinlm` | member    | direct      | `Pages/Messages/Index.tsx` | `messages/conversation--direct--member`     |      |
| Conversation of a small group    | `/community/atlas-garden/messages/k4y02p6tkpial1dwuo5emsha` | member    | group       | `Pages/Messages/Index.tsx` | `messages/conversation--group--member`      |      |
| Conversation of a small group    | `/community/atlas-garden/messages/k4y02p6tkpial1dwuo5emsha` | moderator | group       | `Pages/Messages/Index.tsx` | `messages/conversation--group--moderator`   |      |
| Conversation of other members    | `/community/atlas-garden/messages/s9j7emw1rapol7zk66odinlm` | owner     | forbidden   | `Pages/Messages/Index.tsx` | `messages/conversation--forbidden--owner`   |      |
| New message, dialog              | `/community/atlas-garden/messages`                          | member    | dialog      | `Pages/Messages/Index.tsx` | `messages/new-message--dialog--member`      |      |
| New message, list of members     | `/community/atlas-garden/messages`                          | member    | member-list | `Pages/Messages/Index.tsx` | `messages/new-message--member-list--member` |      |

## Course spaces

| Screen                              | Address                                                                            | Role      | State         | Page component              | Base file name                             | Note                                                       |
| ----------------------------------- | ---------------------------------------------------------------------------------- | --------- | ------------- | --------------------------- | ------------------------------------------ | ---------------------------------------------------------- |
| Course outline                      | `/community/atlas-garden/space/balcony-course`                                     | member    | full          | `Pages/Spaces/Show.tsx`     | `course/outline--full--member`             | The member finished two lessons and passed the first quiz. |
| Course outline                      | `/community/atlas-garden/space/balcony-course`                                     | moderator | full          | `Pages/Spaces/Show.tsx`     | `course/outline--full--moderator`          | The member finished two lessons and passed the first quiz. |
| Course outline                      | `/community/atlas-garden/space/balcony-course`                                     | owner     | full          | `Pages/Spaces/Show.tsx`     | `course/outline--full--owner`              | The member finished two lessons and passed the first quiz. |
| Course without lessons              | `/community/atlas-empty/space/empty-course`                                        | member    | empty         | `Pages/Spaces/Show.tsx`     | `course/outline--empty--member`            |                                                            |
| Course without lessons              | `/community/atlas-empty/space/empty-course`                                        | owner     | empty         | `Pages/Spaces/Show.tsx`     | `course/outline--empty--owner`             |                                                            |
| Course, new lesson form             | `/community/atlas-garden/space/balcony-course`                                     | owner     | new-lesson    | `Pages/Spaces/Show.tsx`     | `course/outline--new-lesson--owner`        |                                                            |
| Course, new section form            | `/community/atlas-garden/space/balcony-course`                                     | owner     | new-section   | `Pages/Spaces/Show.tsx`     | `course/outline--new-section--owner`       |                                                            |
| Course, add a material              | `/community/atlas-garden/space/balcony-course`                                     | owner     | add-material  | `Pages/Spaces/Show.tsx`     | `course/outline--add-material--owner`      |                                                            |
| Lesson that is done                 | `/community/atlas-garden/space/balcony-course/page/welcome-to-the-course`          | member    | done          | `Pages/SpacePages/Show.tsx` | `course/lesson--done--member`              |                                                            |
| Lesson that is not done             | `/community/atlas-garden/space/balcony-course/page/watering`                       | member    | not-done      | `Pages/SpacePages/Show.tsx` | `course/lesson--not-done--member`          |                                                            |
| Lesson that is not done             | `/community/atlas-garden/space/balcony-course/page/watering`                       | owner     | not-done      | `Pages/SpacePages/Show.tsx` | `course/lesson--not-done--owner`           |                                                            |
| Lesson in edit mode                 | `/community/atlas-garden/space/balcony-course/page/watering`                       | owner     | edit          | `Pages/SpacePages/Show.tsx` | `course/lesson--edit--owner`               |                                                            |
| Delete a lesson, dialog             | `/community/atlas-garden/space/balcony-course/page/watering`                       | owner     | delete-dialog | `Pages/SpacePages/Show.tsx` | `course/lesson--delete-dialog--owner`      |                                                            |
| Video lesson with a material        | `/community/atlas-garden/space/balcony-course/page/video-fill-a-pot-the-right-way` | member    | full          | `Pages/SpacePages/Show.tsx` | `course/lesson-video--full--member`        |                                                            |
| Lesson with supplementary materials | `/community/atlas-garden/space/balcony-course/page/compost-in-a-bucket`            | member    | full          | `Pages/SpacePages/Show.tsx` | `course/lesson-materials--full--member`    |                                                            |
| Lesson with supplementary materials | `/community/atlas-garden/space/balcony-course/page/compost-in-a-bucket`            | moderator | full          | `Pages/SpacePages/Show.tsx` | `course/lesson-materials--full--moderator` |                                                            |
| Quiz before the first attempt       | `/community/atlas-garden/space/balcony-course/page/quiz-water-and-compost`         | member    | before        | `Pages/SpacePages/Show.tsx` | `course/quiz--before--member`              |                                                            |
| Quiz sent without answers           | `/community/atlas-garden/space/balcony-course/page/quiz-water-and-compost`         | member    | errors        | `Pages/SpacePages/Show.tsx` | `course/quiz--errors--member`              |                                                            |
| Quiz after an attempt               | `/community/atlas-garden/space/balcony-course/page/quiz-the-basics`                | member    | after         | `Pages/SpacePages/Show.tsx` | `course/quiz--after--member`               | Passed with one wrong answer.                              |
| Quiz as the team sees it            | `/community/atlas-garden/space/balcony-course/page/quiz-the-basics`                | moderator | manage        | `Pages/SpacePages/Show.tsx` | `course/quiz--manage--moderator`           |                                                            |
| Quiz editor                         | `/community/atlas-garden/space/balcony-course/page/quiz-the-basics`                | moderator | editor        | `Pages/SpacePages/Show.tsx` | `course/quiz--editor--moderator`           |                                                            |

## Pages spaces

| Screen                                 | Address                                                                 | Role      | State           | Page component              | Base file name                        | Note |
| -------------------------------------- | ----------------------------------------------------------------------- | --------- | --------------- | --------------------------- | ------------------------------------- | ---- |
| Pages space                            | `/community/atlas-garden/space/welcome`                                 | member    | full            | `Pages/Spaces/Show.tsx`     | `pages/space--full--member`           |      |
| Pages space                            | `/community/atlas-garden/space/welcome`                                 | moderator | full            | `Pages/Spaces/Show.tsx`     | `pages/space--full--moderator`        |      |
| Pages space                            | `/community/atlas-garden/space/welcome`                                 | owner     | full            | `Pages/Spaces/Show.tsx`     | `pages/space--full--owner`            |      |
| Pages space without pages              | `/community/atlas-empty/space/empty-pages`                              | member    | empty           | `Pages/Spaces/Show.tsx`     | `pages/space--empty--member`          |      |
| Pages space without pages              | `/community/atlas-empty/space/empty-pages`                              | owner     | empty           | `Pages/Spaces/Show.tsx`     | `pages/space--empty--owner`           |      |
| Pages space, new page form             | `/community/atlas-garden/space/welcome`                                 | owner     | new-page        | `Pages/Spaces/Show.tsx`     | `pages/space--new-page--owner`        |      |
| Pages space, new page form with errors | `/community/atlas-garden/space/welcome`                                 | owner     | new-page-errors | `Pages/Spaces/Show.tsx`     | `pages/space--new-page-errors--owner` |      |
| Page                                   | `/community/atlas-garden/space/welcome/page/community-guidelines`       | member    | full            | `Pages/SpacePages/Show.tsx` | `pages/page--full--member`            |      |
| Page                                   | `/community/atlas-garden/space/welcome/page/community-guidelines`       | owner     | full            | `Pages/SpacePages/Show.tsx` | `pages/page--full--owner`             |      |
| Page in edit mode                      | `/community/atlas-garden/space/welcome/page/community-guidelines`       | owner     | edit            | `Pages/SpacePages/Show.tsx` | `pages/page--edit--owner`             |      |
| Long page                              | `/community/atlas-garden/space/welcome/page/frequently-asked-questions` | member    | full            | `Pages/SpacePages/Show.tsx` | `pages/page-long--full--member`       |      |

## Resources spaces

| Screen                                               | Address                                                                        | Role      | State         | Page component             | Base file name                              | Note |
| ---------------------------------------------------- | ------------------------------------------------------------------------------ | --------- | ------------- | -------------------------- | ------------------------------------------- | ---- |
| Resources space                                      | `/community/atlas-garden/space/reading-list`                                   | member    | full          | `Pages/Spaces/Show.tsx`    | `resources/space--full--member`             |      |
| Resources space                                      | `/community/atlas-garden/space/reading-list`                                   | moderator | full          | `Pages/Spaces/Show.tsx`    | `resources/space--full--moderator`          |      |
| Resources space                                      | `/community/atlas-garden/space/reading-list`                                   | owner     | full          | `Pages/Spaces/Show.tsx`    | `resources/space--full--owner`              |      |
| Resources space that the team curates (a collection) | `/community/atlas-garden/space/tools`                                          | member    | full          | `Pages/Spaces/Show.tsx`    | `resources/collection--full--member`        |      |
| Resources space that the team curates (a collection) | `/community/atlas-garden/space/tools`                                          | moderator | full          | `Pages/Spaces/Show.tsx`    | `resources/collection--full--moderator`     |      |
| Resources space without resources                    | `/community/atlas-empty/space/empty-resources`                                 | member    | empty         | `Pages/Spaces/Show.tsx`    | `resources/space--empty--member`            |      |
| Resources space without resources                    | `/community/atlas-empty/space/empty-resources`                                 | owner     | empty         | `Pages/Spaces/Show.tsx`    | `resources/space--empty--owner`             |      |
| Add a resource, dialog                               | `/community/atlas-garden/space/reading-list`                                   | member    | dialog        | `Pages/Spaces/Show.tsx`    | `resources/add-resource--dialog--member`    |      |
| Add a resource, dialog with errors                   | `/community/atlas-garden/space/reading-list`                                   | member    | errors        | `Pages/Spaces/Show.tsx`    | `resources/add-resource--errors--member`    |      |
| Resource                                             | `/community/atlas-garden/space/reading-list/resource/balcony-vegetables-guide` | member    | full          | `Pages/Resources/Show.tsx` | `resources/resource--full--member`          |      |
| Resource                                             | `/community/atlas-garden/space/reading-list/resource/balcony-vegetables-guide` | moderator | full          | `Pages/Resources/Show.tsx` | `resources/resource--full--moderator`       |      |
| Resource                                             | `/community/atlas-garden/space/reading-list/resource/balcony-vegetables-guide` | owner     | full          | `Pages/Resources/Show.tsx` | `resources/resource--full--owner`           |      |
| Resource, toast after "Copy the link"                | `/community/atlas-garden/space/reading-list/resource/balcony-vegetables-guide` | member    | toast         | `Pages/Resources/Show.tsx` | `resources/resource--toast--member`         |      |
| Resource, type menu open                             | `/community/atlas-garden/space/reading-list/resource/balcony-vegetables-guide` | member    | type-menu     | `Pages/Resources/Show.tsx` | `resources/resource--type-menu--member`     |      |
| Delete a resource, dialog                            | `/community/atlas-garden/space/reading-list/resource/balcony-vegetables-guide` | member    | delete-dialog | `Pages/Resources/Show.tsx` | `resources/resource--delete-dialog--member` |      |

## Link spaces

| Screen     | Address                                        | Role   | State | Page component          | Base file name             | Note |
| ---------- | ---------------------------------------------- | ------ | ----- | ----------------------- | -------------------------- | ---- |
| Link space | `/community/atlas-garden/space/seed-catalogue` | member | full  | `Pages/Spaces/Show.tsx` | `link/space--full--member` |      |
| Link space | `/community/atlas-garden/space/seed-catalogue` | owner  | full  | `Pages/Spaces/Show.tsx` | `link/space--full--owner`  |      |

## Notifications

| Screen                                                      | Address               | Role       | State          | Page component                    | Base file name                                   | Note                                            |
| ----------------------------------------------------------- | --------------------- | ---------- | -------------- | --------------------------------- | ------------------------------------------------ | ----------------------------------------------- |
| Notification bell panel open                                | `/dashboard`          | member     | open           | `Pages/Dashboard.tsx`             | `notifications/bell-panel--open--member`         |                                                 |
| Notification bell panel open                                | `/dashboard`          | owner      | open           | `Pages/Dashboard.tsx`             | `notifications/bell-panel--open--owner`          |                                                 |
| Notification bell panel without notifications               | `/dashboard`          | new-member | empty          | `Pages/Dashboard.tsx`             | `notifications/bell-panel--empty--new-member`    |                                                 |
| Notification bell panel, while the data loads               | `/dashboard`          | member     | loading        | `Pages/Dashboard.tsx`             | `notifications/bell-panel--loading--member`      |                                                 |
| Notification bell panel, when the data request fails        | `/dashboard`          | member     | error          | `Pages/Dashboard.tsx`             | `notifications/bell-panel--error--member`        |                                                 |
| Notifications page                                          | `/notifications`      | member     | full           | `Pages/Notifications/Index.tsx`   | `notifications/page--full--member`               | The member sees the "Catch up" card at the top. |
| Notifications page                                          | `/notifications`      | moderator  | full           | `Pages/Notifications/Index.tsx`   | `notifications/page--full--moderator`            | The member sees the "Catch up" card at the top. |
| Notifications page                                          | `/notifications`      | owner      | full           | `Pages/Notifications/Index.tsx`   | `notifications/page--full--owner`                | The member sees the "Catch up" card at the top. |
| Notifications page without notifications                    | `/notifications`      | new-member | empty          | `Pages/Notifications/Index.tsx`   | `notifications/page--empty--new-member`          |                                                 |
| Notifications page, "Earlier" open                          | `/notifications`      | member     | earlier-open   | `Pages/Notifications/Index.tsx`   | `notifications/page--earlier-open--member`       |                                                 |
| Notifications page, menu of a line open                     | `/notifications`      | member     | line-menu      | `Pages/Notifications/Index.tsx`   | `notifications/page--line-menu--member`          |                                                 |
| Notifications page, while the data loads                    | `/notifications`      | member     | loading        | `Pages/Notifications/Index.tsx`   | `notifications/page--loading--member`            |                                                 |
| Notifications page, when the data request fails             | `/notifications`      | member     | error          | `Pages/Notifications/Index.tsx`   | `notifications/page--error--member`              |                                                 |
| Notification settings                                       | `/user/notifications` | member     | full           | `Pages/Profile/Notifications.tsx` | `notifications/settings--full--member`           |                                                 |
| Notification settings                                       | `/user/notifications` | owner      | full           | `Pages/Profile/Notifications.tsx` | `notifications/settings--full--owner`            |                                                 |
| Notification settings of a new member (no time zone chosen) | `/user/notifications` | new-member | new            | `Pages/Profile/Notifications.tsx` | `notifications/settings--new--new-member`        |                                                 |
| Notification settings, level menu open                      | `/user/notifications` | member     | level-menu     | `Pages/Profile/Notifications.tsx` | `notifications/settings--level-menu--member`     |                                                 |
| Notification settings, time zone list open                  | `/user/notifications` | member     | time-zone-menu | `Pages/Profile/Notifications.tsx` | `notifications/settings--time-zone-menu--member` |                                                 |

## Account settings

| Screen                                                             | Address            | Role       | State                   | Page component           | Base file name                                        | Note                                                                                                                                                   |
| ------------------------------------------------------------------ | ------------------ | ---------- | ----------------------- | ------------------------ | ----------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------ |
| Account settings, every section                                    | `/user/profile`    | new-member | full                    | `Pages/Profile/Show.tsx` | `account/profile--full--new-member`                   |                                                                                                                                                        |
| Account settings, every section                                    | `/user/profile`    | member     | full                    | `Pages/Profile/Show.tsx` | `account/profile--full--member`                       |                                                                                                                                                        |
| Account settings, every section                                    | `/user/profile`    | owner      | full                    | `Pages/Profile/Show.tsx` | `account/profile--full--owner`                        |                                                                                                                                                        |
| Account settings with two-factor authentication on                 | `/user/profile`    | member     | two-factor-on           | `Pages/Profile/Show.tsx` | `account/profile--two-factor-on--member`              | Account atlas-2fa@example.com.                                                                                                                         |
| Profile information, user name that is too short                   | `/user/profile`    | member     | errors                  | `Pages/Profile/Show.tsx` | `account/profile-information--errors--member`         | The message comes from the check in the browser, before the form is sent.                                                                              |
| Profile information, sent with a user name that the server refuses | `/user/profile`    | member     | refused                 | `Pages/Profile/Show.tsx` | `account/profile-information--refused--member`        | The server refuses the user name. The page shows no message (see the defects of the report).                                                           |
| Update password with a wrong current password                      | `/user/profile`    | member     | refused                 | `Pages/Profile/Show.tsx` | `account/password--refused--member`                   | The server refuses the current password (nothing changes). The page shows no message (see the defects of the report).                                  |
| Turn on two-factor authentication: confirm the password            | `/user/profile`    | member     | confirm-password-dialog | `Pages/Profile/Show.tsx` | `account/two-factor--confirm-password-dialog--member` |                                                                                                                                                        |
| Log out other browser sessions, dialog                             | `/user/profile`    | member     | dialog                  | `Pages/Profile/Show.tsx` | `account/sessions--dialog--member`                    |                                                                                                                                                        |
| Delete account, dialog                                             | `/user/profile`    | member     | dialog                  | `Pages/Profile/Show.tsx` | `account/delete-account--dialog--member`              |                                                                                                                                                        |
| Delete account, dialog                                             | `/user/profile`    | owner      | dialog                  | `Pages/Profile/Show.tsx` | `account/delete-account--dialog--owner`               |                                                                                                                                                        |
| Delete account, dialog sent without the password                   | `/user/profile`    | member     | errors                  | `Pages/Profile/Show.tsx` | `account/delete-account--errors--member`              |                                                                                                                                                        |
| API tokens, with one token                                         | `/user/api-tokens` | member     | full                    | `Pages/API/Index.tsx`    | `account/api-tokens--full--member`                    | Taken with the Vite dev server (npm run dev): with the production build this address answers 500. Check: status 500, not 200; page null, not API/Index |
| API tokens, no token                                               | `/user/api-tokens` | new-member | empty                   | `Pages/API/Index.tsx`    | `account/api-tokens--empty--new-member`               | Taken with the Vite dev server (npm run dev): with the production build this address answers 500. Check: status 500, not 200; page null, not API/Index |
| API tokens, no token                                               | `/user/api-tokens` | owner      | empty                   | `Pages/API/Index.tsx`    | `account/api-tokens--empty--owner`                    | Taken with the Vite dev server (npm run dev): with the production build this address answers 500. Check: status 500, not 200; page null, not API/Index |
| API tokens, permissions of a token                                 | `/user/api-tokens` | member     | permissions-dialog      | `Pages/API/Index.tsx`    | `account/api-tokens--permissions-dialog--member`      | Taken with the Vite dev server (npm run dev): with the production build this address answers 500. Missing: 1920 light, 1920 dark, 360 light, 360 dark. |
| API tokens, delete a token                                         | `/user/api-tokens` | member     | delete-dialog           | `Pages/API/Index.tsx`    | `account/api-tokens--delete-dialog--member`           | Taken with the Vite dev server (npm run dev): with the production build this address answers 500. Missing: 1920 light, 1920 dark, 360 light, 360 dark. |
| Time zone question at the top of the page                          | `/dashboard`       | member     | open                    | `Pages/Dashboard.tsx`    | `account/time-zone-question--open--member`            | The browser of this picture is in the zone America/New_York, the account in Europe/Brussels.                                                           |

## Error pages

| Screen                    | Address                                       | Role       | State | Page component    | Base file name                         | Note |
| ------------------------- | --------------------------------------------- | ---------- | ----- | ----------------- | -------------------------------------- | ---- |
| Page that does not exist  | `/this-page-does-not-exist`                   | visitor    | full  | `Pages/Error.tsx` | `errors/not-found--full--visitor`      |      |
| Page that does not exist  | `/this-page-does-not-exist`                   | member     | full  | `Pages/Error.tsx` | `errors/not-found--full--member`       |      |
| Space that does not exist | `/community/atlas-garden/space/no-such-space` | member     | full  | `Pages/Error.tsx` | `errors/not-found-space--full--member` |      |
| Community of other people | `/community/atlas-empty`                      | new-member | full  | `Pages/Error.tsx` | `errors/forbidden--full--new-member`   |      |

## Missing pictures

- `account/api-tokens--permissions-dialog--member--1920--light.png`: locator.click: Timeout 12000ms exceeded.
- `account/api-tokens--permissions-dialog--member--1920--dark.png`: locator.click: Timeout 12000ms exceeded.
- `account/api-tokens--permissions-dialog--member--360--light.png`: locator.click: Timeout 12000ms exceeded.
- `account/api-tokens--permissions-dialog--member--360--dark.png`: locator.click: Timeout 12000ms exceeded.
- `account/api-tokens--delete-dialog--member--1920--light.png`: locator.click: Timeout 12000ms exceeded.
- `account/api-tokens--delete-dialog--member--1920--dark.png`: locator.click: Timeout 12000ms exceeded.
- `account/api-tokens--delete-dialog--member--360--light.png`: locator.click: Timeout 12000ms exceeded.
- `account/api-tokens--delete-dialog--member--360--dark.png`: locator.click: Timeout 12000ms exceeded.
