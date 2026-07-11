FROM php:8.2-apache-bookworm

# نصب extensions مورد نیاز CodeIgniter
RUN docker-php-ext-install mysqli pdo_mysql && \
    a2enmod rewrite

# کپی فایل‌ها
COPY . /var/www/html/

# تنظیم مجوزها
RUN chown -R www-data:www-data /var/www/html && \
    chmod -R 755 /var/www/html

# DocumentRoot رو روی public تنظیم کن (مهم برای CI4)
RUN sed -i 's|/var/www/html|/var/www/html/public|g' /etc/apache2/sites-available/000-default.conf

EXPOSE 80
