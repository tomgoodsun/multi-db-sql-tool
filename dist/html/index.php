<?php

namespace MultiDbSqlTool;


class Config
{

    /**
     * App info
     */
    const APP_NAME = 'Multi-DB SQL Tool';
    const APP_SHORT_NAME = 'mDBSQL';
    const VERSION = '1.0.0';

    const DEFAULT_SESSION_NAME = 'MDBSQL_SESSION';
    const DEFAULT_SESSION_LIFETIME = 86400; // 1 day
    const MAX_QUERY_HISTORY = 50;

    private static $instance = null;

    protected $settings = [];

    /**
     * Constructor
     *
     * @param string|null $configPath
     * @throws \RuntimeException
     */
    protected function __construct($configPath = null)
    {
        if (null === $configPath) {
            $configPath = __DIR__ . '/../config/config.php';
        }

        if (!file_exists($configPath)) {
            throw new \RuntimeException("Configuration file not found: {$configPath}");
        }

        // Load configuration settings
        $this->settings = require $configPath;
    }

    /**
     * Get the instance of the Config class.
     * Used to retrieve the singleton instance.
     *
     * @return self
     */
    public static function getInstance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Initialize the configuration.
     *
     * @param string|null $configPath
     * @return void
     */
    public static function initialize($configPath = null)
    {
        if (null === self::$instance) {
            self::$instance = new self($configPath);
        }
    }

    /**
     * Get a configuration value by key.
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function get($key, $default = null)
    {
        return self::searchArrayByPath($this->settings, $key, $default);
    }

    /**
     * Search an array by a dot-notated path.
     *
     * @param array $array
     * @param string $path
     * @param mixed $default
     * @return mixed
     */
    public static function searchArrayByPath(array $array, $path, $default = null)
    {
        $keys = explode('.', $path);
        foreach ($keys as $key) {
            if (is_array($array) && array_key_exists($key, $array)) {
                $array = $array[$key];
            } else {
                return $default;
            }
        }
        return $array;
    }

    /**
     * Get the names of all database clusters.
     *
     * @return string[]
     */
    public static function getClusterNames()
    {
        return array_keys(self::getInstance()->get('dbs', []));
    }

    /**
     * Check if cluster exists
     *
     * @param string $clusterName
     * @return boolean
     */
    public static function clusterExists($clusterName)
    {
        return in_array($clusterName, self::getClusterNames());
    }

    /**
     * Get database settings for a specific cluster.
     *
     * @param string $clusterName
     * @param array $targetShards
     * @return array
     * @throws \InvalidArgumentException
     */
    public static function getDatabaseSettings($clusterName, array $targetShards = [])
    {
        if (!self::clusterExists($clusterName)) {
            throw new \InvalidArgumentException("Cluster '{$clusterName}' not found");
        }

        $dbSettings = self::getInstance()->get("dbs.$clusterName", []);
        if (empty($targetShards)) {
            return $dbSettings;
        }

        $result = [];
        foreach ($targetShards as $shard) {
            if (array_key_exists($shard, $dbSettings)) {
                $result[$shard] = $dbSettings[$shard];
            }
        }
        return $result;
    }

    /**
     * Get shard names for a specific cluster
     *
     * @param string $clusterName
     * @return array
     * @throws \InvalidArgumentException
     */
    public static function getShardNames($clusterName)
    {
        $dbs = self::getDatabaseSettings($clusterName);
        return array_keys($dbs);
    }

    /**
     * Check if the application is in read-only mode.
     *
     * @return bool
     */
    public static function isReadOnlyMode()
    {
        return (bool)self::getInstance()->get('readonly_mode', false);
    }

    /**
     * Check if the application is in CSS development mode.
     *
     * @return bool
     */
    public static function cssDevMode()
    {
        return (bool)self::getInstance()->get('css_dev_mode', false);
    }

    /**
     * Check if the application is in JavaScript development mode.
     *
     * @return bool
     */
    public static function jsDevMode()
    {
        return (bool)self::getInstance()->get('js_dev_mode', false);
    }
}

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

