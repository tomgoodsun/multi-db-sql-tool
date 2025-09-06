<?php
namespace MultiDbSqlTool;

class Query
{
    /**
     * @var string
     */
    protected $sql = '';

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
        $this->sql = $sql;
        $this->params = $params;
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
        $this->connections[$name] = new \PDO($dsn, $username, $password);
        return $this;
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
                foreach ($result as &$row) {
                    $row['__shard'] = $name;
                    $results[] = $row;
                }
                $this->resultSet[$name] = $result;
                $this->rowCounts[$name] = count($result);
            } catch (\Throwable $e) {
                $this->errors[] = [
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
}
