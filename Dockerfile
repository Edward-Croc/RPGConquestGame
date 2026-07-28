FROM php:8.0-apache

# Install PHP extensions and mysql client (needed for BDD export/import tests)
RUN apt-get update \
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
