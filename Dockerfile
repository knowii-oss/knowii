# Production image of Knowii (#1172, ADR 0046). One image runs the application services of
# compose.production.yaml (app, queue, queue-mail, queue-captures, reverb, scheduler) with another command. No development tools.
# The Sail image for development stays in docker/8.5/.
#
# Build:  docker build -t knowii:local .
# Stages: base (PHP + extensions) -> vendor (Composer, no dev packages) -> frontend (Vite build) -> runtime

# Each base image is pinned by digest (#1661): a tag can point to another image tomorrow. To move to a new image of
# the same tag, read its digest with `docker buildx imagetools inspect <image:tag>` and change it here, in a pull request.
ARG PHP_IMAGE=dunglas/frankenphp:1-php8.5-trixie@sha256:667c5fffd348d9c3c260803d75956f1d9aace7e4b7331d8893de6f101d7e0e57
ARG NODE_IMAGE=node:26-bookworm-slim@sha256:5df79989ed685e09f0a98019db4cebfb22fb9df1a53c0161664a011f7ff6b0f1
ARG COMPOSER_IMAGE=composer:2@sha256:0c8e663f14c94d07b3c45b175c51f5a14718508f21fe4dd7e54f09488dd0ed71

# ---------------------------------------------------------------------------------------------------------------------
# PHP with the extensions that the application needs (and nothing else)
#   pdo_pgsql  PostgreSQL (the only database, ADR 0008)
#   pcntl      queue worker timeouts and Reverb
#   intl, bcmath, zip, exif, sodium (built in), opcache
#   gd         re-encoding of uploaded images to WebP (app/Support/ImageProcessor.php), EXIF orientation of JPEG files
# Redis is not installed: sessions, cache and queue use the database, and Reverb scaling is off.
# ---------------------------------------------------------------------------------------------------------------------
FROM ${PHP_IMAGE} AS base

RUN install-php-extensions pdo_pgsql pcntl intl bcmath zip exif gd opcache

# ---------------------------------------------------------------------------------------------------------------------
# PHP dependencies without dev packages
# ---------------------------------------------------------------------------------------------------------------------
FROM ${COMPOSER_IMAGE} AS composer

FROM base AS vendor

COPY --from=composer /usr/bin/composer /usr/bin/composer
ENV COMPOSER_ALLOW_SUPERUSER=1 COMPOSER_NO_INTERACTION=1

WORKDIR /app

# The dependencies first, so that this layer stays in the cache until composer.lock changes
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-progress

# The PHP code, then the optimized autoloader (the package discovery runs at start, with the real environment)
COPY artisan ./
COPY app ./app
COPY bootstrap ./bootstrap
COPY config ./config
COPY database ./database
COPY lang ./lang
COPY public ./public
COPY resources ./resources
COPY routes ./routes
COPY storage ./storage
RUN composer dump-autoload --no-dev --optimize --classmap-authoritative --no-scripts

# ---------------------------------------------------------------------------------------------------------------------
# Front-end build (Vite through Nx). The build resolves ziggy-js from vendor/tightenco/ziggy (tsconfig.base.json).
# ---------------------------------------------------------------------------------------------------------------------
FROM ${NODE_IMAGE} AS frontend

ENV NX_DAEMON=false NX_NO_CLOUD=true NX_SKIP_NX_CACHE=true CI=true

# Vite writes the VITE_* values into the JavaScript files at build time (apps/knowii/src/bootstrap.ts, main.tsx). So that
# one image runs in every environment, the build gets placeholders, and docker/production/entrypoint.sh replaces them
# with the values of the container environment at start. Keep this list and the one in the entrypoint identical.
ENV VITE_APP_NAME=__KNOWII_VITE_APP_NAME__ \
    VITE_REVERB_APP_KEY=__KNOWII_VITE_REVERB_APP_KEY__ \
    VITE_REVERB_HOST=__KNOWII_VITE_REVERB_HOST__ \
    VITE_REVERB_PORT=__KNOWII_VITE_REVERB_PORT__ \
    VITE_REVERB_SCHEME=__KNOWII_VITE_REVERB_SCHEME__

WORKDIR /app

COPY package.json package-lock.json .nvmrc ./
RUN npm ci --no-audit --no-fund

COPY nx.json tsconfig.base.json tsconfig.app.json vitest.config.mts eslint.config.mjs ./
COPY apps ./apps
COPY libs ./libs
COPY resources ./resources
COPY --from=vendor /app/vendor/tightenco/ziggy ./vendor/tightenco/ziggy

RUN npm run build

# ---------------------------------------------------------------------------------------------------------------------
# Runtime: FrankenPHP in classic mode (no worker mode), a non-root user
# ---------------------------------------------------------------------------------------------------------------------
FROM base AS runtime

ARG APP_UID=1000
ARG APP_GID=1000

LABEL org.opencontainers.image.title="Knowii" \
      org.opencontainers.image.source="https://github.com/knowii-oss/knowii" \
      org.opencontainers.image.licenses="AGPL-3.0-only"

# Production PHP settings (docker/production/php.ini) and the web server configuration
RUN cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
COPY docker/production/php.ini "$PHP_INI_DIR/conf.d/zz-knowii.ini"
COPY docker/production/Caddyfile /etc/caddy/Caddyfile
COPY docker/production/entrypoint.sh /usr/local/bin/knowii-entrypoint
COPY docker/production/healthcheck-process.sh /usr/local/bin/knowii-healthcheck-process

# A non-root user. FrankenPHP listens on port 8000 (above 1024), so it needs no capability.
RUN setcap -r /usr/local/bin/frankenphp \
    && groupadd --gid "${APP_GID}" knowii \
    && useradd --uid "${APP_UID}" --gid knowii --no-create-home --shell /usr/sbin/nologin knowii \
    && chmod 0755 /usr/local/bin/knowii-entrypoint /usr/local/bin/knowii-healthcheck-process \
    && mkdir -p /data/caddy /config/caddy \
    && chown -R knowii:knowii /data /config

WORKDIR /app

COPY --from=vendor --chown=knowii:knowii /app /app
COPY --from=frontend --chown=knowii:knowii /app/public/build /app/public/build

# Folders that the application writes to. storage/app is a volume in compose.production.yaml (uploads, private
# files, exports). public/ is writable for the storage:link symlink that the entrypoint creates.
RUN mkdir -p storage/app/public storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chown -R knowii:knowii storage bootstrap/cache public \
    && chmod -R ug+rwX storage bootstrap/cache

ENV XDG_CONFIG_HOME=/config \
    XDG_DATA_HOME=/data \
    APP_ENV=production \
    APP_DEBUG=false \
    LOG_CHANNEL=stderr \
    SERVER_PORT=8000

USER knowii

VOLUME ["/app/storage/app"]
EXPOSE 8000

ENTRYPOINT ["knowii-entrypoint"]
CMD ["frankenphp", "run", "--config", "/etc/caddy/Caddyfile", "--adapter", "caddyfile"]