class Utility
{
    /**
     * Split multiple SQL statements into individual statements
     *
     * @param string $sql
     * @return array
     */
    public static function splitSqlStatements($sql)
    {
        $statements = [];
        $buffer = '';
        $inString = false;
        $len = strlen($sql);
        for ($i = 0; $i < $len; $i++) {
            $char = $sql[$i];
            if ("'" === $char || '"' === $char) {
                $inString = !$inString;
            }
            if (';' === $char && !$inString) {
                $statements[] = trim($buffer) . ';';
                $buffer = '';
            } else {
                $buffer .= $char;
            }
        }
        if ('' !== trim($buffer)) {
            $statements[] = trim($buffer);
        }
        return $statements;
    }

    /**
     * Clean the SQL query by removing comments and extra whitespace.
     *
     * @param string $sql
     * @return string
     */
    public static function cleanSql($sql)
    {
        // Remove comments and unnecessary whitespace
        $lines = explode("\n", $sql);
        $cleanedLines = [];
        foreach ($lines as $line) {
            $line = preg_replace('/--.*$/', '', $line); // Remove single-line comments
            $line = preg_replace('/\/\*.*?\*\//s', '', $line); // Remove multi-line comments
            if ('' !== trim($line)) {
                $cleanedLines[] = trim($line);
            }
        }
        return implode("\n", $cleanedLines);
    }

    /**
     * Check if the SQL query is read-only (SELECT, SHOW, DESCRIBE, EXPLAIN)
     *
     * @param string $sql
     * @return boolean
     */
    public static function isReadOnlyQuery($sql)
    {
        $sql = self::cleanSql($sql);
        $sql = trim($sql);
        $sql = preg_replace('/^[\s\(]+/', ' ', $sql); // Remove leading whitespace and parentheses
        $sql = strtoupper($sql);
        $pattern = '/^\s*(SELECT|SHOW|DESCRIBE|DESC|EXPLAIN)\b/i';
        return 1 === preg_match($pattern, $sql);
    }

    /**
     * Check if the SQL query can be executed based on read-only mode setting.
     *
     * @param string $sql
     * @return boolean
     */
    public static function canExecuteQuery($sql)
    {
        if (!Config::getInstance()->isReadOnlyMode()) {
            return true;
        }

        $sqls = self::splitSqlStatements($sql);
        foreach ($sqls as $stmt) {
            if (!self::isReadOnlyQuery($stmt)) {
                return false;
            }
        }
        return true;
    }
}

class Query
{
    /**
     * @var string
     */
    protected $sql = '';

    protected $isReadOnlyQuery = true;

    /**
     * @var array
     */
    protected $params = [];

    /**
     * @var \PDO[]
     */
    protected $connections = [];

    /**
     * @var int[]
     */
    protected $rowCounts = [];

    /**
     * @var array
     */
    protected $resultSet = [];

    /**
     * @var string[]
     */
    protected $errors = [];

    /**
     * Connection errors
     *
     * @var string[]
     */
    protected $connectionErrors = [];

    /**
     * Constructor
     *
     * @param string $sql
     * @param array $params
     */
    public function __construct($sql, $params = [])
    {
        $this->sql = trim($sql);
        $this->params = $params;
        $this->isReadOnlyQuery = Utility::isReadOnlyQuery($this->sql);
    }

    /**
     * Create DSN string from connection config
     *
     * @param array $conn
     * @return string
     */
    public static function createDsn(array $conn)
    {
        $dsn = 'mysql:';
        foreach (['host', 'port', 'dbname', 'charset'] as $key) {
            if (array_key_exists($key, $conn)) {
                $dsn .= "$key={$conn[$key]};";
            }
        }
        return $dsn;
    }

