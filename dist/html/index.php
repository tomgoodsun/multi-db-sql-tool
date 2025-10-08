<?php

namespace MultiDbSqlTool;


class Config
{

    /**
     * App info
     */
    const APP_NAME = 'Multi-DB SQL Tool';
    const APP_SHORT_NAME = 'mDBSQL';
    const VERSION = '1.0.0-alpha';

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

  <style>
/* assets/app.css */
/* Multi-DB SQL Tool Styles */

/* ==========================================================================
   CSS Variables
   ========================================================================== */

:root {
  --danger-color: #dc3545;
  --dark-color: #212529;
  --editor-height: 60vh;
  --header-height: 60px;
  --info-color: #0dcaf0;
  --light-color: #f8f9fa;
  --primary-color: #0d6efd;
  --secondary-color: #6c757d;
  --sidebar-width: 300px;
  --success-color: #198754;
  --warning-color: #ffc107;
}

/* ==========================================================================
   Base Elements
   ========================================================================== */

html,
body {
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
  height: 100%;
  margin: 0;
  padding: 0;
}

/* ==========================================================================
   Application Layout
   ========================================================================== */

.app-logo {
  height: 1.5em;
  margin-right: 0.5rem;
  vertical-align: middle;
}

.app-container {
  height: inherit;
  overflow: hidden;
}

.app-content {
  height: inherit;
  overflow: hidden;
}

/* ==========================================================================
   Header
   ========================================================================== */

.header {
  align-items: center;
  background: var(--dark-color);
  border-bottom: 2px solid var(--primary-color);
  color: white;
  display: flex;
  justify-content: space-between;
  margin-left: calc(-.5 * var(--bs-gutter-x));
  margin-right: calc(-.5 * var(--bs-gutter-x));
  margin-top: calc(-1 * var(--bs-gutter-y));
  padding: 1rem 1rem;
}

.header h1 {
  font-size: 1.25rem;
  font-weight: 600;
  margin: 0;
}

.header-controls {
  align-items: center;
  display: flex;
  gap: 1rem;
}

.mode-name {
  height: 2.5em;
  line-height: 1.5em;
}

/* ==========================================================================
   Layout Panes
   ========================================================================== */

.left-pane,
.right-pane {
  padding: 0;
}

/* ==========================================================================
   Sidebar
   ========================================================================== */

.sidebar {
  background: var(--light-color);
  border-right: 1px solid #dee2e6;
  display: flex;
  flex-direction: column;
  grid-area: sidebar;
  overflow: hidden;
}

.sidebar-section {
  border-bottom: 1px solid #dee2e6;
  padding: 0 0 1rem 0;
}

.sidebar-section h5 {
  color: var(--dark-color);
  font-size: 0.9rem;
  font-weight: 600;
  letter-spacing: 0.5px;
  margin: 1rem 1rem 0.75rem 1rem;
  text-transform: uppercase;
}

.sidebar-tabs,
.results-tabs {
  display: flex;
  flex: 1;
  overflow-x: auto;
  scrollbar-width: none;
  -ms-overflow-style: none;
}

.results-tabs::-webkit-scrollbar {
  display: none;
}

.sidebar-tab,
.results-tab {
  align-items: center;
  background: transparent;
  border: none;
  border-bottom: 2px solid transparent;
  cursor: pointer;
  display: flex;
  font-size: 0.875rem;
  gap: 0.25rem;
  padding: 0.5rem 1rem;
  white-space: nowrap;
}

.sidebar-tab {
  display: inline-block;
  text-align: center;
  width: 50%;
}

.sidebar-tab:hover,
.results-tab:hover {
  background: rgba(13, 110, 253, 0.1);
}

.sidebar-tab.active,
.results-tab.active {
  background: white;
  border-bottom-color: var(--primary-color);
  font-weight: 600;
}

/* ==========================================================================
   Connection Status
   ========================================================================== */

#cluster-selector {
  width: auto;
}

.connection-status {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.status-item {
  align-items: center;
  display: flex;
  font-size: 0.875rem;
  gap: 0.5rem;
}

.status-indicator {
  border-radius: 50%;
  flex-shrink: 0;
  height: 8px;
  width: 8px;
}

.status-indicator.connected {
  background-color: var(--success-color);
}

.status-indicator.failed {
  background-color: var(--danger-color);
}

/* ==========================================================================
   Table List
   ========================================================================== */

.table-list {
  font-size: 0.875rem;
  height: calc(100% - 200px);
  overflow-y: auto;
}

.table-item {
  border-bottom: 1px solid #e9ecef;
  border-radius: 0.25rem;
  cursor: pointer;
  font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
  padding: 0.5rem;
}

.table-item:hover {
  background-color: rgba(13, 110, 253, 0.1);
}

.table-physical-name {
  color: var(--dark-color);
  font-size: 0.875rem;
  font-weight: 600;
  line-break: anywhere;
}

.table-logical-name {
  color: var(--secondary-color);
  font-size: 0.75rem;
  font-weight: normal;
}

/* ==========================================================================
   Editor Area
   ========================================================================== */

.editor-area {
  border-bottom: 1px solid #dee2e6;
  display: flex;
  flex-direction: column;
  grid-area: editor;
  min-height: 0;
  overflow: hidden;
}

.editor-toolbar {
  align-items: center;
  background: #f8f9fa;
  border-bottom: 1px solid #dee2e6;
  display: flex;
  justify-content: space-between;
  padding: 0.5rem;
}

.toolbar-left,
.toolbar-center,
.toolbar-right {
  align-items: center;
  display: flex;
  gap: 0.5rem;
}

.toolbar-center {
  flex: 1;
  justify-content: center;
}

.editor-container {
  height: 30%;
}

.sql-editor-container {
  border: 1px solid #ddd;
  flex: 1;
  height: calc(100% - 50px);
  position: relative;
}

#sql-editor {
  height: 100%;
}

