<?php
namespace MultiDbSqlTool;

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
        $this->connections[$name] = $this->createConnection($dsn, $username, $password);
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
            'errors' => array_values($this->errors),
        ];
    }
}