    /**
     * Create PDO connection with proper options
     *
     * @param string $dsn
     * @param string $username
     * @param string $password
     * @return \PDO
     */
    protected function createConnection($dsn, $username, $password)
    {
        $limits = Config::getInstance()->get('limits', []);
        $timeout = $limits['connection_timeout'] ?? 10;

        $options = [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            \PDO::ATTR_TIMEOUT => $timeout,
            \PDO::MYSQL_ATTR_INIT_COMMAND => "SET sql_mode='STRICT_TRANS_TABLES', time_zone='+00:00'",
            \PDO::MYSQL_ATTR_FOUND_ROWS => true
        ];

        return new \PDO($dsn, $username, $password, $options);
    }

    /**
     * Add multiple database connections.
     *
     * @param array $connections
     * @return $this
     */
    public function bulkAddConnections($connections)
    {
        foreach ($connections as $name => $conn) {
            $this->addConnection($name, self::createDsn($conn), $conn['username'], $conn['password']);
        }
        return $this;
    }

    /**
     * Add a database connection.
     *
     * @param string $name
     * @param string $dsn
     * @param string $username
     * @param string $password
     * @return $this
     */
    public function addConnection($name, $dsn, $username, $password)
    {
        try {
            $this->connections[$name] = $this->createConnection($dsn, $username, $password);
        } catch (\Throwable $e) {
            $this->connectionErrors[$name] = $e->getMessage();
        }
        return $this;
    }

    /**
     * Format the result set for a specific shard.
     *
     * @param string $shardName
     * @param array $result
     * @param array $results
     * @return array
     */
    public static function formatResult($shardName, array $result, array &$results)
    {
        foreach ($result as &$row) {
            $tmp = ['__shard' => $shardName];
            foreach ($row as $k => $v) {
                $tmp[$k] = $v;
            }
            $results[] = $tmp;
        }

        return $result;
    }

    /**
     * Execute the query and return the results.
     *
     * @return array
     */
    public function query()
    {
        $this->rowCounts = [];
        $this->resultSet = [];
        $results = [];
        foreach ($this->connections as $name => $connection) {
            try {
                $stmt = $connection->prepare($this->sql);
                $stmt->execute($this->params);
                $result = $stmt->fetchAll(\PDO::FETCH_ASSOC);
                if ($this->isReadOnlyQuery) {
                    $rowCount = count($result);
                    $result = self::formatResult($name, $result, $results);
                } else {
                    $rowCount = $stmt->rowCount();
                    $result = self::formatResult($name, [['affected_rows' => $rowCount]], $results);
                }
                $this->resultSet[$name] = $result;
                $this->rowCounts[$name] = $rowCount;
            } catch (\Throwable $e) {
                $this->errors[$name] = [
                    'shard' => $name,
                    'message' => $e->getMessage()
                ];
            }
        }

        return [
            'rows' => array_sum($this->rowCounts),
            'results' => $results,
            'errors' => $this->errors,
        ];
    }

