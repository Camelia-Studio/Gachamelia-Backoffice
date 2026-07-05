FROM dunglas/frankenphp

ENV SERVER_NAME=":80"
ENV FRANKENPHP_CONFIG="worker ./public/index.php"
ENV TZ="Europe/Paris"

RUN apt-get update; apt-get install curl gpg -y; \
mkdir -p /etc/apt/keyrings; \
curl -fsSL https://deb.nodesource.com/gpgkey/nodesource-repo.gpg.key | gpg --dearmor -o /etc/apt/keyrings/nodesource.gpg; \
echo "deb [signed-by=/etc/apt/keyrings/nodesource.gpg] https://deb.nodesource.com/node_22.x nodistro main" | tee /etc/apt/sources.list.d/nodesource.list; \
 apt-get update && apt-get install -y --no-install-recommends \
	acl \
	file \
	gettext \
	git \
        nodejs \
	&& rm -rf /var/lib/apt/lists/*


RUN set -eux; \
	install-php-extensions \
		@composer \
		apcu \
		intl \
		opcache \
		zip \
		pdo_mysql \
	;

ENV COMPOSER_ALLOW_SUPERUSER=1
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

COPY composer.* symfony.* ./

COPY package*.json ./

RUN npm install

RUN composer install --no-scripts

COPY . .

RUN npm run build
