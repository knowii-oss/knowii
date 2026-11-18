# Self-hosting Knowii

Knowii is free and open source (AGPL). You can install it on your own server. This guide explains how to install, configure, run, and update a Knowii instance.

## What you need

| Component                     | Version                      | Notes                                                                          |
| ----------------------------- | ---------------------------- | ------------------------------------------------------------------------------ |
| Linux server with root access | Ubuntu 24.04 LTS recommended | Other distributions work, but the commands in this guide are for Ubuntu.       |
| PHP (CLI and FPM)             | 8.5                          | The required version is in `composer.json`. See the extensions below.          |
| Composer                      | 2                            |                                                                                |
| Node.js and npm               | Node.js 24, npm 11           | Only to build the front-end assets. The Node.js version is in `.nvmrc`.        |
| PostgreSQL                    | 18                           | The development version is in `docker-compose.yml`.                            |
| Web server                    | nginx (or equivalent)        | Serves the application and proxies the WebSocket server, both over HTTPS.      |
| Email provider                | Any                          | Resend by default. Any Laravel mailer works (SMTP, Postmark, Amazon SES, ...). |

You do not need Redis: sessions, the cache, and the job queue use the database.

The required PHP extensions are: `ctype`, `curl`, `dom`, `fileinfo`, `iconv`, `mbstring`, `openssl`, `pcntl` (used by the queue worker for job timeouts), `pdo_pgsql`, `tokenizer`, `xml`, and `zip`. To list what Composer requires, run `composer check-platform-reqs --no-dev`.

On Ubuntu, the PHP 8.5 packages are in the `ondrej/php` PPA:

```bash
sudo add-apt-repository ppa:ondrej/php
sudo apt install php8.5-cli php8.5-fpm php8.5-curl php8.5-mbstring php8.5-pgsql php8.5-xml php8.5-zip
```

## 1. Get the code

Clone the repository and check out the release that you want to run:

```bash
git clone https://github.com/knowii-oss/knowii.git
cd knowii
git checkout <release tag>
```

## 2. Configure DNS and TLS

Knowii serves the application on your domain, and the WebSocket server on the `ws` sub-domain. Create these DNS records:

- An `A` record for `<YOUR_DOMAIN>` that points to the IP address of your server
- An `A` record for `www.<YOUR_DOMAIN>` that points to the same IP address
- An `A` record for `ws.<YOUR_DOMAIN>` that points to the same IP address

Get a TLS certificate that is valid for `<YOUR_DOMAIN>`, `www.<YOUR_DOMAIN>`, and `ws.<YOUR_DOMAIN>` (or for `*.<YOUR_DOMAIN>`). Let's Encrypt certificates are free. If Cloudflare is in front of your server, you can also use a Cloudflare Origin certificate.

## 3. Create the database

Use a dedicated database and database user. In this example, the database is `knowii` and the user is `knowiipro`.

As the `postgres` user, create the user and the database:

```sql
CREATE USER knowiipro WITH PASSWORD '<a strong password>';
CREATE DATABASE knowii OWNER knowiipro;
```

Then connect to the new database (`psql knowii`) and give the user the `public` schema:

```sql
ALTER SCHEMA public OWNER TO knowiipro;
```

## 4. Configure the environment

Copy `.env.example` to `.env`, then change the values below. The other values in `.env.example` are correct for most instances.

