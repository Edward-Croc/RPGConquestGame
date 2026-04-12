FROM php:8.0-apache

# Install PHP extensions required by the application
RUN docker-php-ext-install pdo pdo_mysql mysqli

# Enable Apache mod_rewrite for future URL routing
RUN a2enmod rewrite

WORKDIR /var/www/html

# Copy and set up the entrypoint script
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

ENTRYPOINT ["entrypoint.sh"]
CMD ["apache2-foreground"]
