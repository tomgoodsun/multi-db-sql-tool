<?php

/**
 * Japanese language file
 */

return [
    // Common
    'app_name' => 'マルチDB SQLツール',
    'loading' => '読み込み中...',
    'error' => 'エラー',
    'success' => '成功',
    'warning' => '警告',
    'info' => '情報',
    'cancel' => 'キャンセル',
    'close' => '閉じる',
    'ok' => 'OK',
    'yes' => 'はい',
    'no' => 'いいえ',
    
    // Header
    'read_only' => '読み取り専用',
    'write_enabled' => '書き込み可能',
    
    // Sidebar
    'db_table_status' => 'DB・テーブル状態',
    'tables' => 'テーブル',
    'logical_name' => '論理名',
    
    // Toolbar
    'history' => '履歴',
    'beautify_sql' => 'SQLフォーマット',
    'run' => '実行 (Ctrl+Enter)',
    'export' => 'エクスポート:',
    'csv' => 'CSV',
    'xlsx' => 'XLSX',
    
    // SQL Editor
    'sql_editor' => 'SQLエディタ',
    'enter_sql_query' => 'SQLクエリを入力してください',
    
    // Results
    'results' => '結果',
    'execute_sql_message' => 'SQLクエリを実行すると結果がここに表示されます',
    'no_data_returned' => 'データが返されませんでした',
    'query' => 'クエリ',
    'db' => 'DB',
    
    // History
    'query_history' => 'クエリ履歴',
    'no_history_available' => 'クエリ履歴がありません。',
    
    // Execution Warning Dialog
    'sql_execution_confirmation' => 'SQL実行確認',
    'dangerous_sql_confirmation' => '危険なSQL実行確認',
    'sql_will_execute_on_all_dbs' => 'このSQLを全てのデータベースに実行します。',
    'sql_may_modify_data' => 'このSQLはデータを変更・削除する可能性があります。',
    'sql_type' => 'SQLタイプ',
    'target_databases' => '実行対象データベース',
    'current_cluster_all_shards' => '現在のクラスター内の全てのシャードに実行されます。',
    'important_warning' => '重要な警告',
    'operation_irreversible' => 'この操作は元に戻せません',
    'production_caution' => '本番環境での実行は特に注意が必要です',
    'backup_recommended' => '必要に応じてバックアップを取ってください',
    'execute' => '実行する',
    
    // Messages
    'query_executed_successfully' => 'クエリが正常に実行されました',
    'query_execution_failed' => 'クエリ実行に失敗しました',
    'format_successful' => 'SQLのフォーマットが完了しました',
    'format_failed' => 'フォーマットに失敗しました',
    'history_load_failed' => '履歴の読み込みに失敗しました',
    'cluster_switched' => 'クラスターを切り替えました',
    'cluster_switch_failed' => 'クラスター切り替えに失敗しました',
    'no_results_to_export' => 'エクスポートする結果がありません',
    'results_exported_successfully' => '結果のエクスポートが完了しました',
    'csv_export_successful' => 'CSV形式でエクスポートが完了しました',
    'csv_export_failed' => 'CSVエクスポートに失敗しました',
    'export_failed' => 'エクスポートに失敗しました',
    'initialization_failed' => 'アプリケーションの初期化に失敗しました',
    'language_changed' => '言語が正常に変更されました',
    
    // Status
    'connected' => '接続済み',
    'failed' => '失敗',
    
    // SQL Types
    'SELECT' => 'SELECT',
    'INSERT' => 'INSERT',
    'UPDATE' => 'UPDATE',
    'DELETE' => 'DELETE',
    'DROP' => 'DROP',
    'TRUNCATE' => 'TRUNCATE',
    'ALTER' => 'ALTER',
    'CREATE' => 'CREATE',
    'SHOW' => 'SHOW',
    'DESCRIBE' => 'DESCRIBE',
    'EXPLAIN' => 'EXPLAIN',
    'UNKNOWN' => '不明',
];