/* ==========================================================================
   CodeMirror Styles
   ========================================================================== */

.CodeMirror {
  border: none;
  border-radius: 4px;
  font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
  font-size: 12px;
  height: 100% !important;
  width: 100% !important;
}

.CodeMirror-scroll {
  height: 100% !important;
  overflow: auto !important;
}

.CodeMirror-gutters {
  border-right: 1px solid #ddd !important;
}

.CodeMirror-sizer {
  min-height: 100% !important;
}

.CodeMirror-lines {
  padding: 4px 0;
}

.CodeMirror-scrollbar-filler {
  background-color: transparent;
}

/* ==========================================================================
   Results Area
   ========================================================================== */

.result-container {
  height: 70%;
}

.results-area {
  display: flex;
  flex-direction: column;
  grid-area: results;
  overflow: hidden;
}

.results-tabs-container {
  align-items: center;
  background: #f8f9fa;
  border-bottom: 1px solid #dee2e6;
  display: flex;
  flex-shrink: 0;
}

.tab-nav-btn {
  background: transparent;
  border: none;
  color: var(--secondary-color);
  cursor: pointer;
  flex-shrink: 0;
  padding: 0.5rem;
}

.tab-nav-btn:hover {
  background: rgba(13, 110, 253, 0.1);
  color: var(--primary-color);
}

.tab-nav-btn:disabled {
  cursor: not-allowed;
  opacity: 0.3;
}

#db-has-error {
  color: var(--danger-color);
  font-weight: 600;
}

.results-tab.error {
  color: var(--danger-color);
}

.results-tab.error i {
  color: var(--warning-color);
}

.results-tab:not(.error) i {
  color: var(--success-color);
}

.results-tab .copy-btn {
  margin-left: 5px;
}

.results-content {
  flex: 1;
  overflow: hidden;
}

.tab-pane {
  display: none;
  flex-direction: column;
  height: 100%;
}

.tab-pane.active {
  display: flex;
}

.error-list {
  border-bottom: 1px solid #dee2e6;
  max-height: 150px;
  overflow-y: auto;
}

.sql-error {
  margin: 0.5rem 1rem 0.5rem 1rem;
}

.results-grid {
  flex: 1;
  overflow: auto;
}

/* ==========================================================================
   AG Grid Customization
   ========================================================================== */

.ag-theme-alpine {
  --ag-font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
  --ag-font-size: 13px;
}

.ag-header-cell-text {
  font-weight: 600;
}

.shard-column {
  background-color: #f8f9fa !important;
  color: var(--primary-color);
  font-weight: 600;
}

/* ==========================================================================
   Modal Styles
   ========================================================================== */

.modal-overlay {
  align-items: center;
  background: rgba(0, 0, 0, 0.5);
  bottom: 0;
  display: flex;
  justify-content: center;
  left: 0;
  position: fixed;
  right: 0;
  top: 0;
  z-index: 1050;
}

.modal-content {
  background: white;
  border-radius: 0.5rem;
  box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
  max-height: 90vh;
  max-width: 90vw;
  overflow: auto;
}

.modal-header {
  align-items: center;
  border-bottom: 1px solid #dee2e6;
  display: flex;
  justify-content: space-between;
  padding: 1rem;
}

.modal-body {
  padding: 1rem;
}

.modal-footer {
  border-top: 1px solid #dee2e6;
  display: flex;
  gap: 0.5rem;
  justify-content: flex-end;
  padding: 1rem;
}

.modal-icon-warning {
  color: var(--warning-color);
  font-size: 2rem;
  margin-right: 0.5rem;
}

.modal-icon-info {
  color: var(--info-color);
  font-size: 2rem;
  margin-right: 0.5rem;
}

.modal-icon-danger {
  color: var(--danger-color);
  font-size: 2rem;
  margin-right: 0.5rem;
}

.modal-icon-success {
  color: var(--success-color);
  font-size: 2rem;
  margin-right: 0.5rem;
}

/* ==========================================================================
   History Styles
   ========================================================================== */

.history-list {
  max-height: 400px;
  overflow-y: auto;
}

.history-item {
  border-bottom: 1px solid #dee2e6;
  cursor: pointer;
  padding: 0.75rem;
}

