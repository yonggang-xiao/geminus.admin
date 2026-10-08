FROM dunglas/frankenphp:1-php8.4
RUN install-php-extensions gd intl pgsql sockets zip redis opcache
RUN apt-get update && apt-get install -y --no-install-recommends cron unzip \
	&& rm -rf /var/lib/apt/lists/*
RUN mv "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini"
COPY --from=composer /usr/bin/composer /usr/local/bin/composer
COPY --chmod=0644 tasks.cron /etc/cron.d/geminus-tasks
HEALTHCHECK --interval=30s --timeout=5s --start-period=30s \
	CMD curl --fail --silent --show-error --insecure --output /dev/null https://localhost/ || exit 1