| Key                                                               | Value                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                           |
| ----------------------------------------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `APP_ENV`                                                         | `production`                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                    |
| `APP_DEBUG`                                                       | `false`                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                         |
| `APP_URL`                                                         | `https://<YOUR_DOMAIN>`                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                         |
| `APP_KEY`                                                         | Generated by `php artisan key:generate` (step 5)                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                |
| `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | The database from step 3                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                        |
| `MAIL_MAILER`, `MAIL_FROM_ADDRESS`                                | Your email provider and sender address. For Resend, also set `RESEND_KEY`.                                                                                                                                                                                                                                                                                                                                                                                                                                                                                      |
| `MAIL_QUEUE`                                                      | `mail`: the queue of the notification mail, read by its own worker (step 6). `default` puts it on the first worker, for a host that runs one worker only: the rate limit and the hold still apply. Use one of these two names: no worker of this guide reads another queue                                                                                                                                                                                                                                                                                      |
| `MAIL_RATE_LIMIT_PER_SECOND`                                      | `5`: notification mail sent per second, for all mail workers together. Resend allows 10 requests a second per team: the half leaves room for the sign-in links and the other account mail. `0`: no limit (your own SMTP server, `log`)                                                                                                                                                                                                                                                                                                                          |
| `MAIL_MONTHLY_QUOTA`                                              | Optional: the mail that the plan of your mail provider allows each month. If other products send with the same account of your mail provider, set this value lower than the limit of your plan. Empty: the monthly limit that Resend gives (with `MAIL_MAILER=resend`), else no alert and no hold. See "Mail volume" below                                                                                                                                                                                                                                      |
| `MAIL_NOTIFICATION_HOLD_PERCENT`                                  | `95`: notification mail stops at this share of the monthly quota, so that the sign-in links keep a reserve (kept between `1` and `100`; `100`: no reserve)                                                                                                                                                                                                                                                                                                                                                                                                      |
| `MAIL_ALERT_TO`                                                   | Optional: the addresses that get the alert of the mail budget, separated by commas (an operator or a role address, never a member). Empty: the warning in the log is the alert                                                                                                                                                                                                                                                                                                                                                                                  |
| `SESSION_DOMAIN`                                                  | `<YOUR_DOMAIN>`                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                 |
| `SESSION_SECURE_COOKIE`                                           | `true`                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                          |
| `SESSION_COOKIE`                                                  | `__Secure-knowii_session`                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                       |
| `REVERB_APP_ID`, `REVERB_APP_SECRET`                              | Unique random values                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                            |
| `REVERB_APP_KEY`                                                  | A unique value, for example `knowii-<your instance name>`                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                       |
| `REVERB_HOST`                                                     | `ws.<YOUR_DOMAIN>`                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                              |
| `REVERB_PORT`                                                     | `443`                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                           |
| `REVERB_SCHEME`                                                   | `https`                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                         |
| `REVERB_SERVER_HOST`, `REVERB_SERVER_PORT`                        | `127.0.0.1` and `4201`: the WebSocket server listens locally, and nginx forwards `ws.<YOUR_DOMAIN>` to it                                                                                                                                                                                                                                                                                                                                                                                                                                                       |
| `REVERB_ALLOWED_ORIGINS`                                          | `<YOUR_DOMAIN>`                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                 |
| `EMAILOCTOPUS_API_KEY`                                            | Optional: the key of the EmailOctopus account of the newsletter form (and of the old waitlist list). Leave empty to hide the form.                                                                                                                                                                                                                                                                                                                                                                                                                              |
| `EMAILOCTOPUS_WAITLIST_LIST_ID`                                   | Optional: the list of the old waitlist, which takes no new address (#1574). Only `mailing-lists:prune` reads it, to delete its contacts at the end of their retention period. Leave empty on a new instance.                                                                                                                                                                                                                                                                                                                                                    |
| `EMAILOCTOPUS_NEWSLETTER_LIST_ID`                                 | Optional: the newsletter form in the footer (#938). Leave empty to hide the form. It also needs `EMAILOCTOPUS_API_KEY` and the Turnstile keys. Use a list for this instance only: `mailing-lists:prune` deletes its unconfirmed and unsubscribed contacts (ADR 0030).                                                                                                                                                                                                                                                                                           |
| `EMAILOCTOPUS_NEWSLETTER_CONTACT_STATUS`                          | `pending` (the visitor confirms the address by email, recommended) or `subscribed`                                                                                                                                                                                                                                                                                                                                                                                                                                                                              |
| `TURNSTILE_SITE_KEY`, `TURNSTILE_SECRET_KEY`                      | Cloudflare Turnstile keys (bot check). Required by the newsletter form: without them, the form is hidden. The sign-up form uses them when they are set.                                                                                                                                                                                                                                                                                                                                                                                                         |
| `PLAUSIBLE_DOMAIN`, `PLAUSIBLE_PROXY_URL`                         | Optional: Plausible statistics (cookieless) through a proxy that serves `/content/script.js` and receives `/api/v1/event` (for example a Cloudflare Worker). Empty: no analytics script. If you turn it on, list your provider in your privacy policy                                                                                                                                                                                                                                                                                                           |
| `OIDC_ISSUER`, `OIDC_CLIENT_ID`, `OIDC_CLIENT_SECRET`             | Optional: sign in with an OpenID Connect provider (#1385). The three values turn it on: leave one empty and there is no button and no route. See "Sign in with an OpenID Connect provider" below.                                                                                                                                                                                                                                                                                                                                                               |
| `OIDC_ENTITLEMENTS_URL`                                           | Optional: turns on the entitlements of the provider (#1386). The address of the read of one person, `https`, with `{subject}` once in the path. Without it the feature does not exist. See "Entitlements of the provider feed the access groups" below.                                                                                                                                                                                                                                                                                                         |
| `OIDC_ENTITLEMENT_COMMUNITIES`                                    | Optional: the slugs of the communities (separated by commas) whose owner and admins may give an access group by an entitlement key. Empty means none.                                                                                                                                                                                                                                                                                                                                                                                                           |
| `OIDC_ENTITLEMENTS_SCOPE`                                         | Optional: the scope of the client credentials grant that reads the entitlements (default `entitlements:read:subjects`).                                                                                                                                                                                                                                                                                                                                                                                                                                         |
| `OIDC_ENTITLEMENTS_RESOURCE`                                      | Optional: the audience or resource parameter of that token request, when the provider needs one.                                                                                                                                                                                                                                                                                                                                                                                                                                                                |
| `OIDC_ENTITLEMENTS_SIGN_IN_SCOPE`                                 | Optional: the scope that the sign-in also asks when the feature is on (default `entitlements:read`): the provider links the client to the person only then.                                                                                                                                                                                                                                                                                                                                                                                                     |
| `OIDC_FRESH_SIGN_IN_SECONDS`                                      | Optional: an account without a password confirms the deletion of the account with a new sign-in at the provider (#1521). The sign-in must be at most this many seconds old, and the deletion must follow within this many seconds after it (default `300`, kept between `60` and `900`).                                                                                                                                                                                                                                                                        |
| `OIDC_SCOPES`, `OIDC_LABEL`                                       | Optional: the scopes (default `openid profile email`, `offline_access` is never asked) and the text of the button (without it, the host name of the issuer).                                                                                                                                                                                                                                                                                                                                                                                                    |
| `ROBOTS_ALLOW_INDEXING`                                           | `true` lets search engines index the public pages (default in production). `false` on a test or private instance: `robots.txt` refuses every crawler and every page sends `noindex`                                                                                                                                                                                                                                                                                                                                                                             |
| `SECURITY_TXT_CONTACTS`                                           | Where a person reports a security problem, served in `/.well-known/security.txt` (RFC 9116): `mailto:`, `https://` or `tel:` addresses, separated by commas, in the order of preference. Empty: the instance has no `security.txt`. Name your own contact: never the one of another instance                                                                                                                                                                                                                                                                    |
| `SECURITY_TXT_EXPIRES`, `SECURITY_TXT_POLICY`                     | Optional: the date after which the file is stale (ISO 8601; empty: 180 days after the current day), and the page of your security policy (`https`; empty: the security page of the application)                                                                                                                                                                                                                                                                                                                                                                 |
| `SECURITY_TXT_ENCRYPTION`, `SECURITY_TXT_ACKNOWLEDGMENTS`         | Optional (`https`): your public key for encrypted reports, and the page that thanks the reporters. `SECURITY_TXT_PREFERRED_LANGUAGES`: the languages of a report that you read (default `en`)                                                                                                                                                                                                                                                                                                                                                                   |
| `COMMUNITY_RESERVED_NAMES`, `COMMUNITY_RESERVED_NAMES_OWNERS`     | Optional: community names that only your administrators can use (your brand, your products), separated by commas, and the email addresses of the accounts that can use them (verified accounts). They add to the built-in list of `config/communities.php` (Support, Official, Admin, the names of the platform). For everyone else a reserved name is "taken" (ADR 0036)                                                                                                                                                                                       |
| `COURSE_MATERIALS_DISK`, `COURSE_MATERIALS_MAX_MB`                | Optional: the disk of the files that course managers attach to courses and lessons, and the maximum size of a file in megabytes (default `local` and `50`). The disk must be private: `local` (`storage/app`, never served by the web server) or an S3-compatible disk of `config/filesystems.php`, never `public`. Members download the files through the application, which checks their access (ADR 0037). Raise `upload_max_filesize` and `post_max_size` of PHP and `client_max_body_size` of nginx to the same size, and include the disk in your backups |
| `LOG_STACK`, `LOG_DAILY_DAYS`                                     | `daily` and `14`: one log file per day in `storage/logs`, deleted after 14 days (the period of the privacy policy). With `stderr`, rotate the logs of your process manager the same way                                                                                                                                                                                                                                                                                                                                                                         |

