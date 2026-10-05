# CCDB attendance system - PHP + Apache for Render
FROM php:8.2-apache

# MySQL driver for PDO
RUN docker-php-ext-install pdo pdo_mysql

# App code
COPY . /var/www/html/

# Render injects $PORT: make Apache listen on it instead of fixed 80
CMD sed -i "s/Listen 80/Listen $PORT/" /etc/apache2/ports.conf && \
    sed -i "s/:80>/:$PORT>/" /etc/apache2/sites-enabled/000-default.conf && \
    apache2-foreground
