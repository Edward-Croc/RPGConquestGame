FROM php:8.0-apache

# Install PHP extensions and mysql client (needed for BDD export/import tests)
# Pinned mirrors: deb.debian.org load-balances across backends whose indexes
# can advertise pool files they do not serve, which 404s the build at random
# (mariadb-common 10.5.29 answered 200 on security.debian.org and 404 on the
# CDN). Security must go to security.debian.org — country mirrors carry no
# debian-security tree. The security rewrite runs first so the second
# expression cannot match the line it just changed.
RUN sed -i \
      -e 's|http://deb.debian.org/debian-security|http://security.debian.org/debian-security|g' \
      -e 's|http://deb.debian.org/debian|http://ftp.fr.debian.org/debian|g' \
      /etc/apt/sources.list \
    && apt-get -o Acquire::Retries=3 update \
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
