# Multi DB SQL Tool

Webベースで複数DB（Shardingや拠点分散構成）にSQLを一括実行できる軽量ツール。

## 特徴

- 複数Shard/拠点DBへの同時クエリ実行
- タブ表示による結果比較
- XLSXエクスポート（SheetJS利用予定）
- SELECT/SHOWのみ許可の制限モードあり

## 起動方法（Docker）

```bash
docker-compose up --build
```
