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

| Key                                                               | Value                                                                                                                                                                                                                                                                                                                                                                     |
| ----------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `APP_ENV`                                                         | `production`                                                                                                                                                                                                                                                                                                                                                              |
| `APP_DEBUG`                                                       | `false`                                                                                                                                                                                                                                                                                                                                                                   |
| `APP_URL`                                                         | `https://<YOUR_DOMAIN>`                                                                                                                                                                                                                                                                                                                                                   |
| `APP_KEY`                                                         | Generated by `php artisan key:generate` (step 5)                                                                                                                                                                                                                                                                                                                          |
| `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | The database from step 3                                                                                                                                                                                                                                                                                                                                                  |
| `MAIL_MAILER`, `MAIL_FROM_ADDRESS`                                | Your email provider and sender address. For Resend, also set `RESEND_KEY`.                                                                                                                                                                                                                                                                                                |
| `SESSION_DOMAIN`                                                  | `<YOUR_DOMAIN>`                                                                                                                                                                                                                                                                                                                                                           |
| `SESSION_SECURE_COOKIE`                                           | `true`                                                                                                                                                                                                                                                                                                                                                                    |
| `SESSION_COOKIE`                                                  | `__Secure-knowii_session`                                                                                                                                                                                                                                                                                                                                                 |
| `REVERB_APP_ID`, `REVERB_APP_SECRET`                              | Unique random values                                                                                                                                                                                                                                                                                                                                                      |
| `REVERB_APP_KEY`                                                  | A unique value, for example `knowii-<your instance name>`                                                                                                                                                                                                                                                                                                                 |
| `REVERB_HOST`                                                     | `ws.<YOUR_DOMAIN>`                                                                                                                                                                                                                                                                                                                                                        |
| `REVERB_PORT`                                                     | `443`                                                                                                                                                                                                                                                                                                                                                                     |
| `REVERB_SCHEME`                                                   | `https`                                                                                                                                                                                                                                                                                                                                                                   |
| `REVERB_SERVER_HOST`, `REVERB_SERVER_PORT`                        | `127.0.0.1` and `4201`: the WebSocket server listens locally, and nginx forwards `ws.<YOUR_DOMAIN>` to it                                                                                                                                                                                                                                                                 |
| `REVERB_ALLOWED_ORIGINS`                                          | `<YOUR_DOMAIN>`                                                                                                                                                                                                                                                                                                                                                           |
| `EMAILOCTOPUS_API_KEY`, `EMAILOCTOPUS_WAITLIST_LIST_ID`           | Optional: the waitlist form on the welcome page. Leave empty to hide the form.                                                                                                                                                                                                                                                                                            |
| `EMAILOCTOPUS_WAITLIST_CONTACT_STATUS`                            | `pending` (the visitor confirms the address by email, recommended) or `subscribed`                                                                                                                                                                                                                                                                                        |
| `EMAILOCTOPUS_NEWSLETTER_LIST_ID`                                 | Optional: the newsletter form in the footer (#938). Leave empty to hide the form. It also needs `EMAILOCTOPUS_API_KEY` and the Turnstile keys. Use a list for this instance only: `mailing-lists:prune` deletes its unconfirmed and unsubscribed contacts (ADR 0030).                                                                                                     |
| `EMAILOCTOPUS_NEWSLETTER_CONTACT_STATUS`                          | `pending` (recommended) or `subscribed`, as for the waitlist                                                                                                                                                                                                                                                                                                              |
| `TURNSTILE_SITE_KEY`, `TURNSTILE_SECRET_KEY`                      | Cloudflare Turnstile keys (bot check). Required by the waitlist form: without them, the form is hidden.                                                                                                                                                                                                                                                                   |
| `PLAUSIBLE_DOMAIN`, `PLAUSIBLE_PROXY_URL`                         | Optional: Plausible statistics (cookieless) through a proxy that serves `/content/script.js` and receives `/api/v1/event` (for example a Cloudflare Worker). Empty: no analytics script. If you turn it on, list your provider in your privacy policy                                                                                                                     |
| `ROBOTS_ALLOW_INDEXING`                                           | `true` lets search engines index the public pages (default in production). `false` on a test or private instance: `robots.txt` refuses every crawler and every page sends `noindex`                                                                                                                                                                                       |
| `COMMUNITY_RESERVED_NAMES`, `COMMUNITY_RESERVED_NAMES_OWNERS`     | Optional: community names that only your administrators can use (your brand, your products), separated by commas, and the email addresses of the accounts that can use them (verified accounts). They add to the built-in list of `config/communities.php` (Support, Official, Admin, the names of the platform). For everyone else a reserved name is "taken" (ADR 0036) |
| `LOG_STACK`, `LOG_DAILY_DAYS`                                     | `daily` and `14`: one log file per day in `storage/logs`, deleted after 14 days (the period of the privacy policy). With `stderr`, rotate the logs of your process manager the same way                                                                                                                                                                                   |

Crawlers (#104): `/robots.txt` is built from `config/robots.php`. It refuses a list of AI crawlers everywhere and keeps every crawler out of the member areas and the API. Pages of signed-in members and the API always send `X-Robots-Tag: noindex, nofollow`. `robots.txt` is only a request: if Cloudflare is in front of your instance, also turn on its "Block AI bots" setting (Security, Bots), which refuses them at the edge. On a test or private instance, set `ROBOTS_ALLOW_INDEXING=false`.

Retention (ADR 0030): `config/retention.php` holds the retention periods of the data that Knowii keeps only for a time, and the scheduled tasks of step 6 apply them. The privacy policy reads the same numbers, so it always tells what your instance does. If you change a `RETENTION_*` value, the policy changes with it. Logs (`LOG_DAILY_DAYS`), sessions (`SESSION_LIFETIME`) and your backups follow your server configuration: keep them equal to the periods of your privacy policy.

| Key                                                                                | Value                                                                                                                                                                                                       |
| ---------------------------------------------------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `RETENTION_EXPIRED_INVITATIONS_DAYS`                                               | Days an expired invitation stays before `retention:prune` deletes it (default `30`)                                                                                                                         |
| `RETENTION_EXPIRED_API_TOKENS_DAYS`                                                | Days an expired API token stays before `retention:prune` deletes it (default `30`)                                                                                                                          |
| `RETENTION_FAILED_JOBS_DAYS`                                                       | Days a failed background job keeps its data (default `7`)                                                                                                                                                   |
| `RETENTION_UNUSED_UPLOADS_DAYS`                                                    | Days an uploaded image that no content uses stays (default `7`)                                                                                                                                             |
| `RETENTION_WAITLIST_MONTHS`                                                        | Months a waitlist contact stays in EmailOctopus after the signup (default `12`)                                                                                                                             |
| `RETENTION_INACTIVE_CONTACTS_DAYS`                                                 | Days an unconfirmed or unsubscribed EmailOctopus contact stays after its last change (default `30`)                                                                                                         |
| `RETENTION_BACKUPS_DAILY`, `RETENTION_BACKUPS_WEEKLY`, `RETENTION_BACKUPS_MONTHLY` | The snapshots your backup script keeps (default `7`, `4` and `12`). Knowii does not run the backups: use the same numbers in your script (`restic forget --keep-daily 7 --keep-weekly 4 --keep-monthly 12`) |

Legal pages (#904): the privacy policy, the terms of service, the imprint, the data processing agreement and the content policy are Markdown files in `resources/markdown`. They describe the Knowii instance of DeveloPassion SRL: replace each one with your own file in `storage/app/legal/<file>.md` (same file names: `policy.md`, `terms.md`, `imprint.md`, `dpa.md`, `content-policy.md`), which an update never overwrites. A file starts with a front matter block (`title`, `updated`, and `draft: true` while the text is not final). `{{retention.<period>}}` shows a period of `config/retention.php` (ADR 0030) and `{{subprocessors}}` the table of `config/legal.php`.

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

Knowii needs four processes, in addition to PHP-FPM:

- The web server (nginx with PHP-FPM), which serves the `public/` folder
- The queue worker, which processes jobs. Knowii sends its real-time (WebSocket) events through the queue: without a worker, users get no live updates.
- The WebSocket server (Laravel Reverb)
- The scheduler, which runs the scheduled tasks of `routes/console.php` (see the list below). Without it, unused uploaded images are never deleted.

Keep the queue worker, Reverb and the scheduler running with a process manager. This is an example for Supervisor (`/etc/supervisor/conf.d/knowii.conf`). Change the path and the user:

```ini
[program:knowii-queue]
command=php /var/www/knowii/artisan queue:work --queue=default --sleep=3 --tries=3
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
2. `retention:prune`, every day at 03:40: deletes the invitations 30 days after they expire, the API tokens 30 days after their expiry date, the failed background jobs after 7 days, the expired password reset tokens, and the expired sessions and cache entries when they are stored in the database (#1306). Run `php artisan retention:prune --dry-run` to see the counts first.
3. `mailing-lists:prune`, every day at 03:50: deletes the waitlist contacts 12 months after the signup, and the unconfirmed or unsubscribed contacts of the waitlist and the newsletter 30 days after their last change (EmailOctopus API). It does nothing while `EMAILOCTOPUS_API_KEY` is empty.

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
php artisan queue:restart    # the worker restarts with the new code
php artisan reverb:restart   # the WebSocket server restarts with the new code
sudo systemctl reload php8.5-fpm
```

Read the release notes (`CHANGELOG.md`) before you update. They tell you about new or changed environment values.

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
