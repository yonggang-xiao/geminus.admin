FROM dunglas/frankenphp:1-php8.4
RUN install-php-extensions gd intl pgsql sockets zip redis opcache
RUN apt-get update && apt-get install -y --no-install-recommends unzip \
	&& rm -rf /var/lib/apt/lists/*
RUN mv "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini"
COPY --from=composer /usr/bin/composer /usr/local/bin/composer
