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

    protected function json(array $data)
    {
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }

    /**
     * Api
     *
     * @return void
     */
    protected function processApiQuery()
    {
        $resultSet = [];
        $sql = $_REQUEST['sql'] ?? '';

        $sqls = Utility::splitSqlStatements($sql);

        $hasError = false;
        foreach ($sqls as $sql) {
            $result = [];
            $error = null;
            try {
                $query = new Query($sql);
                $query->bulkAddConnections(Config::getInstance()->getDatabaseSettings($this->clusterName));
                $result = $query->query();
                $this->sessionManager->addQueryHistory($sql, $this->clusterName);
            } catch (\Exception $e) {
                $error = $e->getMessage();
                $hasError = true;
            }

            $result += [
                'error' => $error,
                'results' => [],
                'rows' => 0,
                'sql' => $sql,
            ];

            $resultSet[] = $result;
        }

        $this->json([
            'cluster' => $this->clusterName,
            'resultSet' => $resultSet,
            'hasError' => $hasError
        ]);
    }

    protected function processApiHistory()
    {
        $histories = $this->sessionManager->getQueryHistory();
        $this->json([
            'cluster' => $this->clusterName,
            'histories' => $histories,
        ]);
    }

    protected function processApiTableList()
    {
        $tables = [];
        $error = null;
        try {
            $sql = 'SELECT table_name, table_comment FROM information_schema.tables WHERE table_schema = database();';
            $query = new Query($sql);
            $query->bulkAddConnections(Config::getInstance()->getDatabaseSettings($this->clusterName));
            $result = $query->query();
            foreach ($result['results'] as $item) {
                $shard = $item['__shard'];
                $tableName = $item['table_name'];

                if (!isset($tables[$tableName])) {
                    $tables[$tableName] = [
                        'name' => $tableName,
                        'comment' => $item['table_comment'],
                        'databases' => [],
                    ];
                }
                $tables[$tableName]['databases'][] = $shard;
            }
        } catch (\Exception $e) {
            $error = $e->getMessage();
        }

        $this->json([
            'cluster' => $this->clusterName,
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


    public function execute()
    {
        switch ($this->action) {
            case 'api_query':
                $this->processApiQuery();
                break;
            case 'api_history':
                $this->processApiHistory();
                break;
            case 'api_table_list':
                $this->processApiTableList();
                break;
            default:
                $this->processWeb();
                break;
        }
    }
}
