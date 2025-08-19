FROM php:7.4-apache

# PHP拡張のインストール
RUN docker-php-ext-install pdo pdo_mysql

# Apache設定
RUN a2enmod rewrite headers
WORKDIR /var/www/html

# PHPの設定調整（開発用）
RUN echo "display_errors = On" >> /usr/local/etc/php/conf.d/development.ini && \
    echo "error_reporting = E_ALL" >> /usr/local/etc/php/conf.d/development.ini && \
    echo "log_errors = On" >> /usr/local/etc/php/conf.d/development.ini && \
    echo "max_execution_time = 60" >> /usr/local/etc/php/conf.d/development.ini && \
    echo "memory_limit = 256M" >> /usr/local/etc/php/conf.d/development.ini

# セッション設定
RUN echo "session.gc_maxlifetime = 86400" >> /usr/local/etc/php/conf.d/session.ini && \
    echo "session.cookie_lifetime = 86400" >> /usr/local/etc/php/conf.d/session.ini

# Apache設定ファイル
COPY docker/apache/000-default.conf /etc/apache2/sites-available/000-default.conf

EXPOSE 80