.history-item:hover {
  background: #f8f9fa;
}

.history-item:last-child {
  border-bottom: none;
}

.history-time {
  color: var(--secondary-color);
  font-size: 0.75rem;
  margin-bottom: 0.25rem;
}

.history-sql {
  font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
  font-size: 0.875rem;
  white-space: pre-wrap;
  word-break: break-all;
}

/* ==========================================================================
   Utility Classes
   ========================================================================== */

.btn-sm {
  font-size: 0.875rem;
  padding: 0.25rem 0.5rem;
}

.text-muted {
  color: var(--secondary-color) !important;
}

.text-success {
  color: var(--success-color) !important;
}

.text-danger {
  color: var(--danger-color) !important;
}

.d-none {
  display: none !important;
}

.d-flex {
  display: flex !important;
}

.align-items-center {
  align-items: center !important;
}

.gap-1 {
  gap: 0.25rem !important;
}

.gap-2 {
  gap: 0.5rem !important;
}

/* ==========================================================================
   Loading Indicator
   ========================================================================== */

.loading {
  animation: spin 1s linear infinite;
  border: 2px solid #f3f3f3;
  border-radius: 50%;
  border-top: 2px solid var(--primary-color);
  display: inline-block;
  height: 1rem;
  width: 1rem;
}

@keyframes spin {
  0% {
    transform: rotate(0deg);
  }
  100% {
    transform: rotate(360deg);
  }
}
</style>
  <style>
/* assets/codemirror-fix.css */
/* ==========================================================================
   CodeMirror Height Fix
   Additional CSS to fix CodeMirror auto-expansion height issue
   ========================================================================== */

/*
 * Fix for CodeMirror auto-expanding height problem
 * References:
 * - https://stackoverflow.com/questions/28378229/codemirror-how-to-limit-height-in-editor
 * - https://talk.tiddlywiki.org/t/give-the-codemirror-editor-a-max-height-but-still-auto-shrink/9069
 */

/* SQL Editor Container - Add height constraints */
.sql-editor-container {
  border: 1px solid #ddd;
  flex: 1;
  height: calc(100% - 50px);
  max-height: calc(100% - 50px);
  overflow: hidden;
  position: relative;
}

/* CodeMirror Main Container - Prevent auto height expansion */
.CodeMirror {
  border: none;
  border-radius: 4px;
  font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
  font-size: 12px;
  height: 100% !important;
  max-height: 100% !important;
  overflow: hidden;
  width: 100% !important;
}

/* CodeMirror Scroll Area - Enable internal scrolling */
.CodeMirror-scroll {
  height: 100% !important;
  max-height: 100% !important;
  overflow: auto !important;
}

/* CodeMirror Sizer - Prevent automatic height calculation */
.CodeMirror-sizer {
  min-height: auto !important;
}

/* Editor Container - Fix height ratio and constraints */
.editor-container {
  height: 30%;
  max-height: 50vh;
  min-height: 200px;
  overflow: hidden;
}

/* Result Container - Fix height ratio and constraints */
.result-container {
  height: 70%;
  min-height: 300px;
  overflow: hidden;
}

</style>

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
  <script>
