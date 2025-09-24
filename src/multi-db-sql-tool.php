<?php
namespace MultiDbSqlTool;

class Config
{
    const APP_NAME = 'Multi-DB SQL Tool';
    const APP_SHORT_NAME = 'mDBSQL';
    const VERSION = '1.0.0-alpha';
    const DEFAULT_SESSION_NAME = 'MDBSQL_SESSION';
    const DEFAULT_SESSION_LIFETIME = 86400; // 1 day
    const MAX_QUERY_HISTORY = 50;
    private static $instance = null;
    protected $settings = [];
    protected function __construct($configPath = null)
    {
        if (null === $configPath) {
            $configPath = __DIR__ . '/../config/config.php';
        }
        if (!file_exists($configPath)) {
            throw new \RuntimeException("Configuration file not found: {$configPath}");
        }
        $this->settings = require $configPath;
    }
    public static function getInstance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    public static function initialize($configPath = null)
    {
        if (null === self::$instance) {
            self::$instance = new self($configPath);
        }
    }
    public function get($key, $default = null)
    {
        return self::searchArrayByPath($this->settings, $key, $default);
    }
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
    public static function getClusterNames()
    {
        return array_keys(self::getInstance()->get('dbs', []));
    }
    public static function clusterExists($clusterName)
    {
        return in_array($clusterName, self::getClusterNames());
    }
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
    public static function getShardNames($clusterName)
    {
        $dbs = self::getDatabaseSettings($clusterName);
        return array_keys($dbs);
    }
    public static function isReadOnlyMode()
    {
        return (bool)self::getInstance()->get('readonly_mode', false);
    }
}
class SessionManager
{
    public function __construct()
    {
        $this->startSession();
    }
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
        foreach ($queryHistory as $item) {
            if ($item['sql'] === $sql) {
                return;
            }
        }
        array_unshift($queryHistory, $historyItem);
        $maxHistory = Config::getInstance()->get('session.max_history', Config::MAX_QUERY_HISTORY);
        $_SESSION['query_history'] = array_slice($queryHistory, 0, $maxHistory);
    }
    public function getQueryHistory()
    {
        return $_SESSION['query_history'] ?? [];
    }
    public function clearQueryHistory()
    {
        $_SESSION['query_history'] = [];
    }
    public function destroy()
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }
}
class Utility
{
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
    public static function cleanSql($sql)
    {
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
    public static function isReadOnlyQuery($sql)
    {
        $sql = self::cleanSql($sql);
        $sql = trim($sql);
        $sql = preg_replace('/^[\s\(]+/', ' ', $sql); // Remove leading whitespace and parentheses
        $sql = strtoupper($sql);
        $pattern = '/^\s*(SELECT|SHOW|DESCRIBE|DESC|EXPLAIN)\b/i';
        return 1 === preg_match($pattern, $sql);
    }
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
    protected $sql = '';
    protected $isReadOnlyQuery = true;
    protected $params = [];
    protected $connections = [];
    protected $rowCounts = [];
    protected $resultSet = [];
    protected $errors = [];
    protected $connectionErrors = [];
    public function __construct($sql, $params = [])
    {
        $this->sql = trim($sql);
        $this->params = $params;
        $this->isReadOnlyQuery = Utility::isReadOnlyQuery($this->sql);
    }
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
    public function bulkAddConnections($connections)
    {
        foreach ($connections as $name => $conn) {
            $this->addConnection($name, self::createDsn($conn), $conn['username'], $conn['password']);
        }
        return $this;
    }
    public function addConnection($name, $dsn, $username, $password)
    {
        try {
            $this->connections[$name] = $this->createConnection($dsn, $username, $password);
        } catch (\Throwable $e) {
            $this->connectionErrors[$name] = $e->getMessage();
        }
        return $this;
    }
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
    protected function isPostMethod()
    {
        return 'POST' === strtoupper($this->method);
    }
    protected function validateCluster()
    {
        if (0 === strlen($this->clusterName)) {
            return;
        }
        if (!in_array($this->clusterName, Config::getInstance()->getClusterNames(), true)) {
            throw new \Exception('Invalid cluster name');
        }
    }
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
    protected function requireAuth()
    {
        header('WWW-Authenticate: Basic realm="Multi-DB SQL Tool"');
        http_response_code(401);
        exit('Authentication Required');
    }
    protected function applyExecutionLimits()
    {
        $limits = Config::getInstance()->get('limits', []);
        $maxExecutionTime = $limits['max_execution_time'] ?? 30;
        set_time_limit($maxExecutionTime);
        $memoryLimit = $limits['memory_limit'] ?? '256M';
        ini_set('memory_limit', $memoryLimit);
    }
    protected function validateQueryLimits(array $sqls)
    {
        $limits = Config::getInstance()->get('limits', []);
        $maxQueries = $limits['max_queries_per_request'] ?? 10;
        if (count($sqls) > $maxQueries) {
            throw new \InvalidArgumentException("Too many queries. Maximum {$maxQueries} allowed.");
        }
        return true;
    }
    protected function json(array $data)
    {
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }
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
        if (is_callable($templateFunction)) {
            $templateFunction(compact(
                'appName',
                'appShortName',
                'appShortNameLower',
                'version',
                'optionalName',
                'clausterList',
                'readOnlyMode'
            ));
            return;
        }
    }
    public function execute($templateFunction = null)
    {
        try {
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

  <!-- CSS Libraries -->
  <link href="//cdn.jsdelivr.net/npm/normalize.css@8.0.1/normalize.min.css" rel="stylesheet">
  <link href="//cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
  <link href="//cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="//cdn.jsdelivr.net/npm/codemirror@5.65.16/lib/codemirror.min.css" rel="stylesheet">
  <link href="//cdn.jsdelivr.net/npm/codemirror@5.65.16/theme/eclipse.min.css" rel="stylesheet">

  <!-- AG Grid -->
  <link href="//cdn.jsdelivr.net/npm/ag-grid-community@31.0.0/styles/ag-grid.min.css" rel="stylesheet">
  <link href="//cdn.jsdelivr.net/npm/ag-grid-community@31.0.0/styles/ag-theme-alpine.min.css" rel="stylesheet">

  <!-- Custom CSS -->
  <link href="assets/app.css" rel="stylesheet">
  <link href="assets/codemirror-fix.css" rel="stylesheet">
</head>
<body>

  <div class="container-fluid app-container">
    <!-- header -->
    <div class="header">
      <h1>
        <img src="favicon.svg" alt="logo" class="app-logo">
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
          <h1 class="modal-title fs-5" id="execution-confirm-label">??Confirm!</h1>
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
          <h1 class="modal-title fs-5" id="sql-history-label">??SQL History</h1>
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


  <!-- JavaScript Libraries -->
  <script src="//cdn.jsdelivr.net/npm/jquery@3.5.1/dist/jquery.min.js"></script>
  <script src="//cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI" crossorigin="anonymous"></script>
  <script src="//cdn.jsdelivr.net/npm/codemirror@5.65.16/lib/codemirror.min.js"></script>
  <script src="//cdn.jsdelivr.net/npm/codemirror@5.65.16/mode/sql/sql.min.js"></script>
  <script src="//cdn.jsdelivr.net/npm/ag-grid-community@31.0.0/dist/ag-grid-community.min.js"></script>
  <script src="//cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
  <script src="//cdn.jsdelivr.net/npm/sql-formatter@15.6.8/dist/sql-formatter.min.js"></script>

  <script>
    window.MultiDbSql = {
      appShortName: '<?php echo $appShortName; ?>',
      appShortNameLower: '<?php echo $appShortNameLower; ?>',
      version: '<?php echo $version; ?>',
      isReadOnlyMode: <?php echo $readOnlyMode ? 'true' : 'false'; ?>,
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

