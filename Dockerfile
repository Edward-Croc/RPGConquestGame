FROM php:8.0-apache

# Install PHP extensions and mysql client (needed for BDD export/import tests)
# Pinned mirror: deb.debian.org and security.debian.org both resolve to the
# same CDN, whose backends can advertise pool files they do not serve. That
# 404s the build at random — seen on mariadb-common and libmariadb3 10.5.29,
# from Fastly addresses, under both names. A single real mirror is consistent
# with its own index; ftp.fr.debian.org carries debian-security too, so one
# rewrite covers main, updates and security.
#
# Check-Valid-Until=false: bullseye-security republishes weekly and every
# mirror serves the expired Release until it does, so a build landing in that
# window fails on freshness alone. The cost is accepting a stale index for an
# image that installs one client package and is never a runtime dependency.
RUN sed -i 's|http://deb.debian.org|http://ftp.fr.debian.org|g' /etc/apt/sources.list \
    && apt-get -o Acquire::Retries=3 -o Acquire::Check-Valid-Until=false update \
    && apt-get install -y --no-install-recommends default-mysql-client \
    && rm -rf /var/lib/apt/lists/* \
    && docker-php-ext-install pdo pdo_mysql mysqli

# Enable Apache mod_rewrite for future URL routing
RUN a2enmod rewrite

WORKDIR /var/www/html

# Copy and set up the entrypoint script
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

ENTRYPOINT ["entrypoint.sh"]
CMD ["apache2-foreground"]
