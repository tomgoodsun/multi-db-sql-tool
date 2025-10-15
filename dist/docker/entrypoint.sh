#!/bin/bash
set -e

echo "Multi-DB SQL Tool - Starting..."

# 設定ファイルが存在しない場合はサンプルをコピー
if [ ! -f /var/www/html/config.php ]; then
    echo "Config file not found. Creating from sample..."
    if [ -f /var/www/html/config.sample.php ]; then
        cp /var/www/html/config.sample.php /var/www/html/config.php
        echo "Please mount your config.php or set environment variables."
        echo "Example: docker run -v \$(pwd)/config.php:/var/www/html/config.php multi-db-sql-tool"
    fi
fi

# 環境変数からの設定オーバーライド（将来の拡張用）
if [ -n "$DB_HOST" ]; then
    echo "Environment-based configuration detected."
    # TODO: 環境変数から設定ファイルを生成
fi

echo "Starting Apache..."

# Apache を起動
exec "$@"
