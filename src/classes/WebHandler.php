<?php
namespace MultiDbSqlTool;

class WebHandler
{
    /**
     * @var SessionManager
     */
    protected $sessionManager;

    protected $action = '';
    protected $lang = '';
    protected $clusterName = '';

    public function __construct()
    {
        $this->sessionManager = new SessionManager();

        $this->action = $_REQUEST['action'] ?? '';
        $this->lang = $_REQUEST['lang'] ?? '';
        $this->clusterName = $_REQUEST['cluster'] ?? '';
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
        $resultSet = [];
        $targetShards = $_REQUEST['shards'] ?? [];
        $sql = $_REQUEST['sql'] ?? '';

        $sqls = Utility::splitSqlStatements($sql);

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
                $this->sessionManager->addQueryHistory($sql, $this->clusterName);
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
        $tables['introduced_user_profile_header'] = ['name' => 'introduced_user_profile_header', 'comment' => 'Introduced User Profile Header (Unique)', 'databases' => ['shard1', 'shard2', 'shard3']];
        $tables['user11'] = ['name' => 'user11', 'comment' => 'User 11', 'databases' => ['shard1', 'shard2', 'shard3']];
        $tables['user12'] = ['name' => 'user12', 'comment' => 'User 12', 'databases' => ['shard1', 'shard2', 'shard3']];
        $tables['user13'] = ['name' => 'user13', 'comment' => 'User 13', 'databases' => ['shard1', 'shard2', 'shard3']];
        $tables['user14'] = ['name' => 'user14', 'comment' => 'User 14', 'databases' => ['shard1', 'shard2', 'shard3']];
        $tables['user15'] = ['name' => 'user15', 'comment' => 'User 15', 'databases' => ['shard1', 'shard2', 'shard3']];
        $tables['user16'] = ['name' => 'user16', 'comment' => 'User 16', 'databases' => ['shard1', 'shard2', 'shard3']];
        $tables['user17'] = ['name' => 'user17', 'comment' => 'User 17', 'databases' => ['shard1', 'shard2', 'shard3']];
        $tables['user18'] = ['name' => 'user18', 'comment' => 'User 18', 'databases' => ['shard1', 'shard2', 'shard3']];
        $tables['user19'] = ['name' => 'user19', 'comment' => 'User 19', 'databases' => ['shard1', 'shard2', 'shard3']];
        $tables['user20'] = ['name' => 'user20', 'comment' => 'User 20', 'databases' => ['shard1', 'shard2', 'shard3']];
        $tables['user21'] = ['name' => 'user21', 'comment' => 'User 21', 'databases' => ['shard1', 'shard2', 'shard3']];
        $tables['user22'] = ['name' => 'user22', 'comment' => 'User 22', 'databases' => ['shard1', 'shard2', 'shard3']];
        $tables['user23'] = ['name' => 'user23', 'comment' => 'User 23', 'databases' => ['shard1', 'shard2', 'shard3']];
        $tables['user24'] = ['name' => 'user24', 'comment' => 'User 24', 'databases' => ['shard1', 'shard2', 'shard3']];
        $tables['user25'] = ['name' => 'user25', 'comment' => 'User 25', 'databases' => ['shard1', 'shard2', 'shard3']];
        $tables['user26'] = ['name' => 'user26', 'comment' => 'User 26', 'databases' => ['shard1', 'shard2', 'shard3']];
        $tables['user27'] = ['name' => 'user27', 'comment' => 'User 27', 'databases' => ['shard1', 'shard2', 'shard3']];
        $tables['user28'] = ['name' => 'user28', 'comment' => 'User 28', 'databases' => ['shard1', 'shard2', 'shard3']];
        $tables['user29'] = ['name' => 'user29', 'comment' => 'User 29', 'databases' => ['shard1', 'shard2', 'shard3']];
        $tables['user30'] = ['name' => 'user30', 'comment' => 'User 30', 'databases' => ['shard1', 'shard2', 'shard3']];

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