/* assets/app.js */
(function(window, document) {
  let dbSelector = document.getElementById('db-selector');

  /**
   * Create a date-time string for the filename.
   *
   * @returns {string} - Formatted date-time string
   */
  let createDateTimeStrForFilename = () => {
    let dateStr = new Date().toISOString().slice(0, 19).replace(/:/g, '');
    dateStr = dateStr.replace('T', '_');
    dateStr = dateStr.replace(/-/g, '');
    return dateStr + 'Z';
  };

  /**
   * Get the currently selected cluster from the dropdown.
   *
   * @returns {string} - Selected cluster name
   */
  let getCurrentCluster = () => {
    let clusterName = document.getElementById('cluster-selector').value;
    return clusterName;
  };

  /**
   * Get the selected target shards (databases) from the multi-select dropdown.
   *
   * @returns {Array} - Selected target shards (databases)
   */
  let getTargetShards = () => {
    let selectedDbs = [];
    dbSelector.querySelectorAll('option').forEach(el => {
      if (el.selected) {
        selectedDbs.push(el.value);
      }
    });
    return selectedDbs;
  };

  // ------------------------------------------------------------

  let currentResults = {};
  let editorElement = document.getElementById('sql-editor');
  let sqlEditor = null;
  let isExecuting = false;
  let alertDialogElem = document.getElementById('alert-dialog');
  let alertDialog = null;
  let sqlExecutionDialogElem = document.getElementById('execution-confirm');
  let sqlExecutionDialog = null;
  let historyDialogElem = document.getElementById('sql-history');
  let historyDialog = null;

  /**
   * Format the SQL query in the editor using sql-formatter library.
   *
   * @returns {void}
   */
  let formatQuery = () => {
    let sql = sqlEditor.getValue().trim();
    if (!sql) {
      return;
    }

    sql = sqlFormatter.format(sql);
    sqlEditor.setValue(sql);
  };

  /**
   * Split SQL statements by semicolon.
   *
   * @param {string} sql
   * @returns {Array<string>}
   */
  let splitSql = (sql) => {
    // Simple split by semicolon, ignoring semicolons in quotes
    let sqls = [];
    let currentStatement = '';
    let inSingleQuote = false;
    let inDoubleQuote = false;
    let inBacktick = false;
    for (let char of sql) {
      if ("'" === char && !inDoubleQuote && !inBacktick) {
        inSingleQuote = !inSingleQuote;
      } else if ('"' === char && !inSingleQuote && !inBacktick) {
        inDoubleQuote = !inDoubleQuote;
      } else if ('`' === char && !inSingleQuote && !inDoubleQuote) {
        inBacktick = !inBacktick;
      }
      if (';' === char && !inSingleQuote && !inDoubleQuote && !inBacktick) {
        if (currentStatement.trim()) {
          sqls.push(currentStatement.trim());
          currentStatement = '';
        }
      } else {
        currentStatement += char;
      }
    }

    if (currentStatement.trim()) {
      sqls.push(currentStatement.trim());
    }
    return sqls;
  };

  /**
   * Clean the SQL query by removing comments and extra whitespace.
   *
   * @param {string} sql
   * @returns {string}
   */
  let cleanSql = (sql) => {
    // Remove comments and trim
    return sql.replace(/--.*$/gm, '').replace(/\/\*[\s\S]*?\*\//g, '').trim();
  };

  /**
   * Check if the SQL query is a reading query (SELECT, SHOW, DESC, DESCRIBE).
   *
   * @param {string} sql
   * @returns {boolean}
   */
  let isReadOnlyQuery = (sql) => {
    // Check if the SQL query is a SELECT, SHOW, DESC, DESCRIBE statement
    sql = cleanSql(sql);
    sql = sql.trim().toUpperCase();
    sql = sql.replace(/^[\s\(]+/, ''); // Remove leading spaces and parentheses
    return sql.startsWith('SELECT')
      || sql.startsWith('SHOW')
      || sql.startsWith('DESC')
      || sql.startsWith('DESCRIBE');
  };

  /**
   * Check if the SQL query can be executed (not read-only).
   *
   * @param {string} sql
   * @returns {boolean}
   */
  let canExecuteQuery = (sql) => {
    if (!window.MultiDbSql.isReadOnlyMode) {
      return true;
    }

    let sqls = splitSql(sql);
    if (0 === sqls.length) {
      return false;
    }

    return sqls.every(stmt => isReadOnlyQuery(stmt));
  };

  /**
   * Execute the SQL query in the editor.
   *
   * @returns {void}
   */
  let executeQuery = async () => {
    if (isExecuting) {
      return;
    }

    let sql = sqlEditor.getValue().trim();
    if (!sql) {
      console.log('No SQL query to execute.');
      showAlert('No SQL query to execute.', 'warning');
      sqlExecutionDialog.hide();
      return;
    }

    if (!canExecuteQuery(sql)) {
      console.log('This query is read-only and cannot be executed.');
      sqlExecutionDialog.hide();
      showAlert('This query is read-only and cannot be executed.', 'warning');
      return;
    }

    isExecuting = true;

    try {
      // POST / API call to execute SQL
      let postData = [];
      postData.push('action=api_query');
      postData.push('cluster=' + encodeURIComponent(getCurrentCluster()));
      getTargetShards().forEach(db => {
        postData.push('shards[]=' + encodeURIComponent(db));
      });
      postData.push('sql=' + encodeURIComponent(sql));

      let reqData = {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: postData.join('&')
      };

      // This API call must user POST method to avoid URL length limit
      fetch('', reqData)
        .then(response => response.json())
        .then(data => {
          if (data.hasError) {
            console.error('Query execution error:', data.error);
          }
          currentResults = data;

          renderResults(data);
        })
        .catch(error => {
          console.error('Error executing query:', error);
        })
        .finally(() => {
          isExecuting = false;
        });
    } catch (error) {
      console.error('Unexpected error:', error);
      isExecuting = false;
    }

    sqlExecutionDialog.hide();
  };

  /**
   * Show alert dialog with a message.
   *
   * @param {string} message
   * @param {string} type
   * @returns {void}
   */
  let showAlert = (message, type = 'info') => {
    alertDialogElem.querySelector('.modal-body').innerHTML = message.replace(/\n/g, '<br>');
    let title = '<i class="bi bi-info-circle modal-icon modal-icon-info"></i> Info';
    if ('warning' === type) {
      title = '<i class="bi bi-exclamation-octagon-fill modal-icon modal-icon-warning"></i> Warning';
    } else if ('danger' === type || 'error' === type) {
      title = '<i class="bi bi-x-octagon-fill modal-icon modal-icon-danger"></i> Error';
    } else if ('success' === type) {
      title = '<i class="bi bi-check-circle-fill modal-icon modal-icon-success"></i> Success';
    }
    alertDialogElem.querySelector('.modal-title').innerHTML = title;
    alertDialog.show();
  };

  /**
   * Show confirmation dialog for SQL execution.
   *
   * @returns {void}
   */
  let confirmSqlExecution = () => {
    sqlExecutionDialog.show();
  };

  /**
   * Initialize CodeMirror editor and bind buttons.
   *
   * @returns {void}
   */
  let initSqlEditor = () => {
    alertDialog = new bootstrap.Modal(alertDialogElem, {backdrop: 'static', keyboard: false});
    sqlExecutionDialog = new bootstrap.Modal(sqlExecutionDialogElem, {backdrop: 'static', keyboard: false});
    historyDialog = new bootstrap.Modal(historyDialogElem, {backdrop: 'static', keyboard: false});

    document.getElementById('btn-format')?.addEventListener('click', () => formatQuery());
    document.getElementById('btn-execute')?.addEventListener('click', () => confirmSqlExecution());
    document.getElementById('btn-confirm-execute')?.addEventListener('click', () => executeQuery());
    document.getElementById('btn-history')?.addEventListener('click', () => createHistoryContent());

    // Initialize CodeMirror
    sqlEditor = CodeMirror(editorElement, {
      mode: 'text/x-mysql',
      theme: 'eclipse',
      lineNumbers: true,
      indentUnit: 2,
      smartIndent: true,
      lineWrapping: false,
      //viewportMargin: Infinity,
      extraKeys: {
        'Ctrl-Enter': () => {
          confirmSqlExecution();
        },
        'Ctrl-Space': 'autocomplete'
      },
      value: ''
    });
    sqlEditor.setSize('100%', '100%');
  };

  /**
   * Create history content
   *
   * @returns {void}
   */
  let createHistoryContent = () => {
    fetch('?action=api_history')
      .then(response => response.json())
      .then(data => {
        let container = document.createElement('div');

        if (0 === data.histories.length) {
          container.innerHTML = '<p class="text-muted">No query history available.</p>';
          return container;
        }

        let historyList = document.createElement('div');
        historyList.className = 'history-list';

        data.histories.forEach((item) => {
          let historyItem = document.createElement('div');
          historyItem.className = 'history-item';
          historyItem.addEventListener('click', () => {
            sqlEditor.setValue(item.sql);
            historyDialog.hide();
          });

          let timeDiv = document.createElement('div');
          timeDiv.className = 'history-time';
          timeDiv.textContent = item.formattedTime + ' @' + item.cluster;

          let sqlDiv = document.createElement('div');
          sqlDiv.className = 'history-sql';
          sqlDiv.textContent = item.sql.length > 100 ? item.sql.substring(0, 100) + '...' : item.sql;

          historyItem.appendChild(timeDiv);
          historyItem.appendChild(sqlDiv);
          historyList.appendChild(historyItem);
        });

        container.appendChild(historyList);
        historyDialogElem.querySelector('.modal-body').innerHTML = '';
        historyDialogElem.querySelector('.modal-body').appendChild(container);
        historyDialog.show();
      })
      .catch(error => {
        console.error('Error fetching history:', error);
      });
  };

  initSqlEditor();

  // ------------------------------------------------------------

  /**
   * Initialize result tabs and bind events.
   *
   * @returns {void}
   */
  let initResultsTabs = () => {
    document.querySelectorAll('#results-tabs .results-tab').forEach(btn => {
      btn.addEventListener('click', evt => {
        let targetId = evt.target.dataset.target;
        activateResultTab(targetId);
      });
    });

    document.getElementById('tab-nav-left')?.addEventListener('click', () => scrollTabs('left'));
    document.getElementById('tab-nav-right')?.addEventListener('click', () => scrollTabs('right'));
    updateTabNavigation();
  };

  /**
   * Activate a result tab.
   *
   * @param {string} targetId
   */
  let activateResultTab = (targetId) => {
    activateTab(targetId, '#results-tabs .results-tab', '#results-content .tab-pane');
  };

  /**
   * Initialize sidebar tabs and bind events.
   *
   * @returns {void}
   */
  let initSidebarTabs = () => {
    document.querySelectorAll('#sidebar-tabs .sidebar-tab').forEach(btn => {
      btn.addEventListener('click', evt => {
        let targetId = evt.target.dataset.target;
        activateSidebarTab(targetId);
      });
    });
    activateSidebarTab('stab-1');
  };

  /**
   * Activate a sidebar tab.
   *
   * @param {string} targetId
   */
  let activateSidebarTab = (targetId) => {
    activateTab(targetId, '#sidebar-tabs .sidebar-tab', '#sidebar-content .tab-pane');
  };

  /**
   * Activate a tab.
   *
   * @param {string} targetId
   * @param {string} tabCssSelector
   * @param {string} tabContentSelector
   */
  let activateTab = (targetId, tabCssSelector, tabContentSelector) => {
    // Tab
    document.querySelectorAll(tabCssSelector).forEach(tab => {
      tab.classList.remove('active');
      if (tab.dataset.target === targetId) {
        tab.classList.add('active');
      }
    });

    // Tab panel
    document.querySelectorAll(tabContentSelector).forEach(pane => {
      pane.classList.remove('active');
      if (pane.id === targetId) {
        pane.classList.add('active');
      }
    });
  };

  /**
   * Scroll the result tabs.
   *
   * @param {string} direction - The direction to scroll ('left' or 'right').
   */
  let scrollTabs = (direction) => {
    const tabsContainer = document.getElementById('results-tabs');
    if (!tabsContainer) {
      return;
    }

    const scrollAmount = 200;
    const currentScroll = tabsContainer.scrollLeft;

    if ('left' === direction) {
      tabsContainer.scrollTo({
        left: currentScroll - scrollAmount,
        behavior: 'smooth'
      });
    } else {
      tabsContainer.scrollTo({
        left: currentScroll + scrollAmount,
        behavior: 'smooth'
      });
    }

    setTimeout(() => updateTabNavigation(), 300);
  };

  /**
   * Update the state of the tab navigation buttons.
   *
   * @returns {void}
   */
  let updateTabNavigation = () => {
    const tabsContainer = document.getElementById('results-tabs');
    const leftBtn = document.getElementById('tab-nav-left');
    const rightBtn = document.getElementById('tab-nav-right');

    if (!tabsContainer || !leftBtn || !rightBtn) {
      return;
    }

    const { scrollLeft, scrollWidth, clientWidth } = tabsContainer;

    leftBtn.disabled = scrollLeft <= 0;
    rightBtn.disabled = scrollLeft >= scrollWidth - clientWidth - 1;
  };

  initResultsTabs();
  initSidebarTabs();

  // ------------------------------------------------------------

  /**
   * Create a result grid using ag-Grid.
   *
   * @param {HTMLElement} container - The container element to hold the grid.
   * @param {Array} combinedData - The data to display in the grid.
   */
  let createResultGrid = (container, combinedData) => {
    if (!combinedData || 0 === combinedData.length) {
      return;
    }

    let gridDiv = document.createElement('div');
    gridDiv.className = 'results-grid ag-theme-alpine';
    container.appendChild(gridDiv);

    let columnDefs = Object.keys(combinedData[0]).map(key => ({
      field: key,
      headerName: '_shard' === key ? 'DB' : key,
      sortable: true,
      filter: true,
      resizable: true,
      pinned: '_shard' === key ? 'left' : false,
      cellClass: '_shard' === key ? 'shard-column' : '',
      width: '_shard' === key ? 150 : undefined
    }));

    let gridOptions = {
      columnDefs,
      rowData: combinedData,
      defaultColDef: {
        flex: 1,
        minWidth: 100
      },
      suppressRowClickSelection: true,
      enableCellTextSelection: true
    };

    // new agGrid.Grid(gridDiv, gridOptions); is deprecated.
    // see: https://www.ag-grid.com/javascript-data-grid/upgrading-to-ag-grid-31/#creating-ag-grid
    agGrid.createGrid(gridDiv, gridOptions);

  };

  /**
   * Render the query results into tabs and grids.
   *
   * @param {Object} response - The response object containing the result set.
   */
  let renderResults = (response) => {
    let tabArea = document.getElementById('results-tabs');
    let gridArea = document.getElementById('results-content');

    tabArea.innerHTML = '';
    gridArea.innerHTML = '';

    response.resultSet.forEach(result => {
      let id = result.id;
      let errors = result.errors;
      let rows = result.rows;
      let sql = result.sql;
      let results = result.results;

      // Tab format 1 (Success)
      // <button class="results-tab" type="button" data-target="tab-1" role="tab">
      //   <i class="bi bi-check-circle"></i> Query 1 (10)
      // </button>
      // Tab format 2 (Error)
      // <button class="results-tab error" type="button" data-target="tab-2" role="tab">
      //   <i class="bi bi-exclamation-triangle"></i> Query 2 (0)
      // </button>
      let tabBtn = document.createElement('button');
      tabBtn.className = 'results-tab';

      if (errors.length > 0) {
        tabBtn.classList.add('error');
        tabBtn.innerHTML = `<i class="bi bi-exclamation-triangle"></i> Query ${id} (${rows})`;
      } else {
        tabBtn.innerHTML = `<i class="bi bi-check-circle"></i> Query ${id} (${rows})`;
      }
      tabBtn.type = 'button';
      tabBtn.setAttribute('data-target', `tab-${id}`);
      tabBtn.setAttribute('role', 'tab');
      tabBtn.addEventListener('click', evt => {
        let targetId = evt.target.dataset.target;
        activateResultTab(targetId);
      });

      // Copy to clipboard button
      // Use tooltip and show "Copy results as TSV to clipboard" message when hovered
      // After clicked, show "Copied!" tooltip and fade out after 2 seconds
      let copyBtn = document.createElement('a');
      copyBtn.className = 'copy-btn';
      copyBtn.innerHTML = '<i class="bi bi-clipboard"></i>';
      copyBtn.href = 'javascript:void(0);';
      copyBtn.title = 'Copy results as TSV to clipboard';
      copyBtn.innerHTML = '<i class="bi bi-clipboard"></i>';

      // TODO: Conflicts with copy complete message tooltip
      //copyBtn.addEventListener('mouseover', () => {
      //  let tooltip = bootstrap.Tooltip.getInstance(copyBtn);
      //  if (!tooltip) {
      //    tooltip = new bootstrap.Tooltip(copyBtn);
      //  }
      //  tooltip.show();
      //});
      //copyBtn.addEventListener('mouseout', () => {
      //  let tooltip = bootstrap.Tooltip.getInstance(copyBtn);
      //  if (tooltip) {
      //    tooltip.hide();
      //  }
      //});

      copyBtn.addEventListener('click', evt => {
        evt.stopPropagation();
        copyTsvToClipboard(id);
        copyBtn.title = 'Copied result of this tab as TSV!';
        let tooltip = bootstrap.Tooltip.getInstance(copyBtn);
        if (!tooltip) {
          tooltip = new bootstrap.Tooltip(copyBtn);
        }
        tooltip.show();
        setTimeout(() => {
          tooltip.hide();
          copyBtn.title = 'Copy results as TSV to clipboard';
        }, 2000);
      });
      tabBtn.appendChild(copyBtn);

      tabArea.appendChild(tabBtn);

      // Tab panel format (error + grid), no error-list if no error
      // <div class="tab-pane" id="tab-3" role="tabpanel">
      //   <div class="error-list">
      //     <div class="alert alert-danger align-items-center sql-error" role="alert">
      //       <strong>ERROR [shard1]</strong> syntax error at or near "FROM"
      //     </div>
      //   </div>
      // </div>
      let tabPane = document.createElement('div');
      tabPane.className = 'tab-pane';
      tabPane.id = `tab-${id}`;
      tabPane.setAttribute('role', 'tabpanel');
      gridArea.appendChild(tabPane);

      if (errors.length > 0) {
        let errorListDiv = document.createElement('div');
        errorListDiv.className = 'error-list';
        tabPane.appendChild(errorListDiv);
        for (let error of errors) {
          let errorDiv = document.createElement('div');
          errorDiv.className = 'alert alert-danger align-items-center sql-error';
          errorDiv.setAttribute('role', 'alert');
          errorDiv.innerHTML = `<strong>ERROR [${error.shard}]</strong> ${error.message}`;
          errorListDiv.appendChild(errorDiv);
        }
      }
      createResultGrid(tabPane, results);
    });
    activateResultTab('tab-1');
  };

  // ------------------------------------------------------------

  /**
   * Copy results as TSV to clipboard
   *
   * Coprying multiple selected cells as TSV is not supported on ag-Grid Community Edition.
   * Enterprise Edition supports it.
   * This functionality is implemented manually here.
   *
   * @param {string} targetResultId
   * @returns {void}
   */
  let copyTsvToClipboard = (targetResultId) => {
    if (!currentResults || 0 === Object.keys(currentResults).length) {
      showAlert('No results to copy', 'warning');
      return;
    }

    let dataToTsv = [];
    let results = currentResults.resultSet.find(item => item.id === targetResultId);
    if (!results || 0 === results.results.length) {
      showAlert('No results to copy', 'warning');
      return;
    }

    let headers = Object.keys(results.results[0]);
    dataToTsv.push(headers.join('\t'));

    results.results.forEach(row => {
      let rowValues = headers.map(header => {
        let value = row[header] || '';
        return String(value).replace(/\t/g, ' ').replace(/\n/g, ' ');
      });
      dataToTsv.push(rowValues.join('\t'));
    });
    let tsvContent = dataToTsv.join('\n');

    navigator.clipboard.writeText(tsvContent).then(() => {
      //showAlert('Results copied to clipboard', 'success');
      console.log('Results copied to clipboard');
    }).catch(err => {
      showAlert('Failed to copy results: ' + err.message, 'danger');
      console.error('Clipboard copy error:', err);
    });
  };

  /**
   * Export results to CSV
   *
   * @returns {void}
   */
  let exportResultsCsv = () => {
    if (!currentResults || 0 === Object.keys(currentResults).length) {
      showAlert('No results to export', 'warning');
      return;
    }

    let dateStr = createDateTimeStrForFilename();

    currentResults.resultSet.forEach(resultSetItem => {
      let csv = convertToCSV(resultSetItem.results);
      let filename = `${window.MultiDbSql.appShortNameLower}-result-${dateStr}-query-${resultSetItem.id}.csv`;
      downloadCSV(csv, filename);
    });
  };

  /**
   * Convert data to CSV format
   *
   * @param {Object} data
   * @returns
   */
  let convertToCSV = (data) => {
    if (0 === data.length) {
      return '';
    }

    let headers = Object.keys(data[0]);
    let csvContent = [
      headers.join(','),
      ...data.map(row =>
        headers.map(header => {
          let value = row[header] || '';
          let escaped = String(value).replace(/"/g, '""');
          if (escaped.includes(',') || escaped.includes('"') || escaped.includes('\n')) {
            return `"${escaped}"`;
          }
          return escaped;
        }).join(',')
      )
    ].join('\n');

    return csvContent;
  };

  /**
   * Download CSV file
   *
   * @param {string} csvContent
   * @param {string} filename
   */
  let downloadCSV = (csvContent, filename) => {
    let blob = new Blob([csvContent], {type: 'text/csv;charset=utf-8;'});
    let link = document.createElement('a');
    if (undefined !== link.download) {
      let url = URL.createObjectURL(blob);
      link.setAttribute('href', url);
      link.setAttribute('download', filename);
      link.style.visibility = 'hidden';
      document.body.appendChild(link);
      link.click();
      document.body.removeChild(link);
    }
  };

  /**
   * Export results to XLSX
   *
   * @returns {void}
   */
  let exportResults = () => {
    if (!currentResults || 0 === Object.keys(currentResults).length) {
      showAlert('No results to export', 'warning');
      return;
    }

    try {
      // Options for workbook
      let wsopts = {
        dateNF: 'yyyy-mm-dd hh:mm:ss'
      };

      let workbook = XLSX.utils.book_new();

      currentResults.resultSet.forEach(resultSetItem => {
        let combinedData = resultSetItem.results;
        if (0 === combinedData.length) {
          combinedData = [{}];
        }
        let worksheet = XLSX.utils.json_to_sheet(combinedData, wsopts);
        let sheetName = `Query ${resultSetItem.id}`;
        XLSX.utils.book_append_sheet(workbook, worksheet, sheetName);
      });

      let dateStr = createDateTimeStrForFilename();
      let filename = `${window.MultiDbSql.appShortNameLower}-results-${dateStr}.xlsx`;
      XLSX.writeFile(workbook, filename);

      //showAlert('Results exported successfully', 'success');
      console.log('Results exported successfully', 'success');
    } catch (error) {
      showAlert('Export failed: ' + error.message, 'danger');
      console.error('Export error:', error);
    }
  };

  /**
   * Initialize result exporter by binding buttons.
   *
   * @returns {void}
   */
  let initResultExporter = () => {
    document.getElementById('btn-export-csv')?.addEventListener('click', () => exportResultsCsv());
    document.getElementById('btn-export')?.addEventListener('click', () => exportResults());
  };

  initResultExporter();

  // ------------------------------------------------------------

  /**
   * Initialize the application by loading cluster settings and populating the UI.
   *
   * @returns {void}
   */
  let initialize = () => {
    let clusterName = getCurrentCluster();

    fetch(`?action=api_initial_data&cluster=${encodeURIComponent(clusterName)}`)
      .then(response => response.json())
      .then(data => {
        let elem = document.getElementById('table-list');
        elem.innerHTML = '';
        for (let i in data.tables) {
          let table = data.tables[i];
          let itemDiv = document.createElement('div');
          itemDiv.className = 'table-item';

          let physicalDiv = document.createElement('div');
          physicalDiv.className = 'table-physical-name';
          physicalDiv.textContent = table.name;

          let logicalDiv = document.createElement('div');
          logicalDiv.className = 'table-logical-name';
          logicalDiv.textContent = table.comment || table.name;

          itemDiv.appendChild(physicalDiv);
          itemDiv.appendChild(logicalDiv);
          elem.appendChild(itemDiv);
        };

        dbSelector.innerHTML = '';
        data.shardList.forEach((db, i) => {
          let option = document.createElement('option');
          let text = db;
          if (db in data.connectionErrors) {
            console.error(`Connection error for shard ${db}:`, data.connectionErrors[db]);
            text += ' ⚠ (F12 for details)';
            option.disabled = true;
            document.getElementById('db-has-error').innerText = '⚠';
          }
          option.value = db;
          option.innerHTML = text;
          option.setAttribute('selected', true);
          dbSelector.appendChild(option);
        });
      })
      .catch(error => {
        console.error('Error fetching cluster settings:', error);
      });
  };

  initialize();

  // Setup event listeners
  document.getElementById('cluster-selector')?.addEventListener('change', () => {
    initialize();
  });

  // ------------------------------------------------------------

  /**
   * Adjust styles dynamically based on window size.
   *
   * @returns {void}
   */
  let adjustStyles = () => {
    document.querySelectorAll('.table-list, #db-selector').forEach(el => {
      el.style.height = (window.innerHeight - el.offsetTop) + 'px';
    });

    document.querySelectorAll('.tab-pane').forEach(el => {
      el.style.height = (window.innerHeight - el.offsetTop) + 'px';
    });

    let selectedDbCount = 0;
    dbSelector.querySelectorAll('option').forEach(el => {
      if (el.selected) {
        selectedDbCount++;
      }
    });
    document.getElementById('db-count').innerHTML = selectedDbCount;

    setTimeout(adjustStyles, 100);
  };

  adjustStyles();

  // ------------------------------------------------------------

})(window, window.document);

</script>
</body>
</html>
        <?php
    };
    // Handle web requests
    $webHandler = new \MultiDbSqlTool\WebHandler();
    $webHandler->execute($templateFunction);
}

main();