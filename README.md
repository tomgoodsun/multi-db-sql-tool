# Multi-DB SQL Tool

複数のデータベースシャードや分散データベース環境に同時にSQLクエリを実行できるWebベースのツールです。

## 特徴

- **複数DB同時実行**: 複数のシャード/データベースに対して同一のSQLを同時実行
- **直感的なUI**: Bootstrap 5ベースのモダンなインターフェース
- **高機能エディター**: CodeMirror 6によるSQL構文ハイライト・補完
- **結果表示**: AG Grid Communityによる高速で操作性の良いテーブル表示
- **XLSX出力**: SheetJSによる結果のExcel形式ダウンロード
- **履歴管理**: セッションベースのクエリ履歴
- **安全性**: 読み取り専用モード、Basic認証対応

## 技術仕様

- **Backend**: PHP 7.0+（サードパーティライブラリ非依存）
- **Database**: MySQL 5.7+
- **Frontend**: Bootstrap 5, CodeMirror 6, AG Grid Community, SheetJS
- **コーディング規約**: PSR-12準拠

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

### 3. 設定

`src/config/config.php` で以下を設定できます：

- **データベース接続情報**: 複数クラスター・シャードの定義
- **読み取り専用モード**: true/false
- **Basic認証**: ユーザー/パスワードの配列
- **制限設定**: 実行時間、最大行数、最大クエリ数
- **UI設定**: テーマ、エディターテーマ等

### 4. サンプルデータ

Docker環境では以下のテストデータが自動作成されます：
- `users` - ユーザーマスタ（各シャードに3件ずつ）
- `orders` - 注文データ（各シャードに3件ずつ）

## 使用方法

### 基本操作
1. 左サイドバーでテーブル一覧・接続状況を確認
2. 右上のSQLエディターでクエリを記述
3. 「Run」ボタンまたは`Ctrl+Enter`で実行
4. 結果は下部にタブ形式で表示

### 便利機能
- **Beautify**: SQLの自動フォーマット
- **History**: 過去のクエリ履歴から選択・復元
- **Export XLSX**: 全タブの結果をExcelファイルとしてダウンロード
- **Status**: 詳細な接続状況・統計情報を表示

### サンプルクエリ

```sql
-- 全シャードのユーザー数を確認
SELECT COUNT(*) as user_count FROM users;

-- 全シャードの注文データを確認
SELECT * FROM orders ORDER BY created_at DESC LIMIT 5;

-- 複数クエリの実行（セミコロン区切り）
SELECT COUNT(*) FROM users;
SELECT COUNT(*) FROM orders;
SELECT MAX(amount) as max_amount FROM orders;
```

## アーキテクチャ

### ディレクトリ構造
```
src/
├── classes/           # PHP classes (PSR-12)
│   ├── Config.php           # 設定管理
│   ├── DatabaseManager.php  # DB接続管理
│   ├── SqlExecutor.php      # SQL実行エンジン
│   ├── SessionManager.php   # セッション・履歴管理
│   ├── AuthManager.php      # Basic認証
│   └── ApiHandler.php       # API エンドポイント
├── config/            # Configuration files
│   └── config.php           # メイン設定ファイル
├── assets/            # Frontend assets
│   ├── css/app.css          # カスタムスタイル
│   └── js/app.js            # アプリケーションロジック
├── index.php          # メインUI
└── api.php            # API エンドポイント
```

### 主要機能

**SQL解析エンジン**
- セミコロン分割によるマルチクエリ対応
- 文字列リテラル・コメントを考慮した高精度パース
- 読み取り専用クエリの自動判定

**結果表示システム**
- クエリごとのタブ表示
- シャード名を第1列に自動追加
- エラー・成功の混在表示対応

**エクスポート機能**
- 各タブを個別のワークシートとして出力
- 日時付きファイル名の自動生成

## 用途例

- **Sharding環境**: 水平分割されたデータベースの統合調査
- **多店舗システム**: 全国店舗のローカルDBに対する一括操作
- **マイクロサービス**: 各サービスのDBに対する横断的なデータ確認
- **環境管理**: 開発・ステージング・本番環境の設定確認

## 開発・カスタマイズ

### ローカル開発
```bash
# ログの確認
docker-compose logs -f web

# コンテナ内でのデバッグ
docker-compose exec web bash

# MySQL接続確認
docker-compose exec mysql-shard1 mysql -u dbuser -pdbpass shard1
```

### 設定カスタマイズ
新しいクラスター・シャードを追加する場合：
1. `config/config.php` に設定追加
2. `docker-compose.yml` に対応するMySQLサービス追加
3. 初期化SQLファイルを `docker/mysql/` に配置

### セキュリティ
- 本番環境では `readonly_mode => true` を推奨
- Basic認証の設定を強く推奨
- SQLインジェクション対策としてPDOを使用

## ライセンス

MIT License

## 貢献

Issue、Pull Requestをお待ちしています。特に以下の分野での貢献を歓迎します：
- SQL解析エンジンの改善
- 新しいデータベース対応
- UI/UXの改善
- セキュリティ強化
