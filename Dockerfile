FROM php:8.1-apache-bullseye

ENV OHRM_VERSION 5.5
ENV OHRM_MD5 113e76fa9dd42a03f2b6a397fa2ffbc8

RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

RUN set -ex; \
	savedAptMark="$(apt-mark showmanual)"; \
	apt-get update; \
	apt-get install -y --no-install-recommends \
		libfreetype6-dev \
		libjpeg-dev \
		libpng-dev \
		libzip-dev \
		libldap2-dev \
		libicu-dev \
		unzip \
	; \
	\
	cd .. && rm -r html; \
	curl -fSL -o orangehrm.zip "https://sourceforge.net/projects/orangehrm/files/stable/${OHRM_VERSION}/orangehrm-${OHRM_VERSION}.zip"; \
	echo "${OHRM_MD5} orangehrm.zip" | md5sum -c -; \
	unzip -q orangehrm.zip "orangehrm-${OHRM_VERSION}/*"; \
	mv orangehrm-$OHRM_VERSION html; \
	rm -rf orangehrm.zip; \
	chown www-data:www-data html; \
	chown -R www-data:www-data html/src/cache html/src/log html/src/config; \
	chmod -R 775 html/src/cache html/src/log html/src/config; \
	\
	docker-php-ext-configure gd --with-freetype --with-jpeg; \
	docker-php-ext-configure ldap \
	    --with-libdir=lib/$(uname -m)-linux-gnu/ \
	; \
	\
	docker-php-ext-install -j "$(nproc)" \
		gd \
		opcache \
		intl \
		pdo_mysql \
		zip \
		ldap \
	; \
	\
	apt-mark auto '.*' > /dev/null; \
	apt-mark manual $savedAptMark; \
	ldd "$(php -r 'echo ini_get("extension_dir");')"/*.so \
		| awk '/=>/ { print $3 }' \
		| sort -u \
		| xargs -r dpkg-query -S \
		| cut -d: -f1 \
		| sort -u \
		| xargs -rt apt-mark manual; \
	\
	apt-get purge -y --auto-remove -o APT::AutoRemove::RecommendsImportant=false; \
	rm -rf /var/cache/apt/archives; \
	rm -rf /var/lib/apt/lists/*

RUN { \
		echo 'opcache.memory_consumption=128'; \
		echo 'opcache.interned_strings_buffer=8'; \
		echo 'opcache.max_accelerated_files=4000'; \
		echo 'opcache.revalidate_freq=60'; \
		echo 'opcache.fast_shutdown=1'; \
		echo 'opcache.enable_cli=1'; \
	} > /usr/local/etc/php/conf.d/opcache-recommended.ini; \
	\
	if command -v a2enmod; then \
		a2enmod rewrite; \
	fi;

# RUN pecl install xdebug && docker-php-ext-enable xdebug ## Uncomment to install xdebug for debugging

# Install cron
RUN apt-get update && apt-get install -y cron

# Install dos2unix
RUN apt-get update && apt-get install -y dos2unix


# Copy hello-cron file to the cron.d directory
COPY attendance_cron /etc/cron.d/attendance_cron

COPY devTools /var/www/html/devTools
 
# Give execution rights on the cron job
RUN chmod 0644 /etc/cron.d/attendance_cron

# Apply cron job
RUN crontab /etc/cron.d/attendance_cron 


VOLUME ["/var/www/html"]