Mail volume (#1477, [ADR 0054](documentation/adr/0054-email-delivery-the-mail-queue-the-budget-bounces-and-the-one-click-stop.md)): the mail provider limits how much the platform sends.

1. Resend accepts 10 requests a second per team, for all its API keys together. The free plan allows 3 000 emails a month and 100 a day; the paid plans have a higher monthly quota and no daily one. Over a quota, Resend refuses every email, the sign-in links included.
2. The quota is per team at the provider, not per instance. If other products send with the same account of your mail provider, set `MAIL_MONTHLY_QUOTA` lower than the limit of your plan.
3. `mail:check-budget` runs every hour (step 6). It counts the emails of the month: the number of Resend when it can read the usage of the team, else the count of this instance (every email sent, whatever the mailer). At 80% of the quota it writes a warning in the log and sends one alert to `MAIL_ALERT_TO`. At `MAIL_NOTIFICATION_HOLD_PERCENT` it holds the notification emails until the next month, and sends a second alert. The sign-in links and the other account emails keep going. A refusal of Resend for its monthly or its daily quota holds the notification emails too, until the next month or the next day (00:00 UTC). Raise the quota and the next check ends the hold. When Resend cannot be read (a timeout, for example), the check keeps a hold that is set and tries again at the next hour.
4. To read the usage, the API key of `RESEND_KEY` needs the right to read it. Without that right, the check uses the count of this instance and writes a warning in the log every hour (its `error_type` is `restricted_api_key`).
5. Without a quota (no `MAIL_MONTHLY_QUOTA` and a mailer that is not Resend, for example `smtp` or `log`), there is no alert and no hold. The rate limit works with every mailer.

Sign in with an OpenID Connect provider (#1385, [ADR 0045](documentation/adr/0045-openid-connect-sign-in.md)): members can sign in with an account of any OpenID Connect provider (an external account service), next to the password and the sign-in link. Knowii has no list of providers: you set one. What the provider must implement, from the sign-in to the change calls, is in [the contract of an external account service](documentation/architecture/external-account-service.md).

1. At the provider, register Knowii as a confidential client (authorization code flow with PKCE). The redirect address is `<APP_URL>/auth/oidc/callback`, exactly, for example `https://community.example.com/auth/oidc/callback`.
2. Set `OIDC_ISSUER` (the address of the provider, `https`, without a trailing path that the provider does not publish: Knowii reads `<issuer>/.well-known/openid-configuration`), `OIDC_CLIENT_ID` and `OIDC_CLIENT_SECRET`. Set `OIDC_LABEL` to the name of the account at the provider: with `Example account` the button shows "Continue with Example account".
3. The provider must send these claims in the ID token: `sub` (the stable identifier of the account), `email` and `email_verified` (the boolean `true`), and optionally `name`. The default scopes `openid profile email` ask for them. The ID token must be signed with `EdDSA`, `RS256` or `ES256`. Knowii refuses any other algorithm, `none` included.
4. Knowii reads the discovery document and the keys of the provider and keeps them for one hour. The server must reach the provider over `https`, and the provider must resolve to a public address.
5. The first sign-in of a person links the provider account to the account of the platform that has the same email address, when the provider says that the address is verified. If that account never verified its address, the sign-in takes it over: the address becomes verified, the password is removed, and the sessions and the API tokens of the account are closed. After that the link uses the identifier of the provider account, not the address. A person with no account gets one, with a verified address and no password.
6. Accounts with two-factor authentication still enter their code. The password, the sign-in link and the registration stay available. `/login?provider=1` goes to the provider at once.
7. An account without a password deletes itself after a new sign-in at the provider ([ADR 0052](documentation/adr/0052-account-deletion-after-a-new-sign-in.md)): Knowii asks for it with `max_age=0` and `prompt=login`, and accepts it only when the ID token holds `auth_time` and that time is at most `OIDC_FRESH_SIGN_IN_SECONDS` old. For the deletion of an account without a password, the provider must support `max_age` and send `auth_time`. Otherwise the member gets a refusal that asks them to write to you.
8. Without the three values, the feature is off: no button, no route (404), nothing else changes. In the local environment only, an `http://localhost` issuer is accepted, to test with a provider on your machine.

Entitlements of the provider feed the access groups (#1386, [ADR 0047](documentation/adr/0047-entitlement-rules-feed-access-groups.md)): what a person owns at the provider (a plan, a course) can open the spaces of a community, as it happens, without any manual step. It works only with the sign-in above, and only for the communities that you list. The read, the answer and the signed change call are described in [the contract of an external account service](documentation/architecture/external-account-service.md).

1. At the provider, the client of Knowii needs the client credentials grant with the scope of the read of a subject (`OIDC_ENTITLEMENTS_SCOPE`, default `entitlements:read:subjects`), and a change address: `<APP_URL>/webhooks/oidc/entitlements`, exactly. The provider signs each call with the key of its `jwks_uri` (header `typ: entitlements-event+jwt`, `Content-Type: application/jwt`).
2. Set `OIDC_ENTITLEMENTS_URL` to the address of the read of one person, `https`, with `{subject}` once in the path (for example `https://accounts.example.com/v1/subjects/{subject}/entitlements`). Set `OIDC_ENTITLEMENTS_RESOURCE` if the provider needs an audience in the token request. The sign-in then also asks the scope `OIDC_ENTITLEMENTS_SIGN_IN_SCOPE` (default `entitlements:read`): the provider links a client to a person only with it.
3. List the communities that may use rules in `OIDC_ENTITLEMENT_COMMUNITIES`, by slug, separated by commas. Empty means none: an entitlement says what a person bought, so another community of a shared instance must not learn it. The owner and the admins of a listed community then give an access group by one or more keys ("Rules" in the access groups of the settings, or `PUT /communities/{community}/access-groups/{group}/rules`). Knowii never interprets a key.
4. Knowii reads the keys of a person at the sign-in through the provider, when the person joins a community that has rules, when the provider calls the change address, and once a night for every connected account (`entitlements:sync`, 02:30, a read a second at most). It stores the keys and the date of the check, nothing else, and no refresh token. A read that fails (network, timeout, 5xx) changes nothing and is tried again later. A refusal of the client by the provider (401, 403) changes nothing and is logged once an hour: check the registration of the client.
5. Run the queue worker (step 6): the reads and the sync of a rule change are queued jobs.
6. Removing a community from `OIDC_ENTITLEMENT_COMMUNITIES`, or the address, ends the rules at the next nightly run: the memberships that rules gave are removed (the ones added by hand stay), and without the address the stored keys are deleted too.
7. Without `OIDC_ENTITLEMENTS_URL` the feature does not exist: no route (404), no job, no screen.

Crawlers (#104, #1487): two switches work together. `ROBOTS_ALLOW_INDEXING` is the switch of the instance: `false` and nothing is indexed, whatever the communities decide. Each community has its own setting, "Search engines may index the public spaces" (off by default, changed by its owner and admins on the settings page or with `PATCH /v1/communities/{community}/indexing`). A page is indexed only when both are on, the reader is not signed in, and the page is the public community page or a page of a public space; the decision is in the `X-Robots-Tag` header and the meta tag of each page. `/robots.txt` is built from `config/robots.php`. It refuses a list of AI crawlers everywhere and keeps every crawler out of the member areas and the API. Pages of signed-in members and the API always send `X-Robots-Tag: noindex, nofollow`. `robots.txt` is only a request: if Cloudflare is in front of your instance, also turn on its "Block AI bots" setting (Security, Bots), which refuses them at the edge. On a test or private instance, set `ROBOTS_ALLOW_INDEXING=false`.

Retention (ADR 0030): `config/retention.php` holds the retention periods of the data that Knowii keeps only for a time, and the scheduled tasks of step 6 apply them. The privacy policy reads the same numbers, so it always tells what your instance does. If you change a `RETENTION_*` value, the policy changes with it. Logs (`LOG_DAILY_DAYS`), sessions (`SESSION_LIFETIME`) and your backups follow your server configuration: keep them equal to the periods of your privacy policy.

| Key                                                                                | Value                                                                                                                                                                                                       |
| ---------------------------------------------------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `RETENTION_EXPIRED_INVITATIONS_DAYS`                                               | Days an expired invitation stays before `retention:prune` deletes it (default `30`)                                                                                                                         |
| `RETENTION_EXPIRED_API_TOKENS_DAYS`                                                | Days an expired API token stays before `retention:prune` deletes it (default `30`)                                                                                                                          |
| `RETENTION_FAILED_JOBS_DAYS`                                                       | Days a failed background job keeps its data (default `7`)                                                                                                                                                   |
| `RETENTION_UNUSED_UPLOADS_DAYS`                                                    | Days an uploaded image that no content uses stays (default `7`)                                                                                                                                             |
| `RETENTION_WAITLIST_MONTHS`                                                        | Months a waitlist contact stays in EmailOctopus after the signup (default `12`)                                                                                                                             |
| `RETENTION_INACTIVE_CONTACTS_DAYS`                                                 | Days an unconfirmed or unsubscribed EmailOctopus contact stays after its last change (default `30`)                                                                                                         |
| `RETENTION_NOTIFICATIONS_READ_DAYS`                                                | Days a notification stays after the member read it (default `60`)                                                                                                                                           |
| `RETENTION_NOTIFICATIONS_UNREAD_DAYS`                                              | Days a notification stays when the member never read it, counted from its creation (default `90`)                                                                                                           |
| `RETENTION_EXPORTS_DAYS`                                                           | Days an export file (`php artisan communities:export` or `users:export`) stays before `retention:prune` deletes it (default `7`)                                                                            |
| `RETENTION_BACKUPS_DAILY`, `RETENTION_BACKUPS_WEEKLY`, `RETENTION_BACKUPS_MONTHLY` | The snapshots your backup script keeps (default `7`, `4` and `12`). Knowii does not run the backups: use the same numbers in your script (`restic forget --keep-daily 7 --keep-weekly 4 --keep-monthly 12`) |

Legal pages (#904): the privacy policy, the terms of service, the imprint, the data processing agreement and the content policy are Markdown files in `resources/markdown`. They describe the Knowii instance of DeveloPassion SRL: replace each one with your own file in `storage/app/legal/<file>.md` (same file names: `policy.md`, `terms.md`, `imprint.md`, `dpa.md`, `content-policy.md`), which an update never overwrites. A file starts with a front matter block (`title`, `updated`, and `draft: true` while the text is not final). `{{retention.<period>}}` shows a period of `config/retention.php` (ADR 0030), `{{subprocessors}}` the table of `config/legal.php` (`{{subprocessors.community}}` only the sub-processors of community data, for the DPA), and `{{cookies.session}}` the name of the session cookie (`SESSION_COOKIE`). The invitation email names who runs the instance: set `LEGAL_OPERATOR` to your organisation (for example `Example Ltd (Ireland)`).

Members upload images for their posts and comments. By default the files go to the `public` disk (`storage/app/public`), served from `/storage`: run `php artisan storage:link` once after the installation. To use an S3-compatible bucket (Cloudflare R2, Hetzner object storage), configure an S3 disk in `config/filesystems.php`, set `UPLOADS_DISK` to its name and `UPLOADS_PUBLIC_URL` to its public URL (the Content Security Policy allows that origin). Include the upload storage in your backups.

The Content Security Policy (`config/secure-headers.php`) lists every origin that the browser may load from or connect to. It never accepts a whole scheme such as `https:`. It allows the Reverb WebSocket origin from the `REVERB_*` values automatically. These optional values add origins:

| Key                  | Value                                                                                            |
| -------------------- | ------------------------------------------------------------------------------------------------ |
| `UPLOADS_PUBLIC_URL` | The public URL of the uploaded files when another host serves them (S3-compatible bucket or CDN) |
| `CSP_IMG_SRC`        | Comma-separated extra origins for images, for example `https://images.example.com`               |
| `CSP_MEDIA_SRC`      | Comma-separated extra origins for audio and video files                                          |
| `CSP_FRAME_SRC`      | Comma-separated extra origins for embedded frames                                                |
| `CSP_CONNECT_SRC`    | Comma-separated extra origins for API calls and WebSockets                                       |

Bare schemes (`https:`) and `*` in these values are ignored. In the `local` environment, the policy also allows the Vite dev server (`http://localhost:<VITE_PORT>`, or `VITE_DEV_SERVER_URL`).

Do not set `SANCTUM_STATEFUL_DOMAINS` to an empty value. The front-end calls the API with the session cookie, so the API must treat requests from your domain as first-party ("stateful"). If the value is empty, every API call fails with `401 Unauthorized`. Leave the key out (the default includes the host of `APP_URL`), or set it to `<YOUR_DOMAIN>`.

Behind a reverse proxy that is not on the same machine, or that does not use `127.0.0.1`, set `TRUSTED_PROXIES` to the addresses of the proxy (comma-separated, CIDR ranges allowed), or to `*` when only the proxy can reach the application. Laravel then reads the real client address and the scheme from `X-Forwarded-For` and `X-Forwarded-Proto`. The default is empty: no proxy is trusted.

The `VITE_*` values are read when you build the front-end (step 5). If you change `APP_NAME` or a `REVERB_*` value later, build the front-end again.

## 5. Install and build

Run these commands from the root of the repository:

```bash
composer install --no-dev --optimize-autoloader --no-interaction
npm ci
npm run build
php artisan key:generate   # first installation only
php artisan migrate --force
php artisan optimize
```

## 6. Run the processes

Knowii needs five processes, in addition to PHP-FPM:

- The web server (nginx with PHP-FPM), which serves the `public/` folder
- The queue worker, which processes jobs. Knowii sends its real-time (WebSocket) events through the queue: without a worker, users get no live updates, and no notification is written. The worker reads three queues, in this order: `broadcasts` (live updates), `notifications` (who is told about replies and mentions, and the clean-up when access changes) and `default` (the rest). Keep the three names in the `--queue` option (ADR 0042).
- The worker of the notification mail, which reads only the queue `mail` (ADR 0054). A send to a slow mail server never holds up the live updates. Without it, notification emails wait in the queue, and `php artisan app:health` fails after 10 minutes. A notification email that waits for more than 2 hours is not sent late: the worker fails it (`failed_jobs`), and the notification stays in the app. On a host that runs one worker only, set `MAIL_QUEUE=default` instead (step 4).
- The WebSocket server (Laravel Reverb)
- The scheduler, which runs the scheduled tasks of `routes/console.php` (see the list below). Without it, unused uploaded images are never deleted.

Keep the two queue workers, Reverb and the scheduler running with a process manager. This is an example for Supervisor (`/etc/supervisor/conf.d/knowii.conf`). Change the path and the user:

```ini
[program:knowii-queue]
command=php /var/www/knowii/artisan queue:work --queue=broadcasts,notifications,default --sleep=3 --tries=3
user=www-data
autostart=true
autorestart=true
stopwaitsecs=60

[program:knowii-queue-mail]
command=php /var/www/knowii/artisan queue:work --queue=mail --sleep=3 --tries=3
user=www-data
autostart=true
autorestart=true
stopwaitsecs=60

[program:knowii-reverb]
command=php /var/www/knowii/artisan reverb:start
user=www-data
autostart=true
autorestart=true

[program:knowii-scheduler]
command=php /var/www/knowii/artisan schedule:work
user=www-data
autostart=true
autorestart=true
```

Reverb opens one file descriptor per connection. To let it accept many connections, set `minfds=10000` in the `[supervisord]` section of `/etc/supervisor/supervisord.conf`, then restart Supervisor.

The `knowii-scheduler` program (`schedule:work`) runs the scheduler every minute. If your host has no process manager, use a cron entry for the `www-data` user instead (`crontab -u www-data -e`), never both:

```
* * * * * cd /var/www/knowii && php artisan schedule:run >> /dev/null 2>&1
```

The scheduled tasks:

1. `uploads:prune`, every day at 03:30: deletes the uploaded images that no post or comment used for 7 days (the member removed them from the text, cancelled the post, or deleted it). The 7 days protect a post that a member keeps open for days before saving it (#1273). Run `php artisan uploads:prune --dry-run` to see the list first.
2. `retention:prune`, every day at 03:40: deletes the invitations 30 days after they expire, the API tokens 30 days after their expiry date, the failed background jobs after 7 days, the notifications 60 days after they were read (90 days after they were written when never read), the expired password reset tokens, and the expired sessions and cache entries when they are stored in the database (#1306). Run `php artisan retention:prune --dry-run` to see the counts first.
3. `mailing-lists:prune`, every day at 03:50: deletes the waitlist contacts 12 months after the signup, and the unconfirmed or unsubscribed contacts of the waitlist and the newsletter 30 days after their last change (EmailOctopus API). It does nothing while `EMAILOCTOPUS_API_KEY` is empty.
4. `entitlements:sync`, every day at 02:30 (#1386): queues a read of the entitlements for every connected account of the sign-in provider, one a second, and clears the access group memberships that rules gave in a community that is not listed in `OIDC_ENTITLEMENT_COMMUNITIES` any more (or everywhere, and deletes the stored keys, when `OIDC_ENTITLEMENTS_URL` is empty). The ids of the change calls of the provider are kept 24 hours in the cache and forgotten with it.
5. `mail:check-budget`, every hour (#1477): compares the emails of the month with the quota, sends the alert at 80% to `MAIL_ALERT_TO`, and holds or releases the notification emails (see "Mail volume" in step 4). It does nothing without a quota. Run `php artisan mail:check-budget` to see the numbers.

The periods come from `config/retention.php` (see step 4).

If you want automatic backups, schedule `php artisan backup:run` too (the `spatie/laravel-backup` package is installed; see `config/backup.php`).

## 7. Configure nginx

This example uses PHP-FPM 8.5 on Ubuntu. Change the domain, the paths, and the certificate files.

```nginx
# Application
server {
    listen 443 ssl;
    listen [::]:443 ssl;
    server_name <YOUR_DOMAIN> www.<YOUR_DOMAIN>;
    root /var/www/knowii/public;
    index index.php;

    ssl_certificate     /etc/ssl/<YOUR_DOMAIN>/fullchain.pem;
    ssl_certificate_key /etc/ssl/<YOUR_DOMAIN>/privkey.pem;

    client_max_body_size 10m;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.5-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}

# WebSockets (Laravel Reverb)
server {
    listen 443 ssl;
    listen [::]:443 ssl;
    server_name ws.<YOUR_DOMAIN>;

    ssl_certificate     /etc/ssl/<YOUR_DOMAIN>/fullchain.pem;
    ssl_certificate_key /etc/ssl/<YOUR_DOMAIN>/privkey.pem;

    location / {
        proxy_http_version 1.1;
        proxy_set_header Host $http_host;
        proxy_set_header Scheme $scheme;
        proxy_set_header SERVER_PORT $server_port;
        proxy_set_header REMOTE_ADDR $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "Upgrade";
        proxy_pass http://127.0.0.1:4201;
    }
}

# Redirect HTTP to HTTPS
server {
    listen 80;
    listen [::]:80;
    server_name <YOUR_DOMAIN> www.<YOUR_DOMAIN> ws.<YOUR_DOMAIN>;
    return 301 https://$host$request_uri;
}
```

The `www-data` user (or the user of PHP-FPM) must be able to write to `storage/` and `bootstrap/cache/`.

## 8. Update your instance

To update to a new release, run these commands from the root of the repository:

```bash
git fetch --tags
git checkout <new release tag>
composer install --no-dev --optimize-autoloader --no-interaction
npm ci
npm run build
php artisan migrate --force
php artisan optimize
php artisan queue:restart    # both workers restart with the new code
php artisan reverb:restart   # the WebSocket server restarts with the new code
sudo systemctl reload php8.5-fpm
```

From the release that adds the queue `mail` (#1477, ADR 0054), run the second queue worker of step 6 (`knowii-queue-mail`), or set `MAIL_QUEUE=default`. With Docker, `up -d` starts the new service `queue-mail`.

Some releases need one more step after the migration. The release that adds smaller sizes for uploaded images ([ADR 0040](documentation/adr/0040-stored-sizes-of-uploaded-images.md)) needs this, in this order:

```bash
php artisan migrate --force
php artisan uploads:build-sizes     # builds the smaller versions of the images that exist
php artisan content:render-again    # renders the stored HTML again, so old content gets srcset and galleries
```

Both commands accept `--dry-run`, and you can run them again: they only do what is left to do. A file that is missing is reported and skipped, never deleted.

Read the release notes (`CHANGELOG.md`) before you update. They tell you about new or changed environment values.

### Security fixes

1. A security fix comes in a new release. Only the newest release gets fixes.
2. Each fix has a security advisory: https://github.com/knowii-oss/knowii/security/advisories. It says which versions are concerned and what to do.
3. To get a message for each advisory: on the page of the repository, press "Watch", choose "Custom", and select "Security alerts".
4. If you find a security problem, read [`SECURITY.md`](SECURITY.md). Do not open a public issue.
5. Name the security contact of your instance with `SECURITY_TXT_CONTACTS`: the application serves it in `/.well-known/security.txt`. A report about your instance (its server, its configuration) goes to you. A report about the code goes to this project, as `SECURITY.md` says.

## Docker

Instead of steps 1 to 8, you can run Knowii with Docker. The repository has a production image (`Dockerfile`) and one Compose project (`compose.production.yaml`) with these services: `app` (the web server, FrankenPHP), `queue` (the queue worker), `queue-mail` (the worker of the notification mail), `reverb` (WebSockets), `scheduler` (the scheduled tasks) and `postgres` (PostgreSQL 18). The application services use the same image with another command. You need Docker with the Compose plugin, a reverse proxy that ends the TLS (see below), and nothing else: no PHP, Composer or Node.js on the server. The image never runs development tools. See [ADR 0046](documentation/adr/0046-production-image-and-compose.md).

### Get the image

GitHub Actions builds the image and, when the repository variable `PUBLISH_IMAGE` is `true`, pushes it to the container registry of the repository: `ghcr.io/knowii-oss/knowii:main` (the latest `main`), `ghcr.io/knowii-oss/knowii:sha-<short>` (one commit). Pin a `sha-` tag in production. To build it yourself instead, run `docker build -t knowii:local .` and set `KNOWII_IMAGE=knowii:local`.

### First start

1. Copy `compose.production.yaml` and `.env.production.example` to a folder of the server. Name the second one `.env`.
2. Edit `.env`: replace every `CHANGE_ME`, and set `COMPOSE_PROJECT_NAME` (mandatory: one name per environment, so that two environments never share volumes), `APP_URL`, `SESSION_DOMAIN`, `REVERB_HOST` and `REVERB_ALLOWED_ORIGINS` to your domain. Generate `APP_KEY` with `docker run --rm --entrypoint php ghcr.io/knowii-oss/knowii:main artisan key:generate --show`. `ROBOTS_ALLOW_INDEXING=false` is the safe default: set it to `true` only on the public instance. Step 4 explains the other values, the `OIDC_*` ones too: register `https://<your domain>/auth/oidc/callback` at the provider.
3. Start the stack: `docker compose -f compose.production.yaml up -d`. The `app` service runs the migrations, then the other application services start. `docker compose -f compose.production.yaml ps` shows `healthy` for every service after about a minute.
4. Put the reverse proxy in front (next section). The app listens on `127.0.0.1:3400` and Reverb on `127.0.0.1:3401` (`KNOWII_APP_PORT` and `KNOWII_REVERB_PORT` change them). No other port is published, and the database has none.

### The proxy

One host name serves the app and Reverb. The proxy sends `/app/` and `/apps/` (the WebSocket connection and the Reverb API) to Reverb, and everything else to the app. In `.env`, `REVERB_HOST` is that host name, `REVERB_PORT=443` and `REVERB_SCHEME=https`: the browser connects to the proxy, not to the container. Set `TRUSTED_PROXIES=*` when the proxy runs on the same server (the ports are published on `127.0.0.1`, so only the proxy reaches them). An nginx example (change the domain and the certificate files):

```nginx
map $http_upgrade $connection_upgrade {
    default upgrade;
    ''      close;
}

server {
    listen 443 ssl;
    listen [::]:443 ssl;
    server_name app.example.com;

    ssl_certificate     /etc/ssl/app.example.com/fullchain.pem;
    ssl_certificate_key /etc/ssl/app.example.com/privkey.pem;

    # Same as upload_max_filesize and post_max_size of docker/production/php.ini (50 MB of course materials by default)
    client_max_body_size 55m;

    # WebSockets (Reverb)
    location ~ ^/apps?/ {
        proxy_http_version 1.1;
        proxy_set_header Host $http_host;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection $connection_upgrade;
        proxy_read_timeout 3600s;
        proxy_pass http://127.0.0.1:3401;
    }

    # The application
    location / {
        proxy_http_version 1.1;
        proxy_set_header Host $http_host;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_pass http://127.0.0.1:3400;
    }
}
```

### Update

Change `KNOWII_IMAGE` in `.env` to the new tag (or keep `main`), then:

```bash
docker compose -f compose.production.yaml pull
docker compose -f compose.production.yaml up -d --wait
docker compose -f compose.production.yaml exec -T app php artisan app:health
```

The `app` service runs the migrations when it starts, and the other services restart on the new image after it is healthy. Read the release notes (`CHANGELOG.md`) first: some releases need the extra commands of step 8, which you run as shown below. To go back, set the previous tag and run `up -d --wait` again: the migrations of the newer version stay in the database, so only go back to a version that tolerates them.

### Health checks

`/up` answers without touching the database: Docker and the proxy use it. `php artisan app:health` is the deep check: it tests the database, the migrations, the cache, the queue connection, the mail worker (`mail-queue`, with the `database` queue only: it fails when a job of the mail queue waits for more than 10 minutes and the worker ended no job of that queue in the same time, so a long backlog behind the rate limit is not a failure) and Reverb, prints one line per check and exits with 1 when one fails. It runs only on the command line, so it shows no detail to the internet.

### Run an Artisan command

```bash
docker compose -f compose.production.yaml exec app php artisan about
docker compose -f compose.production.yaml exec app php artisan uploads:build-sizes
docker compose -f compose.production.yaml exec app php artisan communities:export <community-slug>
```

The export commands write under `storage/app`, which is the `storage` volume.

### Backup

Back up the two named volumes: the database (`<project>_postgres-data`) and the files (`<project>_storage`, where `<project>` is `COMPOSE_PROJECT_NAME`). For the database, a dump is safer than a copy of the files of a running server:

```bash
docker compose -f compose.production.yaml exec -T postgres sh -c 'pg_dump --username="$POSTGRES_USER" --dbname="$POSTGRES_DB"' | gzip > knowii-db-$(date +%F).sql.gz
docker run --rm -v <project>_storage:/data:ro -v "$PWD":/backup alpine tar czf /backup/knowii-storage-$(date +%F).tar.gz -C /data .
```

To restore, start an empty stack, then load the dump with `psql` in the `postgres` service and unpack the archive into the `storage` volume. Keep the snapshots as long as `RETENTION_BACKUPS_*` say.

### What the image changes compared to the host-based guide

1. The environment lives in the container, not in a `.env` file inside the image. The caches (`config`, `route`, `view`, `event`) are built at every start.
2. The `VITE_*` values are not read: the container takes the Reverb settings and the app name of the browser build from `REVERB_*` and `APP_NAME` at start.
3. PHP uses FrankenPHP in classic mode. The upload limits are in `docker/production/php.ini` (50 MB by default, like `COURSE_MATERIALS_MAX_MB`): raise them together with that value.
4. Logs go to stderr: `docker compose -f compose.production.yaml logs -f app`.

## Laravel Forge (optional)

If you use [Laravel Forge](https://forge.laravel.com/) to manage your server, use this deployment script:

```bash
cd <path to knowii>

$FORGE_COMPOSER install --no-dev --no-interaction --prefer-dist --optimize-autoloader
npm ci
npm run build

( flock -w 10 9 || exit 1
    echo 'Restarting FPM...'; sudo -S service $FORGE_PHP_FPM reload ) 9>/tmp/fpmlock

if [ -f artisan ]; then
    $FORGE_PHP artisan migrate --force
    $FORGE_PHP artisan optimize
    $FORGE_PHP artisan queue:restart
    $FORGE_PHP artisan reverb:restart
fi
```

If you enable Laravel Reverb through Forge with a Cloudflare Origin certificate, Forge can create an nginx configuration for the WebSocket sub-domain that listens on port 80 instead of 443. In that case, edit `/etc/nginx/forge-conf/<YOUR_DOMAIN>/after/reverb.conf`: make it listen on 443 with the certificate of your main site (the file paths are in the main nginx configuration of the site), and proxy to `http://localhost:4201` as in step 7. Reference: [How to make Laravel Reverb work on Laravel Forge](https://tonymasek.com/articles/how-to-make-laravel-reverb-work-on-laravel-forge).
