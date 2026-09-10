FROM php:8.0-apache

# Install PHP extensions and mysql client (needed for BDD export/import tests)
# Two mirrors are listed because each 404s on pool files the other serves; apt falls back.
# Check-Valid-Until=false : bullseye-security republishes weekly and serves an expired Release until it does.
RUN printf 'deb http://ftp.fr.debian.org/debian bullseye main\ndeb http://ftp.fr.debian.org/debian bullseye-updates main\ndeb http://ftp.fr.debian.org/debian-security bullseye-security main\n' >> /etc/apt/sources.list \
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