    /**
     * Get the list of tables for a specific cluster.
     *
     * @param string $clusterName
     * @return array
     */
    public static function getTableList($clusterName)
    {
        $tables = [];
        $error = null;
        try {
            $sql = 'SELECT TABLE_NAME, TABLE_COMMENT FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = database();';
            $query = new self($sql);
            $query->bulkAddConnections(Config::getInstance()->getDatabaseSettings($clusterName));
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
        } catch (\Throwable $e) {
            $error = $e->getMessage();
        }
        return [$tables, $error, $query->connectionErrors];
    }
}

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
                } catch (\Throwable $e) {
                    $error = $e->getMessage();
                    $hasError = true;
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



function main()
{
    // Initialize configuration
    \MultiDbSqlTool\Config::initialize(__DIR__ . '/config.php');

    $templateFunction = function ($vars) {
        extract($vars);
        ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?php echo $appName . ' ' . $version; ?><?php echo ' ' . $optionalName; ?></title>
  <link rel="icon" type="image/svg+xml" href="favicon.svg">
  <link rel="alternate icon" href="favicon.ico">

  <link rel="stylesheet" href="assets/vendor/vendor.css">

  <link href="assets/app.css" rel="stylesheet">
  <link href="assets/codemirror-fix.css" rel="stylesheet">

</head>
<body>

  <div class="container-fluid app-container">
    <!-- header -->
    <div class="header">
      <h1>
        <!-- <img src="favicon.svg" alt="logo" class="app-logo"> -->
        <img src="data:image/png;base64,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" alt="logo" class="app-logo">
        <?php echo $appName . ' ' . $version; ?>
        <span class="optional-name"><?php echo ' ' . $optionalName; ?></span>
      </h1>

      <div class="header-controls">
        <select id="cluster-selector" class="form-select form-select-sm me-2">
          <?php
            foreach ($clausterList as $clusterName) {
              echo "<option value=\"{$clusterName}\">{$clusterName}</option>";
            }
          ?>
        </select>

        <? if ($readOnlyMode): ?>
        <span class="badge bg-warning mode-name">Read Only</span>
        <? else: ?>
        <span class="badge bg-danger mode-name">Write Enabled</span>
        <? endif; ?>
      </div>
    </div>

    <div class="row app-content">
      <!-- sidebar -->
      <div class="col-2 left-pane sidebar">
        <!-- Table list -->
        <div class="sidebar-section">
          <div id="sidebar-tabs" class="sidebar-tabs" role="tablist">
            <button class="sidebar-tab active" type="button" data-target="stab-1" role="tab">TABLE LIST</button>
            <button class="sidebar-tab" type="button" data-target="stab-2" role="tab">DB LIST
              <span class="badge bg-secondary" id="db-count">0</span>
              <span id="db-has-error"></span>
            </button>
          </div>

          <div id="sidebar-content" class="sidebar-content">
            <div class="tab-pane" id="stab-1" role="tabpanel">
              <div id="table-list" class="table-list">
                <!-- Table items will be generated by JavaScript -->
                <!--
                <div class="table-item">
                  <div class="table-physical-name">introduced_user_profile_header</div>
                  <div class="table-logical-name">Introduced User Profile Header (Unique)</div>
                </div>
                -->
              </div>
            </div>

            <div class="tab-pane" id="stab-2" role="tabpanel">
              <select id="db-selector" class="form-select form-select-sm mb-2" multiple>
                <!-- Database options will be generated by JavaScript -->
                <!--
                <option value="shard1">shard1</option>
                <option value="shard2">shard2</option>
                -->
              </select>
            </div>
          </div>
        </div>
      </div>

      <!-- right pane -->
      <div class="col-10 right-pane">
        <!-- Editor Container -->
        <div class="editor-container">

          <!-- Editor Toolbar -->
          <div class="editor-toolbar">
            <div class="toolbar-left">
              <!-- Open dialog and restore SQL in history to editor -->
              <button id="btn-history" class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#sql-history">
                <i class="bi bi-clock-history"></i> History
              </button>
            </div>
            <div class="toolbar-center">
              <!-- Use sql-formatter to clean SQL on editor -->
              <button id="btn-format" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-code"></i> Beautify SQL
              </button>
              <!-- Execute SQL, Ctrl+Enter also fires this event -->
              <!-- When event is triggered, confirmation modal will be shown -->
              <!-- Confirmation modal will ask for user confirmation before executing SQL -->
              <button id="btn-execute" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#execution-confirm">
                <i class="bi bi-play-fill"></i> Run (Ctrl+Enter)
              </button>
            </div>
            <div class="toolbar-right">
              <!-- Export CSV/XLSX by SheetJS -->
              <span class="me-2">Export</span>
              <button id="btn-export-csv" class="btn btn-outline-success btn-sm me-1">CSV</button>
              <button id="btn-export" class="btn btn-outline-success btn-sm">XLSX</button>
            </div>
          </div>

          <!-- SQL Editor -->
          <div class="sql-editor-container">
            <!-- Initialized with CodeMirror -->
            <div id="sql-editor"></div>
          </div>

        </div>

        <!-- Result Container -->
        <div class="result-container">
          <!-- Results Tabs -->
          <div class="results-tabs-container">
            <button class="tab-nav-btn tab-nav-left" id="tab-nav-left">
              <i class="bi bi-chevron-left"></i>
            </button>

            <div id="results-tabs" class="results-tabs" role="tablist">
              <!-- Tabs will be generated by JavaScript -->
              <!-- Format: Query X (Y) , X: Number of query, Y: found rows -->
              <!--
              <button class="results-tab active" type="button" data-target="tab-1" role="tab"><i class="bi bi-check-circle"></i> Query 1 (10)</button>
              <button class="results-tab error" type="button" data-target="tab-2" role="tab"><i class="bi bi-exclamation-triangle"></i> Query 2 (0)</button>
              -->
            </div>

            <button class="tab-nav-btn tab-nav-right" id="tab-nav-right">
              <i class="bi bi-chevron-right"></i>
            </button>
          </div>

          <!-- Results Grid -->
          <div id="results-content" class="results-content">
            <!-- Default view -->
            <div class="p-4 text-center text-muted">
              <i class="bi bi-database" style="font-size: 3rem; opacity: 0.3;"></i>
              <p class="mt-3">Results shown here.</p>
            </div>

            <!-- Tab panes will be generated JavaScript -->
            <!--
            <div class="tab-pane" id="tab-3" role="tabpanel">
              <div class="error-list">
                <div class="alert alert-danger align-items-center sql-error" role="alert">
                  <strong>ERROR [shard1]</strong> syntax error at or near "FROM"
                </div>
                <div class="alert alert-danger align-items-center sql-error" role="alert">
                  <strong>ERROR [shard1]</strong> syntax error at or near "FROM"
                </div>
                <div class="alert alert-danger align-items-center sql-error" role="alert">
                  <strong>ERROR [shard1]</strong> syntax error at or near "FROM"
                </div>
              </div>
            </div>
            -->
          </div>

        </div>
      </div>
    </div>
  </div>


  <!-- Modal: Alert Dialog -->
  <div class="modal fade" id="alert-dialog" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="alert-dialog-label" aria-hidden="true">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <h1 class="modal-title fs-5" id="alert-dialog-label"></h1>
        </div>
        <div class="modal-body"></div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">OK</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Modal: SQL Execution Confirmation -->
  <div class="modal fade" id="execution-confirm" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="execution-confirm-label" aria-hidden="true">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <h1 class="modal-title fs-5" id="execution-confirm-label">🔥Confirm!</h1>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          SQLs will be executed to all databases.<br>
          If updating queries are included, data will be modified.<br>
          Are you sure to execute these SQLs?
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="bi bi-x"></i> Cancel</button>
          <button type="button" class="btn btn-primary" id="btn-confirm-execute"><i class="bi bi-play-fill"></i> Execute</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Modal: SQL History -->
  <div class="modal fade" id="sql-history" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="sql-history-label" aria-hidden="true">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <div class="modal-header">
          <h1 class="modal-title fs-5" id="sql-history-label">📝SQL History</h1>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="bi bi-x"></i> Cancel</button>
        </div>
      </div>
    </div>
  </div>

  <script src="assets/vendor/vendor.js"></script>

  <script>
    window.MultiDbSql = {
      appShortName: '<?php echo $appShortName; ?>',
      appShortNameLower: '<?php echo $appShortNameLower; ?>',
      version: '<?php echo $version; ?>',
      isReadOnlyMode: <?php echo $readOnlyMode ? 'true' : 'false'; ?>,
      cssDevMode: <?php echo $cssDevMode ? 'true' : 'false'; ?>,
      jsDevMode: <?php echo $jsDevMode ? 'true' : 'false'; ?>,
    };
  </script>

  <!-- Custom JavaScript -->
  <script src="assets/app.js"></script>
</body>
</html>
        <?php
    };
    // Handle web requests
    $webHandler = new \MultiDbSqlTool\WebHandler();
    $webHandler->execute($templateFunction);
}

main();

