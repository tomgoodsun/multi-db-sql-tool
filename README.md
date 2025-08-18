# Multi-DB SQL Tool

複数のデータベースシャードに同時にSQLクエリを実行できるWebベースのツールです。

## 機能

- 複数シャードへの同時SQL実行
- CodeMirrorベースのSQLエディター
- クエリ履歴管理
- 読み取り専用モード対応
- Bootstrap 3ベースのレスポンシブUI

## セットアップ

### 1. 必要な環境
- Docker
- Docker Compose

### 2. 起動方法

```bash
# プロジェクトディレクトリに移動
cd multi-db-sql-tool

# Docker環境を構築・起動
docker-compose up -d --build

# ブラウザで以下にアクセス
http://localhost:8080
```

### 3. テストデータ

各シャードには以下のテーブルが自動作成されます：
- `users` - ユーザーマスタ
- `orders` - 注文データ

### 4. サンプルクエリ

```sql
-- 全シャードのユーザー数を確認
SELECT COUNT(*) as user_count FROM users;

-- 全シャードの注文データを確認
SELECT * FROM orders ORDER BY created_at DESC LIMIT 5;
```

## 技術仕様

- PHP 7.4
- MySQL 8.0
- Bootstrap 3
- CodeMirror 5
- PDO (MySQL)

## 設定

設定は `src/classes/Config.php` で管理されています。新しいシャードを追加する場合は、このファイルと `docker-compose.yml` を更新してください。

## 制限事項

- デフォルトで読み取り専用モード
- SELECT、SHOW、DESCRIBE、DESC、EXPLAINクエリのみ実行可能
- 書き込み権限が必要な場合は設定で変更可能

## 開発

開発時は以下のコマンドでログを確認できます：

```bash
# ログの確認
docker-compose logs -f web

# コンテナ内に入る
docker-compose exec web bash
```
