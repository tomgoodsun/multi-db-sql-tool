<?php
namespace MultiDbSqlTool;

class SessionManager
{
    /**
     * Constructor
     */
    public function __construct()
    {
        $this->startSession();
    }

    /**
     * セッション開始
     *
     * @return void
     */
    private function startSession()
    {
        if (session_status() === PHP_SESSION_NONE) {
            $sessionName = Config::getInstance()->get('session.name', 'MDBSQL_SESSION');
            $lifetime = Config::getInstance()->get('session.lifetime', 86400);

            session_name($sessionName);
            session_set_cookie_params($lifetime);
            session_start();
        }
    }

    /**
     * クエリ履歴に追加
     *
     * @param string $sql
     * @param string $clusterName
     * @return void
     */
    public function addQueryHistory($sql, $clusterName)
    {
        if (!isset($_SESSION['query_history'])) {
            $_SESSION['query_history'] = [];
        }
        $queryHistory = $_SESSION['query_history'];

        $historyItem = [
            'cluster' => $clusterName,
            'sql' => $sql,
            'timestamp' => time(),
            'formattedTime' => date(DATE_W3C),
        ];

        // 重複チェック（直前の履歴と同じ場合は追加しない）
        foreach ($queryHistory as $item) {
            if ($item['sql'] === $sql) {
                return;
            }
        }

        array_unshift($queryHistory, $historyItem);

        // 履歴数制限
        $maxHistory = Config::getInstance()->get('session.max_history', 50);
        $_SESSION['query_history'] = array_slice($queryHistory, 0, $maxHistory);
    }

    /**
     * クエリ履歴を取得
     *
     * @return array
     */
    public function getQueryHistory()
    {
        return $_SESSION['query_history'] ?? [];
    }

    /**
     * クエリ履歴をクリア
     *
     * @return void
     */
    public function clearQueryHistory()
    {
        $_SESSION['query_history'] = [];
    }

    /**
     * セッションを破棄
     *
     * @return void
     */
    public function destroy()
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }
}
