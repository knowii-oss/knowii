# Self-hosting Knowii

Knowii is free and open source. You're free to install it anywhere and host it yourself. This guide will help you do that.

## Prerequisites

First, you need a machine to host Knowii. You can use a VPS, a dedicated server, or your own computer. You need root access to that machine. In production, Knowii uses Ubuntu installed on an Hetzner VPS, created and configured using [Laravel Forge](https://forge.laravel.com/). For simplicity and ease of use, we recommend using Laravel Forge for your self-hosting server. That way, you'll be able to easily deploy and configure your instance.

You need to have the following installed on the machine where you want to host Knowii:

- Ideally Ubuntu so that all explanations and scripts work as expected, even though Knowii can also run on other Linux distributions
- Node.js (use the same version as the one in the `.nvmrc` file)
- npm
- PostgreSQL (use the same version as the one in the `docker-compose.yml` file)
- Redis (use the same version as the one in the `docker-compose.yml` file)
- PHP (use the same version as the one in the `composer.json` file)
- An e-mail provider. By default, Knowii expects Resend, but you may configure the application differently
- A Podman or Docker container running Browserless (more details below)

You also need to install some packages:

- `sudo apt install libzip-dev php-zip`

## Domain and DNS configuration

If you're using your own domain, you need to configure it to point to your server.

At the DNS level, you need:

- An A record pointing to the IP address of your server
- An A record called `www` pointing to the IP address of your server
- An A record called `ws` pointing to the IP address of your server (for WebSockets)

You also need a valid TLS certificate for your domain. You can use Let's Encrypt to get a free SSL certificate. If you're using Laravel Forge, you can use the Let's Encrypt integration to get a free SSL certificate, or create a CSR and use it with CloudFlare's SSL/TLS Origin Server "Create Certificate" feature, which will ensure that your certificate is always valid.

WARNING: Note that the TLS certificate should be valid for `<YOUR_DOMAIN>` and `*.<YOUR_DOMAIN>` so that it works for `www`, `ws`, etc.

Once your domain, DNS, and certificate are configured, don't forget to adapt the `.env` file accordingly.

In particular:

- `APP_URL` should be set to your domain name
- `APP_PORT` should be set to `443`
- `REVERB_HOST` should point to `ws.<YOUR_DOMAIN>`
- `REVERB_PORT` should be set to `443`
- `REVERB_SCHEME` should be set to `https`
- `REVERB_ALLOWED_ORIGINS`

## Database configuration

You should use a dedicated database, schema and database user for Knowii. Assuming that your database is called `knowii` and that your database user is called `knowiipro`

Create the database and set the owner:

```
psql
  CREATE DATABASE knowii;
  ALTER DATABASE knowii OWNER TO knowiipro
```

Run `psql knowii --username knowiipro to enter psql as the knowiipro user`:

```
DROP schema public CASCADE;
CREATE SCHEMA public;
GRANT ALL ON SCHEMA public to postgres;
GRANT ALL ON SCHEMA public to knowiipro;
```

If you're using forge, you should also run this command in the `psql` session using the `knowiipro` user: `GRANT ALL ON SCHEMA public to forge;`

## Environment

You need to create a `.env` file at the root of the project. You can copy the `.env.example` file and adjust the values to your needs.

Don't forget to:

- Set a different value for `APP_KEY` using `php artisan key:generate`
- Set correct values for `APP_ENV`, `APP_URL`, `DB_*`, `MAIL_*`, `REDIS_*`, `BROWSERLESS_*`
- Set the `SANCTUM_STATEFUL_DOMAINS` to be empty in production: `SANCTUM_STATEFUL_DOMAINS=`, otherwise the front-end API calls will be broken
- Set `SESSION_SECURE_COOKIE` to `true`, assuming you use HTTPS
- Set `SESSION_COOKIE` to `__Secure-knowii_session`, assuming you use HTTPS

For WebSockets to work (securely), don't forget to modify:

- `REVERB_APP_ID`: set to a unique, secure, and random value
- `REVERB_APP_KEY`: set to a unique value. You can prefix-it by `knowii-your-own-instance-name`)
- `REVERB_APP_SECRET`: set to a unique, secure, and random value

## Browserless

Browserless is used by Knowii to scrape websites and fetch page metadata. The codebase includes a `./browserless-create-container.sh` script that you can use to create the browserless container with the right name and environment values (it loads the `.env` file).

That script expects podman to be installed and running. If you don't have it yet, you can install podman using `sudo apt-get update && sudo apt-get -y install podman` (reference: https://docs.docker.com/engine/install/ubuntu/#installation-methods).

