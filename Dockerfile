FROM php:8.2-apache

RUN docker-php-ext-install mysqli pdo_mysql && \
    a2enmod rewrite

COPY . /var/www/html/

RUN chown -R www-data:www-data /var/www/html && \
    chmod -R 755 /var/www/html

# DocumentRoot را به public_html تغییر بده
RUN sed -i 's|/var/www/html|/var/www/html/public_html|g' /etc/apache2/sites-available/000-default.conf && \
    sed -i 's|/var/www/html|/var/www/html/public_html|g' /etc/apache2/apache2.conf

# رفع Warning ServerName
RUN echo "ServerName localhost" >> /etc/apache2/apache2.conf

EXPOSE 80
