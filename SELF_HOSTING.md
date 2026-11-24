# Self-hosting Knowii

Knowii is free and open source (AGPL). You can install it on your own server. This guide explains how to install, configure, run, and update a Knowii instance.

## What you need

| Component                     | Version                      | Notes                                                                          |
| ----------------------------- | ---------------------------- | ------------------------------------------------------------------------------ |
| Linux server with root access | Ubuntu 24.04 LTS recommended | Other distributions work, but the commands in this guide are for Ubuntu.       |
| PHP (CLI and FPM)             | 8.5                          | The required version is in `composer.json`. See the extensions below.          |
| Composer                      | 2                            |                                                                                |
| Node.js and npm               | Node.js 26, npm 11           | Only to build the front-end assets. The Node.js version is in `.nvmrc`.        |
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

Copy `.env.example` to `.env`, then change the values below. The other values in `.env.example` are correct for most instances. The values that describe your instance (its address, its operator, its contact address, its footer) are in ["Instance values"](#instance-values) at the end of this step: two of them are required in production.

| Key                                                                 | Value                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                 |
| ------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `APP_ENV`                                                           | `production`                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                          |
| `APP_DEBUG`                                                         | `false`                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                               |
| `APP_KEY`                                                           | Generated by `php artisan key:generate` (step 5)                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                      |
| `TRUSTED_HOSTS`                                                     | Optional: other host names that the application answers for, comma-separated, for example `www.<YOUR_DOMAIN>`. The host of `APP_URL` and the loopback names (`localhost`, `127.0.0.1`, `[::1]`) are always accepted. Another host name gets the answer `400`. A name matches as a whole: a sub-domain is another name. An entry is a name only: no scheme, no port and no dot at the end. A second name is served on the same port as `APP_URL`. The simple way for a `www` name is a redirect to the main name in the web server (step 7). To serve the application under both names, the second name goes here and in `SANCTUM_STATEFUL_DOMAINS` (below the table). A load balancer or a monitor that calls the application by an address needs that address in the list (an IPv6 address in brackets, for example `[2001:db8::1]`) |
| `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`   | The database from step 3                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                              |
| `MAIL_MAILER`, `MAIL_FROM_ADDRESS`                                  | Your email provider and sender address. For Resend, also set `RESEND_KEY`.                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                            |
| `MAIL_QUEUE`                                                        | `mail`: the queue of the notification mail, read by its own worker (step 6). `default` puts it on the first worker, for a host that runs one worker only: the rate limit and the hold still apply. Use one of these two names: no worker of this guide reads another queue                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                            |
| `MAIL_RATE_LIMIT_PER_SECOND`                                        | `5`: notification mail sent per second, for all mail workers together. Resend allows 10 requests a second per team: the half leaves room for the sign-in links and the other account mail. `0`: no limit (your own SMTP server, `log`)                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                |
| `MAIL_MONTHLY_QUOTA`                                                | Optional: the mail that the plan of your mail provider allows each month. If other products send with the same account of your mail provider, set this value lower than the limit of your plan. Empty: the monthly limit that Resend gives (with `MAIL_MAILER=resend`), else no alert and no hold. See "Mail volume" below                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                            |
| `MAIL_NOTIFICATION_HOLD_PERCENT`                                    | `95`: notification mail stops at this share of the monthly quota, so that the sign-in links keep a reserve (kept between `1` and `100`; `100`: no reserve)                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                            |
| `MAIL_ALERT_TO`                                                     | Optional: the addresses that get the alert of the mail budget, separated by commas (an operator or a role address, never a member). Empty: the warning in the log is the alert                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                        |
| `MAIL_INVITATIONS_PER_COMMUNITY_PER_DAY`                            | `1000`: a community sends at most this many invitation mails a day (the last 24 hours). Every invitation mail counts: a single invitation, a row of an import from a file, a send again. A whole number of at least 1 (an empty or a wrong value gives 1000). See "Mail volume" below                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                 |
| `MAIL_INVITATIONS_PER_ACCOUNT_PER_DAY`                              | `1000`: an account sends at most this many invitation mails a day, in all its communities together (the account of the manager who sends). The same count and values as the line above                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                |
| `MAIL_INVITATIONS_PER_INSTANCE_PER_DAY`                             | `1000`: the whole instance sends at most this many invitation mails a day, in all its communities and accounts together (#1713). The same count and values as the lines above. See "Mail volume" below for a plan with a daily quota                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                  |
| `RESEND_WEBHOOK_SECRET`                                             | Optional: the signing secret of the webhook of Resend (`whsec_...`), for the reports of bounces and complaints (#1478). Empty: the webhook route answers 404. A value that is not `whsec_` and base64 also turns the route off, with a warning in the log once a day. See "Bounces and complaints" below                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                              |
| `MAIL_PROVIDER_REMOVALS_PER_HOUR`, `MAIL_PROVIDER_REMOVALS_PER_DAY` | `1` and `3`: before a sign-in link, a password reset, an address check or the confirmation of a new address to an address that Resend lists after a bounce or a complaint, the platform asks Resend to take the address off its list, at most this many times per address in an hour and in 24 hours ("Try this address again" counts too). Above the cap the email still goes, and Resend skips it                                                                                                                                                                                                                                                                                                                                                                                                                                   |
| `LEGAL_POSTAL_ADDRESS`                                              | Optional: the postal address of the operator, for the footer of the notification emails (#1478): "<operator>, <postal address>". Empty: the footer shows the operator only                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                            |
| `SESSION_DOMAIN`                                                    | Empty (or leave the key out): the cookies belong to the one host of the instance. See ["The cookies belong to one host"](#the-cookies-belong-to-one-host) below                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                       |
| `SESSION_COOKIE`                                                    | `__Host-knowii_session`: in production the name starts with `__Host-`. See ["The cookies belong to one host"](#the-cookies-belong-to-one-host) below                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                  |
| `SESSION_PATH`                                                      | `/` (the default): a `__Host-` cookie needs it                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                        |
| `SESSION_SECURE_COOKIE`                                             | `true`: a `__Host-` cookie needs it                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                   |
| `SESSION_ENCRYPT`                                                   | `true` (the default): the session is encrypted by default, with `APP_KEY`; an empty value keeps it on. `false`, `0`, `off` or `no` (in any case) turn it off and keep the values of the session in clear in the table `sessions`. A change of this value ends every open session                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                      |
| `REVERB_APP_ID`, `REVERB_APP_SECRET`                                | Unique random values                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                  |
| `REVERB_APP_KEY`                                                    | A unique value, for example `knowii-<your instance name>`                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                             |
| `REVERB_HOST`                                                       | `ws.<YOUR_DOMAIN>`                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                    |
| `REVERB_PORT`                                                       | `443`                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                 |
| `REVERB_SCHEME`                                                     | `https`                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                               |
| `REVERB_SERVER_HOST`, `REVERB_SERVER_PORT`                          | `127.0.0.1` and `4201`: the WebSocket server listens locally, and nginx forwards `ws.<YOUR_DOMAIN>` to it                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                             |
| `REVERB_ALLOWED_ORIGINS`                                            | `<YOUR_DOMAIN>`                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                       |
| `EMAILOCTOPUS_API_KEY`                                              | Optional: the key of the EmailOctopus account of the newsletter form (and of the old waitlist list). Leave empty to hide the form.                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                    |
| `EMAILOCTOPUS_WAITLIST_LIST_ID`                                     | Optional: the list of the old waitlist, which takes no new address (#1574). Only `mailing-lists:prune` reads it, to delete its contacts at the end of their retention period. Leave empty on a new instance.                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                          |
| `EMAILOCTOPUS_NEWSLETTER_LIST_ID`                                   | Optional: the newsletter form in the footer (#938). Leave empty to hide the form. It also needs `EMAILOCTOPUS_API_KEY` and the Turnstile keys. Use a list for this instance only: `mailing-lists:prune` deletes its unconfirmed and unsubscribed contacts (ADR 0030).                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                 |
| `EMAILOCTOPUS_NEWSLETTER_CONTACT_STATUS`                            | `pending` (the visitor confirms the address by email, recommended) or `subscribed`                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                    |
| `TURNSTILE_SITE_KEY`, `TURNSTILE_SECRET_KEY`                        | Cloudflare Turnstile keys (bot check). Required by the newsletter form: without them, the form is hidden. The sign-up form uses them when they are set.                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                               |
| `PLAUSIBLE_DOMAIN`, `PLAUSIBLE_PROXY_URL`                           | Optional: Plausible statistics (cookieless) through a proxy that receives `/api/v1/event` (for example a Cloudflare Worker). The pages send their page views there themselves and load no script of another host. A page view holds the origin and the path of the page, and of the query only the campaign keys (`utm_source`, `utm_medium`, `utm_campaign`, `utm_content`, `utm_term`, `ref`, `source`). The statistics never see the address of a page that holds a link token or a signature. Empty: no page view is sent. If you turn it on, list your provider in your privacy policy                                                                                                                                                                                                                                           |
| `OIDC_ISSUER`, `OIDC_CLIENT_ID`, `OIDC_CLIENT_SECRET`               | Optional: sign in with an OpenID Connect provider (#1385). The three values turn it on: leave one empty and there is no button and no route. See "Sign in with an OpenID Connect provider" below.                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                     |
| `OIDC_ENTITLEMENTS_URL`                                             | Optional: turns on the entitlements of the provider (#1386). The address of the read of one person, `https`, with `{subject}` once in the path. Without it the feature does not exist. See "Entitlements of the provider feed the access groups" below.                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                               |
| `OIDC_ENTITLEMENT_COMMUNITIES`                                      | Optional: the slugs of the communities (separated by commas) whose owner and admins may give an access group by an entitlement key. Empty means none.                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                 |
| `OIDC_ENTITLEMENTS_SCOPE`                                           | Optional: the scope of the client credentials grant that reads the entitlements (default `entitlements:read:subjects`).                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                               |
| `OIDC_ENTITLEMENTS_RESOURCE`                                        | Optional: the audience or resource parameter of that token request, when the provider needs one.                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                      |
| `OIDC_ENTITLEMENTS_SIGN_IN_SCOPE`                                   | Optional: the scope that the sign-in also asks when the feature is on (default `entitlements:read`): the provider links the client to the person only then.                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                           |
| `OIDC_FRESH_SIGN_IN_SECONDS`                                        | Optional: an account without a password confirms the deletion of the account with a new sign-in at the provider (#1521). The sign-in must be at most this many seconds old, and the deletion must follow within this many seconds after it (default `300`, kept between `60` and `900`).                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                              |
| `OIDC_SCOPES`                                                       | Optional: the scopes (default `openid profile email`, `offline_access` is never asked). The text of the button (`OIDC_LABEL`) is in "Instance values".                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                |
| `ROBOTS_ALLOW_INDEXING`                                             | `true` lets search engines index the public pages (default in production). `false` on a test or private instance: `robots.txt` refuses every crawler and every page sends `noindex`                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                   |
| `SECURITY_TXT_EXPIRES`, `SECURITY_TXT_POLICY`                       | Optional: the date after which the file is stale (ISO 8601; empty: 180 days after the current day), and the page of your security policy (`https`; empty: the security page of the application when `storage/app/legal/security.md` exists, else no `Policy` line)                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                    |
| `SECURITY_TXT_ENCRYPTION`, `SECURITY_TXT_ACKNOWLEDGMENTS`           | Optional (`https`): your public key for encrypted reports, and the page that thanks the reporters. `SECURITY_TXT_PREFERRED_LANGUAGES`: the languages of a report that you read (default `en`)                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                         |
| `COMMUNITY_RESERVED_NAMES_OWNERS`                                   | Optional: the email addresses of the accounts (verified accounts) that can use a reserved community name, separated by commas. The names (`COMMUNITY_RESERVED_NAMES`) are in "Instance values".                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                       |
| `COURSE_MATERIALS_DISK`, `COURSE_MATERIALS_MAX_MB`                  | Optional: the disk of the files that course managers attach to courses and lessons, and the maximum size of a file in megabytes (default `local` and `50`). The disk must be private: `local` (`storage/app`, never served by the web server) or an S3-compatible disk of `config/filesystems.php`, never `public`. Members download the files through the application, which checks their access (ADR 0037). Raise `upload_max_filesize` and `post_max_size` of PHP and `client_max_body_size` of nginx to the same size, and include the disk in your backups                                                                                                                                                                                                                                                                       |
| `LOG_STACK`, `LOG_DAILY_DAYS`                                       | `daily` and `14`: one log file per day in `storage/logs`, deleted after 14 days (the period of the privacy policy). With `stderr`, rotate the logs of your process manager the same way                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                               |

Mail volume (#1477, [ADR 0054](documentation/adr/0054-email-delivery-the-mail-queue-the-budget-bounces-and-the-one-click-stop.md)): the mail provider limits how much the platform sends.

1. Resend accepts 10 requests a second per team, for all its API keys together. The free plan allows 3 000 emails a month and 100 a day; the paid plans have a higher monthly quota and no daily one. Over a quota, Resend refuses every email, the sign-in links included.
2. The quota is per team at the provider, not per instance. If other products send with the same account of your mail provider, set `MAIL_MONTHLY_QUOTA` lower than the limit of your plan.
3. `mail:check-budget` runs every hour (step 6). It counts the emails of the month: the number of Resend when it can read the usage of the team, else the count of this instance (every email sent, whatever the mailer). At 80% of the quota it writes a warning in the log and sends one alert to `MAIL_ALERT_TO`. At `MAIL_NOTIFICATION_HOLD_PERCENT` it holds the notification emails until the next month, and sends a second alert. The sign-in links and the other account emails keep going. A refusal of Resend for its monthly or its daily quota holds the notification emails too, until the next month or the next day (00:00 UTC). Raise the quota and the next check ends the hold. When Resend cannot be read (a timeout, for example), the check keeps a hold that is set and tries again at the next hour.
4. To read the usage, the API key of `RESEND_KEY` needs the right to read it. Without that right, the check uses the count of this instance and writes a warning in the log every hour (its `error_type` is `restricted_api_key`).
5. Without a quota (no `MAIL_MONTHLY_QUOTA` and a mailer that is not Resend, for example `smtp` or `log`), there is no alert and no hold. The rate limit works with every mailer.
6. An account, a community and the whole instance send at most 1000 invitation mails a day (`MAIL_INVITATIONS_PER_ACCOUNT_PER_DAY`, `MAIL_INVITATIONS_PER_COMMUNITY_PER_DAY`, `MAIL_INVITATIONS_PER_INSTANCE_PER_DAY`), whatever the quota. On a plan of the mail provider with a daily quota (the free plan of Resend: 100 a day), set the instance cap well below it, so that the sign-in links keep room. Past a cap, an invitation is refused with the time from when it works again, and an import from a file is refused as a whole. While the notification emails are held, a single invitation is refused with the end of the hold. An import from a file also checks the mail left this month before the hold: with `MAIL_MONTHLY_QUOTA`, else with the limit and the use of Resend that the last `mail:check-budget` read.

Bounces and complaints (#1478, [ADR 0054](documentation/adr/0054-email-delivery-the-mail-queue-the-budget-bounces-and-the-one-click-stop.md)): Resend reports the emails that did not arrive and the spam reports to a webhook. Without it, the platform never learns that an address is dead.

1. In the dashboard of Resend, add a webhook with the address `https://<YOUR_DOMAIN>/webhooks/resend` and subscribe it to these four events only: `email.bounced`, `email.complained`, `email.suppressed`, `email.delivered`. Copy its signing secret (`whsec_...`) to `RESEND_WEBHOOK_SECRET`. The webhook gets the reports of every product of the team; a report about a mail that this instance did not log counts only when it comes from `MAIL_FROM_ADDRESS`.
2. The route checks the signature and the time of each call: the clock of the server must be right (NTP). A call more than 5 minutes off is refused.
3. A wrong secret gives 401 to every call. Resend retries a failed call for about a day, then disables the webhook and sends an email to the team: check the secret when that email comes.
4. A hard bounce, a spam report, the list of Resend, or 3 temporary failures in a row stop the notification emails to the address. The sign-in link, the password reset and the address check still go: before each one, the platform asks Resend to take the address off its list (within `MAIL_PROVIDER_REMOVALS_PER_HOUR` and `MAIL_PROVIDER_REMOVALS_PER_DAY`). This needs an API key of `RESEND_KEY` with full access: a key that can only send cannot change the list, and the email is then skipped by Resend (a warning in the log). The member sees the state on the notification settings page and can choose "Try this address again" or change the address.
5. A report on an invitation email is shown on the invitation to the managers; it never stops the emails of an account.
6. To read the state of an address: the table `email_suppressions` (the account, the address, the reason and the date) and the suppression list in the dashboard of Resend. To change the address of a member who cannot receive email, check the identity of the person first.
7. The stop links of the notification emails are signed with `APP_KEY`. When you change `APP_KEY`, keep the old key in `APP_PREVIOUS_KEYS`, so that the links of the emails that members already have keep working.
8. Without `RESEND_WEBHOOK_SECRET` (for example with `smtp` or `log`), nothing reports bounces: no address is ever stopped. The stop links and their headers work with every mailer.

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

| Key                                                                                | Value                                                                                                                                                                                                                 |
| ---------------------------------------------------------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `RETENTION_EXPIRED_INVITATIONS_DAYS`                                               | Days an expired invitation stays before `retention:prune` deletes it (default `30`)                                                                                                                                   |
| `RETENTION_INVITATION_IMPORT_PREVIEWS_HOURS`                                       | Hours an import of invitations from a file that nobody sent stays before `retention:prune` deletes it (default `24`). A sent import stays as long as an invitation (#1390)                                            |
| `RETENTION_EXPIRED_API_TOKENS_DAYS`                                                | Days an expired API token stays before `retention:prune` deletes it (default `30`)                                                                                                                                    |
| `RETENTION_FAILED_JOBS_DAYS`                                                       | Days a failed background job keeps its data (default `7`)                                                                                                                                                             |
| `RETENTION_UNUSED_UPLOADS_DAYS`                                                    | Days an uploaded image that no content uses stays (default `7`)                                                                                                                                                       |
| `RETENTION_WAITLIST_MONTHS`                                                        | Months a waitlist contact stays in EmailOctopus after the signup (default `12`)                                                                                                                                       |
| `RETENTION_INACTIVE_CONTACTS_DAYS`                                                 | Days an unconfirmed or unsubscribed EmailOctopus contact stays after its last change (default `30`)                                                                                                                   |
| `RETENTION_NOTIFICATIONS_READ_DAYS`                                                | Days a notification stays after the member read it (default `60`)                                                                                                                                                     |
| `RETENTION_NOTIFICATIONS_UNREAD_DAYS`                                              | Days a notification stays when the member never read it, counted from its creation (default `90`)                                                                                                                     |
| `RETENTION_EVENT_ANSWERS_DAYS`                                                     | Days the answer of a member to an event (Yes, Replay only or No) stays after the end of the event (default `90`)                                                                                                      |
| `RETENTION_INVITE_LINK_USES_DAYS`                                                  | Days the record of a join through an invite link (the link, the member, the date) stays after the join (default `365`)                                                                                                |
| `RETENTION_EXPORTS_DAYS`                                                           | Days an export file (`php artisan communities:export` or `users:export`) stays before `retention:prune` deletes it (default `7`)                                                                                      |
| `RETENTION_PENDING_EMAIL_DAYS`                                                     | Days a new email address waits for its confirmation before it no longer counts and `retention:prune` removes it (default `7`)                                                                                         |
| `RETENTION_MAIL_DELIVERIES_DAYS`                                                   | Days a row of the delivery log of the emails (the kind, the state, the date and the id of the mail provider, no text) stays (default `30`)                                                                            |
| `RETENTION_MAIL_WEBHOOK_EVENTS_DAYS`                                               | Days the id of a report of the mail provider stays (default `30`)                                                                                                                                                     |
| `RETENTION_EMAIL_SOFT_BOUNCES_DAYS`                                                | Days a count of temporary failures of an address that stopped nothing stays after the last failure (default `90`). The stop of the notification emails after a report has no period: it lasts while the address stays |
| `RETENTION_BACKUPS_DAILY`, `RETENTION_BACKUPS_WEEKLY`, `RETENTION_BACKUPS_MONTHLY` | The snapshots your backup script keeps (default `7`, `4` and `12`). Knowii does not run the backups: use the same numbers in your script (`restic forget --keep-daily 7 --keep-weekly 4 --keep-monthly 12`)           |

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

### The cookies belong to one host

The session cookie belongs to the one host of the instance (`__Host-` prefix, no domain, [ADR 0068](documentation/adr/0068-the-cookies-belong-to-one-host.md)): `SESSION_COOKIE=__Host-knowii_session`, `SESSION_DOMAIN` empty, `SESSION_SECURE_COOKIE=true` and `SESSION_PATH=/`. The cookie `XSRF-TOKEN` and the cookie of "Remember me" have no domain either. Why: a cookie of a domain goes to every host under that domain, and another host of the domain could set one. A browser accepts a cookie with the `__Host-` prefix only from the host of the page, over https, without a domain and with the path `/`.

In production, the instance answers `503` on every address, the health address too, with a text that names the two values, while `SESSION_DOMAIN` has a value or the name of `SESSION_COOKIE` does not start with `__Host-`. The log gets the same text.

1. The API allows requests with cookies from its own origin only. A page of another origin calls the API with a token in the `Authorization` header (`Bearer <token>`); the answers of the API name no origin that may send cookies.
2. After a sign-in, the application goes only to an address of its own host names (the host of `APP_URL` and the names of `TRUSTED_HOSTS`).
3. With a second host name (`TRUSTED_HOSTS`), each name has its own cookies: a member who signs in on one name is not signed in on the other. The redirect of step 7 avoids this.
4. One switch for another layout: `SESSION_ALLOW_DOMAIN_COOKIE=true` turns the check of the two values off, for an instance that must share its session with the other hosts of its domain. You then accept that every host under the domain of the cookie receives the session cookie and can set one. Leave it out in every other case.

Do not set `SANCTUM_STATEFUL_DOMAINS` to an empty value. The front-end calls the API with the session cookie, so the API must treat requests from your domain as first-party ("stateful"). If the value is empty, every API call fails with `401 Unauthorized`. Leave the key out (the default includes the host of `APP_URL`), or set it to `<YOUR_DOMAIN>`. When the application serves a second host name (`TRUSTED_HOSTS`), set it to both names, for example `<YOUR_DOMAIN>,www.<YOUR_DOMAIN>`: the default holds the host of `APP_URL` and no second name.

Behind a reverse proxy that is not on the same machine, or that does not use `127.0.0.1`, set `TRUSTED_PROXIES` to the addresses of the proxy (comma-separated, CIDR ranges allowed), or to `*` when only the proxy can reach the application. Laravel then reads the real client address and the scheme from `X-Forwarded-For` and `X-Forwarded-Proto`. It reads the host name and the port from `X-Forwarded-Host` and `X-Forwarded-Port` too, and no other header of the proxy. The default is empty: no proxy is trusted.

The `VITE_*` values are read when you build the front-end (step 5). If you change `APP_NAME` or a `REVERB_*` value later, build the front-end again.

### Legal pages

The legal pages (#904) are the privacy policy, the terms of service, the data processing agreement, the imprint, the content policy and the security page. The code holds no legal text (#1653, [ADR 0055](documentation/adr/0055-instance-values-come-from-configuration.md)). Your instance serves only the files of the folder `storage/app/legal/`, which an update never overwrites. It reads no other folder, and no environment value changes this place.

| File                 | Page                                                                   | Address             |
| -------------------- | ---------------------------------------------------------------------- | ------------------- |
| `policy.md`          | Privacy policy                                                         | `/privacy-policy`   |
| `terms.md`           | Terms of service                                                       | `/terms-of-service` |
| `dpa.md`             | Data processing agreement                                              | `/dpa`              |
| `imprint.md`         | Imprint                                                                | `/imprint`          |
| `content-policy.md`  | Content policy                                                         | `/content-policy`   |
| `security.md`        | Security                                                               | `/security`         |
| `subprocessors.json` | The sub-processors, which the texts show through a placeholder (below) | None                |

A page without its file does not exist on your instance:

1. Its address answers 404. The application looks for the file at each request, so a file that you copy later is served at once, without a restart.
2. The footer links only the pages that exist. It has no "Legal" column when none exists.
3. The page that finishes a sign-up and the text under the sign-in button of the provider name only the texts that exist: the terms of service and the privacy policy, one of them, or none. Without both, the box of that page keeps "I am 16 or older".
4. Without `policy.md`, the newsletter form does not say "See the privacy policy.", and the invitation email does not name the privacy policy.
5. Without `dpa.md`, the dialog of a new community shows no line about the agreement. A shared community is still created, and no agreement is recorded for it.
6. Without `security.md`, `security.txt` has no `Policy` line, unless you set `SECURITY_TXT_POLICY`.

The application does not refuse to start without a text: the law that applies to you says which texts you need.

A file starts with a front matter block, then the text in Markdown (raw HTML is escaped):

```markdown
---
title: Privacy policy
updated: 2026-01-15
draft: false
---

# Privacy policy
```

1. `title`: the title of the page. Without it, the page uses its name.
2. `updated`: the date of the text, written `YYYY-MM-DD`. The page shows it. For `dpa.md` it is also the version of the agreement that a new shared community records: without a valid date, the acceptance is recorded without a version.
3. `draft`: `true` shows a draft notice on the page. Keep it while the text is not final.

The application replaces these placeholders. A placeholder that it does not know stays visible on the page.

| Placeholder                   | Shows                                                                                                                                                                                                                                                                                                                                                                                                                                                                      |
| ----------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `{{operator}}`                | Who runs the instance: `LEGAL_OPERATOR`                                                                                                                                                                                                                                                                                                                                                                                                                                    |
| `{{contact}}`                 | Your public contact address: `LEGAL_CONTACT_EMAIL`                                                                                                                                                                                                                                                                                                                                                                                                                         |
| `{{site}}`                    | The host of `APP_URL`, for example `community.example.com`                                                                                                                                                                                                                                                                                                                                                                                                                 |
| `{{retention.<period>}}`      | A period of `config/retention.php` (ADR 0030), so the text never drifts from what the application does: `session`, `sign_in_link`, `sign_up_link`, `password_reset_link`, `invitation`, `expired_invitations`, `api_token_default`, `expired_api_tokens`, `failed_jobs`, `unused_uploads`, `notifications_read`, `notifications_unread`, `pending_email`, `logs`, `waitlist`, `inactive_contacts`, `backups_daily`, `backups_weekly`, `backups_monthly`, `backups_longest` |
| `{{subprocessors}}`           | A table of every entry of `subprocessors.json`: for the privacy policy                                                                                                                                                                                                                                                                                                                                                                                                     |
| `{{subprocessors.community}}` | A table of the entries that receive the data of a community: for the data processing agreement                                                                                                                                                                                                                                                                                                                                                                             |
| `{{cookies.session}}`         | The name of the session cookie (`SESSION_COOKIE`)                                                                                                                                                                                                                                                                                                                                                                                                                          |
| `{{sign_in_provider}}`        | The label of the sign-in provider (`OIDC_LABEL`), or "none on this site"                                                                                                                                                                                                                                                                                                                                                                                                   |

`subprocessors.json` is a JSON list of objects, one for each third-party service that receives personal data from your instance:

```json
[
  {
    "name": "Example Hosting Ltd",
    "country": "Ireland",
    "purpose": "Hosting of the servers: application, database, uploaded files",
    "safeguard": "European Union",
    "scope": ["platform", "community"]
  }
]
```

1. `name`, `country`, `purpose` and `safeguard` (the transfer safeguard) are the four columns of the table.
2. `scope` says whose data the service receives: `community` for the data of the communities (the data processing agreement lists it), `platform` for the data of the accounts and of the public forms (only the privacy policy lists it), or both. An entry without a scope is in both tables.
3. `feature` is only for a service that an optional feature uses, for example `"feature": "AI summaries"`: the table then says that only the communities whose owner turns the feature on use it.
4. Without the file, the tables are empty. A file that is not a JSON list gives empty tables and a warning in the log. An entry that is not an object is left out.
5. A feature that you turn on can send personal data to one more service, for example the email provider, the statistics, the bot check or the newsletter list: add it to your file.

The repository has neutral templates in `resources/legal-templates/`: the six texts and `subprocessors.example.json`. The application never serves them. They are a start for your own texts, and not legal advice. They are written for an operator in the European Union (GDPR, Digital Services Act), and their delays (14 days, 30 days, 48 hours, one month) are promises: change what is not true for you. Each one is a draft (`draft: true`) without a date, names you through `{{operator}}`, `{{contact}}` and `{{site}}`, and marks what only you know with `[TODO: what to write]`. To use them: copy them to `storage/app/legal/`, rename `subprocessors.example.json` to `subprocessors.json` and list your own services in it, replace every mark, check each sentence against what your instance does, add the `updated` line, and set `draft: false`. The commands are in ["Update your instance"](#8-update-your-instance) for a host-based instance and in ["Update"](#update) of the Docker section.

The folder `deploy/knowii.net/legal/` holds the texts of the instance that the project hosts. They describe that instance and its operator: never serve them on another one.

### Instance values

These values describe your instance: who runs it, how to reach you, and what its pages name. The code holds none of them (#1653, [ADR 0055](documentation/adr/0055-instance-values-come-from-configuration.md)). An optional value that is empty hides what it feeds. A value that is empty or blank is not set.

| Value                                                                                                                                                                                                                                                                                                                                                                                | Required                         | Default outside production                        | Effect when empty                                                                                                                                                                                                                                                                                                                             |
| ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ | -------------------------------- | ------------------------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `APP_NAME`: the name of your instance, in the title of the pages, in the emails and in the structured data                                                                                                                                                                                                                                                                           | No                               | `Knowii`                                          | Never leave it empty: the pages have no title. If you change it, build the front-end again                                                                                                                                                                                                                                                    |
| `APP_URL`: the exact public address, `https://<YOUR_DOMAIN>`: the scheme, the host, and the port when it is not the default one. The application answers only for that host name and builds every link from it. An instance in a sub-folder of a host (a path after the host) is not supported                                                                                       | Yes                              | `http://localhost`                                | With a wrong host name (`http://localhost` is one), every visitor gets the answer `400`, and the log says which name was refused. Empty or without a scheme, outside the local environment: the answer `503` on every address, the health address `/up` too, with a text that says so                                                         |
| `LEGAL_OPERATOR`: who runs the instance, for example `Example Ltd (Ireland)`. The invitation email names it (GDPR art. 14)                                                                                                                                                                                                                                                           | Yes: production refuses to start | `Example Operator`                                | Production: the refusal below. `Example Operator` is refused too                                                                                                                                                                                                                                                                              |
| `LEGAL_CONTACT_EMAIL`: your public contact address (an email address). The contact page (`/contact`), the footer and the texts of the account deletion show it                                                                                                                                                                                                                       | Yes: production refuses to start | `contact@example.com`                             | Production: the refusal below. `contact@example.com` and a value that is not an email address are refused too                                                                                                                                                                                                                                 |
| `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME`: the sender of every email                                                                                                                                                                                                                                                                                                                     | Yes, to send email               | `contact@example.com` and the value of `APP_NAME` | Not set: the sender is `hello@example.com` and `Example`, and your mail provider refuses the emails                                                                                                                                                                                                                                           |
| `INSTANCE_PUBLISHER_NAME`: the publisher of the site. The footer shows `© <year> <name>.`, and the structured data names it as an organisation                                                                                                                                                                                                                                       | No                               | Empty                                             | No `©` text in the footer, and no publisher in the structured data                                                                                                                                                                                                                                                                            |
| `INSTANCE_PUBLISHER_URL`: the site of the publisher (`https`), for the structured data                                                                                                                                                                                                                                                                                               | No                               | Empty                                             | The publisher has no address in the structured data                                                                                                                                                                                                                                                                                           |
| `INSTANCE_FOOTER_NOTE`: a short text after the `©` text of the footer, for example `Made in Ireland.`                                                                                                                                                                                                                                                                                | No                               | Empty                                             | Not shown                                                                                                                                                                                                                                                                                                                                     |
| `INSTANCE_FOOTER_LINKS`: more links of the "Community" column of the footer. Entries `Label=https://address`, separated by commas, for example `Blog=https://blog.example.com,Forum=https://forum.example.com`                                                                                                                                                                       | No                               | Empty                                             | No extra link: the column holds only "Contact us"                                                                                                                                                                                                                                                                                             |
| `INSTANCE_SOCIAL_LINKS`: the icon links of the footer, also `sameAs` of the publisher in the structured data. Entries `key=https://address`, separated by commas, for example `github=https://github.com/example,linkedin=https://social.example.com/example`                                                                                                                        | No                               | Empty                                             | No icon, and no `sameAs`                                                                                                                                                                                                                                                                                                                      |
| `SECURITY_TXT_CONTACTS`: where a person reports a security problem, served in `/.well-known/security.txt` (RFC 9116). `mailto:`, `https://` or `tel:` addresses, separated by commas, in the order of preference. Name your own contact: never the one of another instance                                                                                                           | No                               | Empty                                             | The instance has no `security.txt` (404)                                                                                                                                                                                                                                                                                                      |
| `OIDC_LABEL`: the text of the sign-in button of the provider, `Continue with <label>`                                                                                                                                                                                                                                                                                                | No                               | Empty                                             | The host name of the issuer                                                                                                                                                                                                                                                                                                                   |
| `SESSION_COOKIE`: the name of the session cookie, which the privacy policy shows. `__Host-knowii_session` in production (["The cookies belong to one host"](#the-cookies-belong-to-one-host))                                                                                                                                                                                        | No                               | `knowii_session`                                  | Not set: a name built from `APP_NAME`                                                                                                                                                                                                                                                                                                         |
| `COMMUNITY_RESERVED_NAMES`: community names that only your administrators can use (your brand, your products), separated by commas. They add to the built-in list of `config/communities.php` (Support, Official, Admin, the names of the platform). For everyone else a reserved name is "taken" (ADR 0036). `COMMUNITY_RESERVED_NAMES_OWNERS` names the accounts that can use them | No                               | Empty                                             | Only the built-in names are reserved                                                                                                                                                                                                                                                                                                          |
| The legal texts: the files of `storage/app/legal/` (the privacy policy, the terms of service, the data processing agreement, the imprint, the content policy, the security page, and the sub-processors). See ["Legal pages"](#legal-pages) above                                                                                                                                    | No                               | None: the instance has no legal page              | Each page without its file answers 404, and what names it is hidden: its link in the footer, the terms and the privacy policy in the sign-up texts, the privacy policy in the newsletter form and in the invitation email, the agreement in the dialog of a new community (no agreement is recorded), and the `Policy` line of `security.txt` |

The refusal: in production (`APP_ENV=production`, which the Docker image sets unless your `.env` gives another value, so an acceptance instance needs the two values too), an instance without `LEGAL_OPERATOR` or `LEGAL_CONTACT_EMAIL` does not start.

1. Every address answers `503` with this text, the health address `/up` too: `This instance is not configured. Set LEGAL_OPERATOR and LEGAL_CONTACT_EMAIL in the environment. See SELF_HOSTING.md, section "Instance values".` The text names only the values that are missing, and the log holds it at the level `critical`.
2. The queue worker (`queue:work`, `queue:listen`), the scheduler (`schedule:work`, `schedule:run`) and Reverb (`reverb:start`) print the same text and exit with 1.
3. Every other artisan command runs, so that you can still install and repair: `key:generate`, `migrate`, `optimize`, `app:health`, `about`, the export commands.
4. After you set the values, run `php artisan optimize` again (it caches the configuration), then restart the processes of step 6.

In an entry of `INSTANCE_FOOTER_LINKS` or `INSTANCE_SOCIAL_LINKS`, an address cannot hold a comma: write `%2C` for it. An entry that a comma cuts in two is left out as a whole. The keys of `INSTANCE_SOCIAL_LINKS` are the names of the social link properties of a profile, without the prefix `social_link_` (`SOCIAL_MEDIA_LINK_PROPERTIES` in `app/Constants.php`), for example `website`, `newsletter`, `linkedin`, `youtube` and `github`. An entry with an empty label, an unknown key or an address that is not `https://`, and an entry that is in the list twice (the same label for a footer link), is left out, with a warning in the log (one a day for each wrong entry). An address with letters outside ASCII in its host is written in its ASCII form (`xn--...`).

`SEED_ADMIN_EMAIL` is for development only: the address of the first user that the database seeder creates (default `admin@example.com`). Never run the seeder on a production instance.

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
2. `retention:prune`, every day at 03:40: deletes the invitations 30 days after they expire, the API tokens 30 days after their expiry date, the failed background jobs after 7 days, the notifications 60 days after they were read (90 days after they were written when never read), a new email address that waited 7 days for its confirmation, the expired password reset tokens, and the expired sessions and cache entries when they are stored in the database (#1306). Run `php artisan retention:prune --dry-run` to see the counts first.
3. `mailing-lists:prune`, every day at 03:50: deletes the waitlist contacts 12 months after the signup, and the unconfirmed or unsubscribed contacts of the waitlist and the newsletter 30 days after their last change (EmailOctopus API). It does nothing while `EMAILOCTOPUS_API_KEY` is empty.
4. `entitlements:sync`, every day at 02:30 (#1386): queues a read of the entitlements for every connected account of the sign-in provider, one a second, and clears the access group memberships that rules gave in a community that is not listed in `OIDC_ENTITLEMENT_COMMUNITIES` any more (or everywhere, and deletes the stored keys, when `OIDC_ENTITLEMENTS_URL` is empty). The ids of the change calls of the provider are kept 24 hours in the cache and forgotten with it.
5. `mail:check-budget`, every hour (#1477): compares the emails of the month with the quota, sends the alert at 80% to `MAIL_ALERT_TO`, and holds or releases the notification emails (see "Mail volume" in step 4). It does nothing without a quota. Run `php artisan mail:check-budget` to see the numbers.

The periods come from `config/retention.php` (see step 4).

If you want automatic backups, schedule `php artisan backup:run` too (the `spatie/laravel-backup` package is installed; see `config/backup.php`).

## 7. Configure nginx

This example uses PHP-FPM 8.5 on Ubuntu. Change the domain, the paths, and the certificate files.

```nginx
# Every other host name: the default server of port 443 closes the connection (it needs no certificate)
server {
    listen 443 ssl default_server;
    listen [::]:443 ssl default_server;
    ssl_reject_handshake on;
    return 444;
}

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
        proxy_set_header X-Forwarded-For $remote_addr;
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

The first block is the default server of port 443: it closes the connection for a host name that is not in a `server_name` (`ssl_reject_handshake` needs nginx 1.19.4 or newer). nginx then sends only your host names to the application, which answers only for the host of `APP_URL`, the names of `TRUSTED_HOSTS` and the loopback names (step 4). Only one `server` block per port can be the default server: when another file of the server already has one, `nginx -t` says so.

The example serves `www.<YOUR_DOMAIN>` too. The simple way for a `www` name is a redirect to the main name in the web server: take the name out of the `server_name` of the application and redirect it to `<YOUR_DOMAIN>` in a `server` block of its own. To serve the application under both names, put the second name in `TRUSTED_HOSTS` and in `SANCTUM_STATEFUL_DOMAINS` in `.env` (`TRUSTED_HOSTS=www.<YOUR_DOMAIN>` and `SANCTUM_STATEFUL_DOMAINS=<YOUR_DOMAIN>,www.<YOUR_DOMAIN>`: the default of that value holds the host of `APP_URL` and no second name). Without one of the two ways, the application answers `400` for that name. A second name is served on the same port as `APP_URL`: a link that the application builds uses the host of `APP_URL`, or the name of `TRUSTED_HOSTS` that the request came by, with the port of `APP_URL` when it is not the default one. `ws.<YOUR_DOMAIN>` needs no entry: nginx sends it to Reverb.

The `www-data` user (or the user of PHP-FPM) must be able to write to `storage/` and `bootstrap/cache/`.

## 8. Update your instance

Read the release notes (`CHANGELOG.md`) before you update. They tell you about new or changed environment values.

From the release that takes the values of one instance from configuration (#1653, ADR 0055): set `LEGAL_OPERATOR` and `LEGAL_CONTACT_EMAIL` in `.env` before you update. Without them the new release answers `503` on every address and its workers do not start. See ["Instance values"](#instance-values) in step 4. The footer and the structured data name nobody until you set the `INSTANCE_*` values. Set them before the first command below: between the checkout and `php artisan optimize`, the instance answers `503` in any case, because the stored configuration is the one of the old release.

From the release in which the application answers only for its own host names: when people reach your instance by a second host name (for example `www.<YOUR_DOMAIN>`), set `TRUSTED_HOSTS` in `.env` before you update. Without it the new release answers `400` for that name. The host of `APP_URL` needs no entry, but `APP_URL` must be the exact public address with its scheme (`https://<YOUR_DOMAIN>`): with another host name in it the new release answers `400` to every visitor, and without a host in it `503` on every address. A load balancer or a monitor that calls the application by an address needs that address in `TRUSTED_HOSTS`. An instance that copied the proxy example of an older version of this guide takes over the new lines of the example: the proxy gives the forwarded values itself (["The proxy"](#the-proxy)). See `TRUSTED_HOSTS` in step 4 and the nginx example of step 7.

From the release in which the cookies belong to one host ([ADR 0068](documentation/adr/0068-the-cookies-belong-to-one-host.md)): before you update, set `SESSION_COOKIE=__Host-knowii_session` and empty `SESSION_DOMAIN` in `.env` (`SESSION_SECURE_COOKIE=true` and `SESSION_PATH=/` stay). Without the two values the new release answers `503` on every address in production. The new name of the cookie ends every open session once: the members sign in again. A client of the API on another origin that sent cookies now sends a token. See ["The cookies belong to one host"](#the-cookies-belong-to-one-host) in step 4.

From the release that reads the legal texts only from `storage/app/legal/` (#1653, ADR 0055): copy your texts to that folder before you update. The new release serves no text of the repository, so a legal page without a file there answers 404 (["Legal pages"](#legal-pages) in step 4). The release that you run now already reads `storage/app/legal/<file>.md` before its own file, so with the copy first no page answers 404 at any moment. It ignores `subprocessors.json`, which only the new release reads. Do what applies to you, from the root of the repository:

1. The instance that the project hosts, once, before the update:

   ```bash
   git fetch --tags
   mkdir -p storage/app/legal && git archive <new release tag> deploy/knowii.net/legal | tar -x --strip-components=3 -C storage/app/legal
   ```

   Then, for every later update, this line comes in its deploy script directly after the line that gets the new code (`git checkout` or `git pull`), so that a change of a text in the repository reaches the site with the same deploy. Before that line, the folder would still hold the texts of the release before:

   ```bash
   mkdir -p storage/app/legal && cp deploy/knowii.net/legal/*.md deploy/knowii.net/legal/subprocessors.json storage/app/legal/
   ```

2. Every other instance, before the update: each legal page that you want is a file in `storage/app/legal/`, with your own `subprocessors.json`. If you served the texts of the repository until now, they described another instance: write your own. To start from the templates:

   ```bash
   git fetch --tags
   mkdir -p storage/app/legal && git archive <new release tag> resources/legal-templates | tar -x --strip-components=2 -C storage/app/legal
   mv storage/app/legal/subprocessors.example.json storage/app/legal/subprocessors.json
   ```

   Then replace every `[TODO: ...]` mark, list your own services in `subprocessors.json`, add the `updated` line, and set `draft: false` in each file. A file that you already have in `storage/app/legal/` with the same name is replaced by the template: save it first. The release that you run serves a file of this folder as soon as it is there, with its draft notice, and a release from before this change shows `{{operator}}`, `{{contact}}` and `{{site}}` as they are written: prepare the texts in another folder and copy them when they are ready, or do it after the update. No command removes a text: to retire a page, delete its file in `storage/app/legal/`.

3. With Docker: see ["Update"](#update) in the Docker section.

Then run the commands below.

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
2. Edit `.env`: replace every `CHANGE_ME`, and set `COMPOSE_PROJECT_NAME` (mandatory: one name per environment, so that two environments never share volumes), `APP_URL`, `REVERB_HOST` and `REVERB_ALLOWED_ORIGINS` to your domain. `SESSION_DOMAIN` stays empty (["The cookies belong to one host"](#the-cookies-belong-to-one-host)). Generate `APP_KEY` with `docker run --rm --entrypoint php ghcr.io/knowii-oss/knowii:main artisan key:generate --show`. `ROBOTS_ALLOW_INDEXING=false` is the safe default: set it to `true` only on the public instance. Step 4 explains the other values, the `OIDC_*` ones too: register `https://<your domain>/auth/oidc/callback` at the provider.
3. Start the stack: `docker compose -f compose.production.yaml up -d`. The `app` service runs the migrations, then the other application services start. `docker compose -f compose.production.yaml ps` shows `healthy` for every service after about a minute.
4. Put the reverse proxy in front (next section). The app listens on `127.0.0.1:3400` and Reverb on `127.0.0.1:3401` (`KNOWII_APP_PORT` and `KNOWII_REVERB_PORT` change them). No other port is published, and the database has none.

The two required values: `LEGAL_OPERATOR` and `LEGAL_CONTACT_EMAIL` are empty in `.env.production.example`. Set them in step 2, and before an update from a release that did not need them ([Instance values](#instance-values) in step 4). Without them `/up` answers `503`, the `app` service never becomes healthy, `up -d --wait` fails, and the other services do not start, because they wait for `app`. `docker compose -f compose.production.yaml logs app` shows which value is missing.

### The proxy

One host name serves the app and Reverb. The proxy sends `/app/` and `/apps/` (the WebSocket connection and the Reverb API) to Reverb, and everything else to the app. In `.env`, `REVERB_HOST` is that host name, `REVERB_PORT=443` and `REVERB_SCHEME=https`: the browser connects to the proxy, not to the container. Set `TRUSTED_PROXIES=*` when the proxy runs on the same server (the ports are published on `127.0.0.1`, so only the proxy reaches them). The application answers only for the host of `APP_URL`, the names of `TRUSTED_HOSTS` and the loopback names (the health check of the `app` service calls `127.0.0.1`): a second host name of the proxy goes in `TRUSTED_HOSTS` and in `SANCTUM_STATEFUL_DOMAINS` (step 4), and the default server of step 7 goes in front of the `server` block below. An nginx example (change the domain and the certificate files):

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
        proxy_set_header X-Forwarded-For $remote_addr;
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
        proxy_set_header X-Forwarded-For $remote_addr;
        proxy_set_header X-Forwarded-Host $http_host;
        proxy_set_header X-Forwarded-Port $server_port;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_set_header X-Forwarded-Prefix "";
        proxy_pass http://127.0.0.1:3400;
    }
}
```

The proxy gives `X-Forwarded-For`, `X-Forwarded-Host`, `X-Forwarded-Port`, `X-Forwarded-Proto` and `X-Forwarded-Prefix` itself, so a value of a visitor is not passed on. When another proxy or a CDN stands in front of this one, `$remote_addr` is the address of that proxy: set the address of the visitor first with the `real_ip` module of nginx, for the address ranges of that proxy only.

### Update

Change `KNOWII_IMAGE` in `.env` to the new tag (or keep `main`), then:

```bash
docker compose -f compose.production.yaml pull
docker compose -f compose.production.yaml up -d --wait
docker compose -f compose.production.yaml exec -T app php artisan app:health
```

From the image that reads the legal texts only from `storage/app/legal/` (#1653, ADR 0055): copy your texts into the volume before `pull`, else your legal pages answer 404 after the update (["Legal pages"](#legal-pages) in step 4). `storage/app` is the volume `storage`, and the image holds no `deploy/` folder. From the folder of the Compose file, with your texts (the `.md` files and `subprocessors.json`) in a folder of the server:

```bash
tar -c -C <folder of your texts> . | docker compose -f compose.production.yaml run --rm --no-deps -T --entrypoint sh app -c 'mkdir -p /app/storage/app/legal && tar -x -C /app/storage/app/legal'
```

The image that runs now already reads these files before its own, so no page answers 404 at any moment. The Compose file, the image and the entrypoint need no change. Use the same command later to replace a text: the application serves the new file at the next request. To start from the templates, take the files of `resources/legal-templates/` from the repository at the tag of your image, and prepare them as ["Update your instance"](#8-update-your-instance) says.

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
