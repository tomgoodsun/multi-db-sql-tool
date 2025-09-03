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
            $stmt = $connection->prepare($this->sql);
            $stmt->execute($this->params);
            $result = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            foreach ($result as &$row) {
                $row['_shard'] = $name;
                $results[] = $row['_shard'];
            }
            $this->resultSet[$name] = $result;
            $this->rowCounts[$name] = count($result);
        }

        return [
            'rows' => array_sum($this->rowCounts),
            'results' => $results,
        ];
    }
}
