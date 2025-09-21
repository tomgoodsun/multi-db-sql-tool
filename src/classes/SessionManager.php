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
     * Start the session if not already started
     *
     * @return void
     */
    private function startSession()
    {
        if (session_status() === PHP_SESSION_NONE) {
            $sessionName = Config::getInstance()->get('session.name', Config::DEFAULT_SESSION_NAME);
            $lifetime = Config::getInstance()->get('session.lifetime', Config::DEFAULT_SESSION_LIFETIME);

            session_name($sessionName);
            session_set_cookie_params($lifetime);
            session_start();
        }
    }

    /**
     * Add a query to the history.
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

        // Check for duplicate (do not add if same as last history)
        foreach ($queryHistory as $item) {
            if ($item['sql'] === $sql) {
                return;
            }
        }

        array_unshift($queryHistory, $historyItem);

        // Limit history size
        $maxHistory = Config::getInstance()->get('session.max_history', Config::MAX_QUERY_HISTORY);
        $_SESSION['query_history'] = array_slice($queryHistory, 0, $maxHistory);
    }

    /**
     * Get the query history.
     *
     * @return array
     */
    public function getQueryHistory()
    {
        return $_SESSION['query_history'] ?? [];
    }

    /**
     * Clear the query history.
     *
     * @return void
     */
    public function clearQueryHistory()
    {
        $_SESSION['query_history'] = [];
    }

    /**
     * Destroy the session.
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
