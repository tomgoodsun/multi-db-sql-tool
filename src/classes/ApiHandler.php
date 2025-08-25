<?php

/**
 * APIエンドポイント処理クラス
 * PSR-12準拠
 */
class ApiHandler 
{
    private $sessionManager;
    private $dbManager;
    private $sqlExecutor;

    public function __construct() 
    {
        $this->sessionManager = new SessionManager();
        
        $currentCluster = $this->sessionManager->getCurrentCluster();
        $this->dbManager = new DatabaseManager($currentCluster);
        $this->sqlExecutor = new SqlExecutor($this->dbManager);
    }

    /**
     * 言語切り替え
     */
    private function handleSetLanguage(): void
    {
        $language = $_POST['language'] ?? 'en';
        
        error_log('ApiHandler::handleSetLanguage - Requested language: ' . $language);
        
        $availableLanguages = Language::getAvailableLanguages();
        if (!array_key_exists($language, $availableLanguages)) {
            error_log('ApiHandler::handleSetLanguage - Invalid language code: ' . $language);
            throw new Exception('Invalid language code');
        }
        
        Language::setLanguage($language);
        
        error_log('ApiHandler::handleSetLanguage - Language set, current: ' . Language::getCurrentLanguage());
        
        $this->sendSuccess([
            'language' => Language::getCurrentLanguage(),
            'message' => Language::get('language_changed')
        ]);
    }

    /**
    * APIリクエストを処理
    */
    public function handle(): void
    {
    // 文字エンコーディングを明示的に設定
    mb_internal_encoding('UTF-8');
    header('Content-Type: application/json; charset=utf-8');
        
        try {
            $action = $_POST['action'] ?? $_GET['action'] ?? '';
            
            switch ($action) {
                case 'execute':
                    $this->handleExecute();
                    break;
                case 'format':
                    $this->handleFormat();
                    break;
                case 'status':
                    $this->handleStatus();
                    break;
                case 'history':
                    $this->handleHistory();
                    break;
                case 'clear_history':
                    $this->handleClearHistory();
                    break;
                case 'switch_cluster':
                    $this->handleSwitchCluster();
                    break;
                case 'set_language':
                    $this->handleSetLanguage();
                    break;
                default:
                    throw new Exception('Invalid action');
            }
        } catch (Exception $e) {
            $this->sendError($e->getMessage());
        }
    }

    /**
     * SQL実行処理
     */
    private function handleExecute(): void 
    {
        $sql = $_POST['sql'] ?? '';
        if (empty($sql)) {
            throw new Exception('No SQL provided');
        }

        $results = $this->sqlExecutor->executeOnAllShards($sql);
        $this->sessionManager->addQueryHistory($sql);

        $this->sendSuccess([
            'results' => $results,
            'query_count' => count($this->sqlExecutor->parseSqlQueries($sql))
        ]);
    }

    /**
     * SQLフォーマット処理
     */
    private function handleFormat(): void 
    {
        $sql = $_POST['sql'] ?? '';
        if (empty($sql)) {
            throw new Exception('No SQL provided');
        }

        $formattedSql = $this->sqlExecutor->formatSql($sql);
        
        $this->sendSuccess([
            'formatted_sql' => $formattedSql
        ]);
    }

    /**
     * ステータス情報取得
     */
    private function handleStatus(): void 
    {
        $tablesInfo = $this->dbManager->getAllTablesInfo();
        
        $this->sendSuccess([
            'cluster' => $this->sessionManager->getCurrentCluster(),
            'shards' => $tablesInfo,
            'readonly_mode' => Config::isReadOnlyMode()
        ]);
    }

    /**
     * 履歴取得
     */
    private function handleHistory(): void 
    {
        $history = $this->sessionManager->getQueryHistory();
        
        $this->sendSuccess([
            'history' => $history
        ]);
    }

    /**
     * 履歴クリア
     */
    private function handleClearHistory(): void 
    {
        $this->sessionManager->clearQueryHistory();
        
        $this->sendSuccess([
            'message' => 'History cleared'
        ]);
    }

    /**
     * クラスター切り替え
     */
    private function handleSwitchCluster(): void 
    {
        $clusterName = $_POST['cluster'] ?? '';
        if (empty($clusterName)) {
            throw new Exception('No cluster specified');
        }

        $clusters = array_keys(Config::get('dbs', []));
        if (!in_array($clusterName, $clusters)) {
            throw new Exception('Invalid cluster name');
        }

        $this->sessionManager->setCurrentCluster($clusterName);
        
        $this->sendSuccess([
            'cluster' => $clusterName,
            'message' => 'Cluster switched successfully'
        ]);
    }

    /**
    * 成功レスポンスを送信
    */
    private function sendSuccess(array $data): void
    {
    echo json_encode([
    'success' => true,
    'data' => $data
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
    }

    /**
    * エラーレスポンスを送信
    */
    private function sendError(string $message): void
    {
    echo json_encode([
    'success' => false,
    'error' => $message
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
    }
}