But that is just useful for testing purposes. In production, you will want to create and use a systemd service to run Browserless, because that service must always be available for Knowii to work as intended.
To that end, the codebase also includes a `./browserless-create-service.sh` script that you can use to create a systemd service for Browserless.

WARNING: That script MUST NOT be executed as root. It leverages Podman's support for running rootless containers through systemd services. For more details about this, check out the following links:

- Explanations: https://www.redhat.com/sysadmin/quadlet-podman
- Reference: https://docs.podman.io/en/stable/markdown/podman-systemd.unit.5.html

The above script will create a `knowii_browserless_chromium.container` file in `~/.config/containers/systemd`, and will load it using systemd for the current user.

If you need to change the browserless container settings, then you should:

- Stop the service using `systemctl --user stop knowii_browserless_chromium`
- Delete the `knowii_browserless_chromium.container` container unit file in `~/.config/containers/systemd`
- Reload the systemd daemon for your user: `systemctl --user daemon-reload` so that it removes the old service
- Change your `.env` file
- Run the `./browserless-create-service.sh` script again.

## Queue worker

Knowii relies on queue workers to process queued jobs. For instance, whenever a WebSocket event needs to be sent, a job will be queued. Without queue workers, those events will never be delivered.

Example configuration:

- Connection: database
- Queue: default
- Command: `php artisan queue:work --queue=default --sleep=3 --tries=3`

## Laravel Forge

This section will be useful (or serve as reference) if you use Laravel Forge to manage your self-hosted server.

### Deployment script

The following script installs/updates the back-end and front-end dependencies, restarts FPM, and runs database migrations:

```
cd <path to knowii>

$FORGE_COMPOSER install --no-dev --no-interaction --prefer-dist --optimize-autoloader
npm run install:linux
npm run build

( flock -w 10 9 || exit 1
    echo 'Restarting FPM...'; sudo -S service $FORGE_PHP_FPM reload ) 9>/tmp/fpmlock

if [ -f artisan ]; then
    $FORGE_PHP artisan migrate --force
fi
```

### WebSockets

The centrally hosted Knowii production environment uses Laravel Forge with CloudFlare in front. TLS uses a certificate provisioned by CloudFlare (Origin Server certificate). That certificate includes `knowii.net` as CN, and `*.knowii.net` as SAN. This is supported by Laravel Forge. Unfortunately, when enabling Laravel Reverb using Laravel Forge, it doesn't understand that the certificate is also valid for Laravel Reverb and the WebSockets sub-domain (in this case `ws.knowii.net`). Because of that, when Laravel Reverb is enabled via Laravel Forge, it generates an NGINX configuration that listens on port 80 instead of port 443.

If you face this issue, you need to adapt the `/etc/nginx/forge-conf/<your site>/after/reverb.conf` configuration file as follows in order to enable TLS for the WebSockets:

```
server {
    listen 443 ssl http2;
    listen [::]:443 ssl http2;
    server_name ws.<YOUR_DOMAIN>;
    server_tokens off;

    # FORGE SSL (DO NOT REMOVE!)
    ssl_certificate /etc/nginx/ssl/<YOUR_DOMAIN>/<certificate id>/server.crt;
    ssl_certificate_key /etc/nginx/ssl/<YOUR_DOMAIN>/<certificate id>/server.key;

    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers ECDHE-ECDSA-AES128-GCM-SHA256:ECDHE-RSA-AES128-GCM-SHA256:ECDHE-ECDSA-AES256-GCM-SHA384:ECDHE-RSA-AES25>    ssl_prefer_server_ciphers off;
    ssl_dhparam /etc/nginx/dhparams.pem;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-XSS-Protection "1; mode=block";
    add_header X-Content-Type-Options "nosniff";

    charset utf-8;

    # FORGE CONFIG (DO NOT REMOVE!)
    include forge-conf/<YOUR DOMAIN>/server/*;

    access_log off;
    error_log  /var/log/nginx/ws.<YOUR DOMAIN>-error.log error;

    location / {
        proxy_http_version 1.1;
        proxy_set_header Host $http_host;
        proxy_set_header Scheme $scheme;
        proxy_set_header SERVER_PORT $server_port;
        proxy_set_header REMOTE_ADDR $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "Upgrade";

        proxy_pass http://localhost:4201;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}

```

Useful reference: https://tonymasek.com/articles/how-to-make-laravel-reverb-work-on-laravel-forge
