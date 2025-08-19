<?php

/**
 * データベース接続管理クラス
 * PSR-12準拠
 */
class DatabaseManager 
{
    private $connections = [];
    private $clusterName;
    private $dbConfigs;

    public function __construct(string $clusterName) 
    {
        $this->clusterName = $clusterName;
        $this->dbConfigs = Config::getClusterDbs($clusterName);
        
        if (empty($this->dbConfigs)) {
            throw new Exception("No database configuration found for cluster: {$clusterName}");
        }
    }

    /**
     * 指定されたシャードへの接続を取得
     */
    public function getConnection(string $shardName): PDO 
    {
        if (!isset($this->connections[$shardName])) {
            $this->connections[$shardName] = $this->createConnection($shardName);
        }
        return $this->connections[$shardName];
    }

    /**
     * PDO接続を作成
     */
    private function createConnection(string $shardName): PDO 
    {
        if (!isset($this->dbConfigs[$shardName])) {
            throw new Exception("Shard '{$shardName}' not found in cluster '{$this->clusterName}'");
        }

        $config = $this->dbConfigs[$shardName];
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            $config['host'],
            $config['port'],
            $config['dbname']
        );

        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_TIMEOUT => Config::get('limits.max_execution_time', 30),
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
        ];

        try {
            return new PDO($dsn, $config['username'], $config['password'], $options);
        } catch (PDOException $e) {
            throw new Exception("Connection failed to {$shardName}: " . $e->getMessage());
        }
    }

    /**
     * 全シャード名を取得
     */
    public function getShardNames(): array 
    {
        return array_keys($this->dbConfigs);
    }

    /**
     * シャードの表示名を取得
     */
    public function getShardDisplayName(string $shardName): string 
    {
        return $this->dbConfigs[$shardName]['name'] ?? $shardName;
    }

    /**
     * 接続テスト
     */
    public function testConnection(string $shardName): array 
    {
        try {
            $pdo = $this->getConnection($shardName);
            $stmt = $pdo->query('SELECT 1 as test, NOW() as timestamp');
            $result = $stmt->fetch();
            
            return [
                'status' => 'connected',
                'message' => 'Connection successful',
                'server_time' => $result['timestamp'],
                'error' => null
            ];
        } catch (Exception $e) {
            return [
                'status' => 'failed',
                'message' => 'Connection failed',
                'server_time' => null,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * テーブル一覧を取得
     */
    public function getTables(string $shardName): array 
    {
        try {
            $pdo = $this->getConnection($shardName);
            $stmt = $pdo->query('SHOW TABLES');
            $tables = [];
            
            while ($row = $stmt->fetch()) {
                $tables[] = array_values($row)[0];
            }
            
            return $tables;
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * 全シャードのテーブル情報を取得
     */
    public function getAllTablesInfo(): array 
    {
        $result = [];
        foreach ($this->getShardNames() as $shardName) {
            $result[$shardName] = [
                'display_name' => $this->getShardDisplayName($shardName),
                'tables' => $this->getTables($shardName),
                'connection' => $this->testConnection($shardName)
            ];
        }
        return $result;
    }

    /**
     * 全接続を閉じる
     */
    public function closeAll(): void 
    {
        $this->connections = [];
    }
}
