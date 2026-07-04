# KARYA application image — FrankenPHP (Caddy + PHP + Octane) in one process.
# The same image runs on a 2GB VPS, an on-prem server, or a managed-cloud host;
# the compose profile decides which sidecars (Redis, Gotenberg) come along.
FROM dunglas/frankenphp:1-php8.3

LABEL org.opencontainers.image.title="KARYA ERP"

# PHP extensions an ERP ledger needs: pgsql for Postgres, bcmath for exact money
# math, intl for locale/number formatting, redis for the standard profile.
RUN install-php-extensions \
        pdo_pgsql \
        pgsql \
        bcmath \
        intl \
        opcache \
        pcntl \
        redis \
        zip

WORKDIR /app

# Copy the dependency manifests first so Composer layer caches across code changes.
COPY composer.json composer.lock ./
COPY app-modules ./app-modules
RUN --mount=type=bin,source=/usr/bin/composer,target=/usr/bin/composer \
    composer install --no-dev --no-scripts --prefer-dist --no-interaction || true

COPY . .

# Production PHP + OPcache tuning lives in this ini; Octane serves the app.
COPY docker/php.prod.ini /usr/local/etc/php/conf.d/karya.ini

ENV OCTANE_SERVER=frankenphp
EXPOSE 8000

# supervisord runs the web server, the queue worker, and the scheduler together.
COPY docker/supervisord.conf /etc/supervisor/conf.d/karya.conf
ENTRYPOINT ["php", "artisan", "octane:frankenphp", "--host=0.0.0.0", "--port=8000"]
