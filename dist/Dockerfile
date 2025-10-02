# Multi-DB SQL Tool - Basic Dockerfile (Phase 1)
# 現在のアプリケーションを動作させる基本的なDockerイメージ

FROM php:8.2-apache

# システムパッケージのインストール
RUN apt-get update && apt-get install -y \
    libzip-dev \
    zip \
    unzip \
    curl \
    && rm -rf /var/lib/apt/lists/*

# PHP拡張のインストール
RUN docker-php-ext-install \
    pdo \
    pdo_mysql \
    zip

# Apache設定
RUN a2enmod rewrite
COPY dist/docker/apache-config.conf /etc/apache2/sites-available/000-default.conf

# アプリケーションファイルをコピー
WORKDIR /var/www/html
COPY src/ ./

# 権限設定
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

# ヘルスチェック
HEALTHCHECK --interval=30s --timeout=3s --start-period=5s --retries=3 \
    CMD curl -f http://localhost/ || exit 1

EXPOSE 80

# 起動スクリプト
COPY dist/docker/entrypoint.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/entrypoint.sh

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["apache2-foreground"]
