# Multi-DB SQL Tool - Docker Distribution

## クイックスタート

### 1. 基本的な起動

```bash
cd dist

# ビルドスクリプトを使用してビルド
./build.sh
# または Windows の場合
# build.bat

# 手動でビルドする場合はプロジェクトルートから実行
cd ..
docker build -f dist/Dockerfile -t multi-db-sql-tool .

# コンテナを起動
docker run -d -p 8080:80 --name multi-db-sql-tool multi-db-sql-tool

# ブラウザで http://localhost:8080 にアクセス
```

### 2. Docker Compose を使用

```bash
# プロジェクトルートから実行
cd dist
cd ..

# サービス起動（MySQL付き）
docker-compose -f dist/docker-compose.yml up -d

# ログ確認
docker-compose logs -f multi-db-sql-tool

# サービス停止
docker-compose down
```

### 3. 設定ファイルを使用

```bash
# 設定ファイルを作成
cp ../src/config.sample.php ./config.php
# config.php を編集してデータベース接続情報を設定

# 設定ファイルをマウントして起動
docker run -d -p 8080:80 -v $(pwd)/config.php:/var/www/html/config.php multi-db-sql-tool

# または docker-compose.yml の volumes をコメントアウトして実行
docker-compose up -d
```

## テスト用データベース設定

Docker Compose で起動した場合、自動的にMySQL容器も起動します。
以下の接続情報でテスト可能です：

```php
// config.php のサンプル設定
$config = [
    'dbs' => [
        'test' => [
            'db1' => [
                'host' => 'mysql',  // Docker Compose内でのサービス名
                'port' => 3306,
                'username' => 'testuser',
                'password' => 'testpass',
                'dbname' => 'testdb',
                'charset' => 'utf8mb4'
            ]
        ]
    ]
];
```

## トラブルシューティング

### ポート競合
```bash
# 別のポートを使用
docker run -p 8081:80 multi-db-sql-tool
```

### ログ確認
```bash
# コンテナのログ確認
docker logs multi-db-sql-tool

# Apache のエラーログ
docker exec multi-db-sql-tool tail -f /var/log/apache2/error.log
```

### 設定ファイルの問題
```bash
# コンテナ内の設定ファイルを確認
docker exec multi-db-sql-tool cat /var/www/html/config.php

# コンテナ内でPHPの構文チェック
docker exec multi-db-sql-tool php -l /var/www/html/index.php
```

## 開発者向け

### イメージのサイズ確認
```bash
docker images multi-db-sql-tool
```

### コンテナ内での作業
```bash
# コンテナに入る
docker exec -it multi-db-sql-tool bash

# PHPの設定確認
docker exec multi-db-sql-tool php -m
```
