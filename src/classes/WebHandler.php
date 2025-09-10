<?php
namespace MultiDbSqlTool;

class WebHandler
{
    /**
     * @var SessionManager
     */
    protected $sessionManager;

    protected $method = '';
    protected $action = '';
    protected $clusterName = '';

    public function __construct()
    {
        $this->sessionManager = new SessionManager();

        $this->method = $_SERVER['REQUEST_METHOD'] ?? '';
        $this->action = $_REQUEST['action'] ?? '';
        $this->clusterName = $_REQUEST['cluster'] ?? '';
    }

    /**
     * Check if the request method is POST
     *
     * @return boolean
     */
    protected function isPostMethod()
    {
        return strtoupper($this->method) === 'POST';
    }

    /**
     * Output JSON and exit
     *
     * @param array $data
     * @return void
     */
    protected function json(array $data)
    {
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }

    /**
     * API: Execute SQL query
     *
     * @return void
     */
    protected function processApiQuery()
    {
        if (!$this->isPostMethod()) {
            http_response_code(405);
            $this->json(['error' => 'Method Not Allowed']);
        }

        $resultSet = [];
        $targetShards = $_REQUEST['shards'] ?? [];
        $reqSql = $_REQUEST['sql'] ?? '';

        $sqls = Utility::splitSqlStatements($reqSql);

        $hasError = false;
        $id = 1;
        foreach ($sqls as $sql) {
            $result = [];
            $error = null;
            try {
                $dbSettings = Config::getInstance()->getDatabaseSettings($this->clusterName, $targetShards);
                $query = new Query($sql);
                $query->bulkAddConnections($dbSettings);
                $result = $query->query();
            } catch (\Throwable $e) {
                $error = $e->getMessage();
                $hasError = true;
            }

            $result += [
                'error' => $error,
                'results' => [],
                'rows' => 0,
                'sql' => $sql,
                'id' => $id,
            ];

            $resultSet[] = $result;
            $id++;
        }

        $this->sessionManager->addQueryHistory($reqSql, $this->clusterName);

        $this->json([
            'cluster' => $this->clusterName,
            'resultSet' => $resultSet,
            'hasError' => $hasError
        ]);
    }

    /**
     * API: Get query history
     *
     * @return void
     */
    protected function processApiHistory()
    {
        $histories = $this->sessionManager->getQueryHistory();
        $this->json([
            //'cluster' => $this->clusterName,
            'histories' => $histories,
        ]);
    }

    /**
     * API: Get initial data
     *
     * @return void
     */
    protected function processApiInitialData()
    {
        $tables = [];
        $error = null;
        try {
            $sql = 'SELECT TABLE_NAME, TABLE_COMMENT FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = database();';
            $query = new Query($sql);
            $query->bulkAddConnections(Config::getInstance()->getDatabaseSettings($this->clusterName));
            $result = $query->query();
            foreach ($result['results'] as $item) {
                $shard = $item['__shard'];
                $tableName = $item['TABLE_NAME'];

                if (!isset($tables[$tableName])) {
                    $tables[$tableName] = [
                        'name' => $tableName,
                        'comment' => $item['TABLE_COMMENT'],
                        'databases' => [],
                    ];
                }
                $tables[$tableName]['databases'][] = $shard;
            }
        } catch (\Exception $e) {
            $error = $e->getMessage();
        }

        // TODO: Remove lines, because these are test data
        //$tables['introduced_user_profile_header'] = ['name' => 'introduced_user_profile_header', 'comment' => 'Introduced User Profile Header (Unique)', 'databases' => ['shard1', 'shard2', 'shard3']];
        //$tables['user11'] = ['name' => 'user11', 'comment' => 'User 11', 'databases' => ['shard1', 'shard2', 'shard3']];

        $this->json([
            'cluster' => $this->clusterName,
            'shardList' => Config::getInstance()->getShardNames($this->clusterName),
            'tables' => $tables,
            'error' => $error,
        ]);
    }

    /**
     * Normal web request handler
     *
     * @return void
     */
    protected function processWeb()
    {
        $optionalName = Config::getInstance()->get('optional_name', '');
        $optionalName = $optionalName ? " for {$optionalName}" : '';
        $clausterList = Config::getInstance()->getClusterNames();
        $readOnlyMode = Config::getInstance()->get('readonly_mode', true);
        require_once __DIR__ . '/../assets/template/index.inc.html';
    }

    /**
     * Main execution function
     *
     * @return void
     */
    public function execute()
    {
        switch ($this->action) {
            case 'api_query':
                $this->processApiQuery();
                break;
            case 'api_history':
                $this->processApiHistory();
                break;
            case 'api_initial_data':
                $this->processApiInitialData();
                break;
            default:
                $this->processWeb();
                break;
        }
    }
}
