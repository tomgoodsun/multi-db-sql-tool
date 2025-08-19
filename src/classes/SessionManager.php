<?php

/**
 * セッション・履歴管理クラス
 * PSR-12準拠
 */
class SessionManager 
{
    public function __construct() 
    {
        $this->startSession();
    }

    /**
     * セッション開始
     */
    private function startSession(): void 
    {
        if (session_status() === PHP_SESSION_NONE) {
            $sessionName = Config::get('session.name', 'MDBSQL_SESSION');
            $lifetime = Config::get('session.lifetime', 86400);
            
            session_name($sessionName);
            session_set_cookie_params($lifetime);
            session_start();
        }
    }

    /**
     * クエリ履歴に追加
     */
    public function addQueryHistory(string $sql): void 
    {
        if (!isset($_SESSION['query_history'])) {
            $_SESSION['query_history'] = [];
        }

        $historyItem = [
            'sql' => $sql,
            'timestamp' => time(),
            'formatted_time' => date('Y-m-d H:i:s')
        ];

        // 重複チェック（直前の履歴と同じ場合は追加しない）
        if (!empty($_SESSION['query_history']) && 
            $_SESSION['query_history'][0]['sql'] === $sql) {
            return;
        }

        array_unshift($_SESSION['query_history'], $historyItem);

        // 履歴数制限
        $maxHistory = Config::get('session.max_history', 50);
        $_SESSION['query_history'] = array_slice($_SESSION['query_history'], 0, $maxHistory);
    }

    /**
     * クエリ履歴を取得
     */
    public function getQueryHistory(): array 
    {
        return $_SESSION['query_history'] ?? [];
    }

    /**
     * クエリ履歴をクリア
     */
    public function clearQueryHistory(): void 
    {
        $_SESSION['query_history'] = [];
    }

    /**
     * 選択中のクラスターを設定
     */
    public function setCurrentCluster(string $clusterName): void 
    {
        $_SESSION['current_cluster'] = $clusterName;
    }

    /**
     * 選択中のクラスターを取得
     */
    public function getCurrentCluster(): string 
    {
        return $_SESSION['current_cluster'] ?? Config::getCurrentCluster();
    }

    /**
     * UI設定を保存
     */
    public function setUiSetting(string $key, $value): void 
    {
        if (!isset($_SESSION['ui_settings'])) {
            $_SESSION['ui_settings'] = [];
        }
        $_SESSION['ui_settings'][$key] = $value;
    }

    /**
     * UI設定を取得
     */
    public function getUiSetting(string $key, $default = null) 
    {
        return $_SESSION['ui_settings'][$key] ?? $default;
    }

    /**
     * セッションを破棄
     */
    public function destroy(): void 
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }
}
