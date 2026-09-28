FROM php:8.3-apache

RUN docker-php-ext-install pdo_mysql \
    && mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
    && mkdir -p /var/lib/sql-client \
    && chown www-data:www-data /var/lib/sql-client

WORKDIR /var/www/html

COPY . /var/www/html/
COPY docker/config/ /var/www/html/config/
COPY docker/entrypoint.sh /usr/local/bin/sql-client-entrypoint

RUN sed -i 's/\r$//' /usr/local/bin/sql-client-entrypoint \
    && chmod +x /usr/local/bin/sql-client-entrypoint \
    && rm -rf /var/www/html/docker \
    && chown -R www-data:www-data /var/www/html

# Opening the site root goes straight to the app.
RUN echo 'DirectoryIndex index.php login.php' > /etc/apache2/conf-enabled/sql-client.conf

VOLUME /var/lib/sql-client
EXPOSE 80

ENTRYPOINT ["sql-client-entrypoint"]
CMD ["apache2-foreground"]
