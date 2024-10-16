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
- A CNAME record called `www` pointing to the domain name (without the `www`)
- A CNAME record called `ws` pointing to the domain name (for WebSockets)

You also need a valid TLS certificate for your domain. You can use Let's Encrypt to get a free SSL certificate. If you're using Laravel Forge, you can use the Let's Encrypt integration to get a free SSL certificate, or create a CSR and use it with CloudFlare's SSL/TLS Origin Server "Create Certificate" feature, which will ensure that your certificate is always valid.

WARNING: Note that the TLS certificate should be valid for `<YOUR_DOMAIN>` and `*.<YOUR_DOMAIN>` so that it works for `www`, `ws`, etc.

Once your domain, DNS, and certificate are configured, don't forget to adapt the `.env` file accordingly.

In particular:

- `APP_URL` should be set to your domain name
- `APP_PORT` should be set to `443`
- `REVERB_HOST` should point to `ws.<YOUR_DOMAIN>`
- `REVERB_PORT` should be set to `443`
- `REVERB_SCHEME` should be set to `https`
- `REVERB_ALLOWED_ORIGINS` should be set to `<YOUR_DOMAIN>`

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
- The `SANCTUM_STATEFUL_DOMAINS` variable should be empty in production: `SANCTUM_STATEFUL_DOMAINS=`, otherwise the front-end API calls will be broken

For WebSockets to work (securely), don't forget to modify:

- `REVERB_APP_ID`: set to a unique value
- `REVERB_APP_KEY`: set to a unique value
- `REVERB_APP_SECRET`: set to a unique value

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
