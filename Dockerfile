FROM php:7.4-apache

# PHP拡張のインストール
RUN docker-php-ext-install pdo pdo_mysql

# Apache設定
RUN a2enmod rewrite
WORKDIR /var/www/html

# PHPの設定調整
RUN echo "display_errors = On" >> /usr/local/etc/php/conf.d/errors.ini
RUN echo "error_reporting = E_ALL" >> /usr/local/etc/php/conf.d/errors.ini
