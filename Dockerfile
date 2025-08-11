ROM php:8.2-apache

# PDO MySQL拡張
RUN docker-php-ext-install pdo pdo_mysql

# Apache設定
WORKDIR /var/www/html
