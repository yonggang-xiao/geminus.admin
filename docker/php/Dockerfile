FROM php:8.4-cli
RUN apt-get update && apt-get install -y \
		libfreetype-dev \
		libjpeg62-turbo-dev \
		libpng-dev \
		libicu-dev \
		libpq-dev \
		libzip-dev \
		unzip \
	&& docker-php-ext-configure gd --with-freetype --with-jpeg \
	&& docker-php-ext-install -j$(nproc) gd intl pgsql sockets zip
RUN pecl install redis \
	&& docker-php-ext-enable redis
RUN mv "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini"
COPY --from=composer /usr/bin/composer /usr/local/bin/composer
