<?php
namespace MultiDbSqlTool;

class WebHandler
{
    /**
     * @var SessionManager
     */
    protected $sessionManager;

    /**
     * Request method
     *
     * @var string
     */
    protected $method = '';

    /**
     * Action parameter
     *
     * @var string
     */
    protected $action = '';

    /**
     * Cluster name parameter
     *
     * @var string
     */
    protected $clusterName = '';

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->sessionManager = new SessionManager();

        $this->method = $_SERVER['REQUEST_METHOD'] ?? '';
        $this->action = $_REQUEST['action'] ?? '';
        $this->clusterName = $_REQUEST['cluster'] ?? '';

        try {
            $this->validateCluster();
        } catch (\Throwable $e) {
            http_response_code(400);
            $this->json(['error' => $e->getMessage()]);
        }
    }

    /**
     * Check if the request method is POST
     *
     * @return boolean
     */
    protected function isPostMethod()
    {
        return 'POST' === strtoupper($this->method);
    }

    /**
     * Validate the incoming request
     *
     * @return void
     */
    protected function validateCluster()
    {
        if (0 === strlen($this->clusterName)) {
            return;
        }
        if (!in_array($this->clusterName, Config::getInstance()->getClusterNames(), true)) {
            throw new \Exception('Invalid cluster name');
        }
    }

    /**
     * Basic authentication check
     *
     * @return boolean
     */
    protected function checkBasicAuth()
    {
        $basicAuthConfig = Config::getInstance()->get('basic_auth', []);

        if (empty($basicAuthConfig)) {
            return true; // No auth required
        }

        if (!isset($_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW'])) {
            $this->requireAuth();
            return false;
        }

        $user = $_SERVER['PHP_AUTH_USER'];
        $pass = $_SERVER['PHP_AUTH_PW'];

        foreach ($basicAuthConfig as $credentials) {
            if ($credentials[0] === $user && $credentials[1] === $pass) {
                return true;
            }
        }

        $this->requireAuth();
        return false;
    }

    /**
     * Send HTTP Basic Auth requirement
     *
     * @return void
     */
    protected function requireAuth()
    {
        header('WWW-Authenticate: Basic realm="Multi-DB SQL Tool"');
        http_response_code(401);
        exit('Authentication Required');
    }

    /**
     * Apply query execution limits
     *
     * @return void
     */
    protected function applyExecutionLimits()
    {
        $limits = Config::getInstance()->get('limits', []);

        $maxExecutionTime = $limits['max_execution_time'] ?? 30;
        set_time_limit($maxExecutionTime);

        $memoryLimit = $limits['memory_limit'] ?? '256M';
        ini_set('memory_limit', $memoryLimit);
    }

    /**
     * Validate query limits
     *
     * @param array $sqls
     * @return boolean
     * @throws \InvalidArgumentException
     */
    protected function validateQueryLimits(array $sqls)
    {
        $limits = Config::getInstance()->get('limits', []);
        $maxQueries = $limits['max_queries_per_request'] ?? 10;

        if (count($sqls) > $maxQueries) {
            throw new \InvalidArgumentException("Too many queries. Maximum {$maxQueries} allowed.");
        }

        return true;
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

        try {
            $this->applyExecutionLimits();

            $resultSet = [];
            $targetShards = $_REQUEST['shards'] ?? [];
            $reqSql = $_REQUEST['sql'] ?? '';

            if (empty(trim($reqSql))) {
                $this->json(['error' => 'SQL query is required']);
            }

            $sqls = Utility::splitSqlStatements($reqSql);
            $this->validateQueryLimits($sqls);

            // Read-only mode validation
            if (Config::getInstance()->isReadOnlyMode()) {
                if (!Utility::canExecuteQuery($reqSql)) {
                    $this->json(['error' => 'Query not allowed in read-only mode']);
                }
            }

            $hasError = false;
            $id = 1;
            $totalRows = 0;

            foreach ($sqls as $sql) {
                $result = [];
                $error = null;
                $executionTime = 0;

                try {
                    $startTime = microtime(true);

                    $dbSettings = Config::getInstance()->getDatabaseSettings($this->clusterName, $targetShards);
                    $query = new Query($sql);
                    $query->bulkAddConnections($dbSettings);
                    $result = $query->query();

                    $executionTime = round((microtime(true) - $startTime) * 1000, 2); // ms
                    $totalRows += $result['rows'];
                    if (false === $hasError && !empty($result['errors'])) {
                        $hasError = true;
                    }
                } catch (\Throwable $e) {
                    $error = $e->getMessage();
                    if (false === $hasError) {
                        $hasError = true;
                    }
                }

                $result += [
                    'error' => $error,
                    'results' => $result['results'] ?? [],
                    'rows' => $result['rows'] ?? 0,
                    'errors' => $result['errors'] ?? [],
                    'sql' => $sql,
                    'id' => $id,
                    'executionTime' => $executionTime,
                ];

                $resultSet[] = $result;
                $id++;
            }

            $this->sessionManager->addQueryHistory($reqSql, $this->clusterName);

            //sleep(3);
            $this->json([
                'cluster' => $this->clusterName,
                'resultSet' => $resultSet,
                'hasError' => $hasError,
                'totalRows' => $totalRows,
                'queryCount' => count($sqls)
            ]);
        } catch (\Throwable $e) {
            $this->json(['error' => $e->getMessage()]);
        }
    }

    /**
     * API: Get query history
     *
     * @return void
     */
    protected function processApiHistory()
    {
        try {
            $histories = $this->sessionManager->getQueryHistory();
            $this->json([
                'histories' => $histories,
            ]);
        } catch (\Throwable $e) {
            http_response_code(400);
            $this->json(['error' => $e->getMessage()]);
        }
    }

    /**
     * API: Get initial data
     *
     * @return void
     */
    protected function processApiInitialData()
    {
        list($tables, $error, $connectionErrors) = Query::getTableList($this->clusterName);
        $this->json([
            'cluster' => $this->clusterName,
            'shardList' => Config::getInstance()->getShardNames($this->clusterName),
            'tables' => $tables,
            'connectionErrors' => $connectionErrors,
            'error' => $error,
        ]);
    }

    /**
     * Normal web request handler
     *
     * @param callable|null $templateFunction
     * @return void
     */
    protected function processWeb($templateFunction = null)
    {
        $appName = Config::APP_NAME;
        $appShortName = Config::APP_SHORT_NAME;
        $appShortNameLower = strtolower($appShortName);
        $version = Config::VERSION;
        $optionalName = Config::getInstance()->get('optional_name', '');
        $optionalName = $optionalName ? " for {$optionalName}" : '';
        $clausterList = Config::getInstance()->getClusterNames();
        $readOnlyMode = Config::getInstance()->isReadOnlyMode();
        $cssDevMode = Config::getInstance()->cssDevMode();
        $jsDevMode = Config::getInstance()->jsDevMode();

        if (is_callable($templateFunction)) {
            $templateFunction(compact(
                'appName',
                'appShortName',
                'appShortNameLower',
                'version',
                'optionalName',
                'clausterList',
                'readOnlyMode',
                'cssDevMode',
                'jsDevMode'
            ));
            return;
        }
    }

    /**
     * Main execution function
     *
     * @param callable|null $templateFunction
     * @return void
     */
    public function execute($templateFunction = null)
    {
        try {
            // Basic authentication check
            if (!$this->checkBasicAuth()) {
                return;
            }

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
                    $this->processWeb($templateFunction);
                    break;
            }
        } catch (\Throwable $e) {
            if ($this->action && strpos($this->action, 'api_') === 0) {
                $this->json(['error' => 'Internal server error']);
            } else {
                http_response_code(500);
                echo 'Internal Server Error';
            }
        }
    }
}
