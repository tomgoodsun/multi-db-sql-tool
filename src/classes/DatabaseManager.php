<?php

/**
 * データベース接続管理クラス
 * PHP 7.0対応
 */
class DatabaseManager {
    private $connections = [];
    private $config;

    public function __construct($config) {
        $this->config = $config;
    }

    /**
     * 指定されたシャードへの接続を取得
     */
    public function getConnection($shardName) {
        if (!isset($this->connections[$shardName])) {
            $this->connections[$shardName] = $this->createConnection($shardName);
        }
        return $this->connections[$shardName];
    }

    /**
     * PDO接続を作成
     */
    private function createConnection($shardName) {
        if (!isset($this->config['shards'][$shardName])) {
            throw new Exception("Shard '{$shardName}' not found in configuration");
        }

        $shardConfig = $this->config['shards'][$shardName];
        $dsn = "mysql:host={$shardConfig['host']};port={$shardConfig['port']};dbname={$shardConfig['database']};charset=utf8mb4";
        
        try {
            $pdo = new PDO($dsn, $shardConfig['username'], $shardConfig['password']);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            return $pdo;
        } catch (PDOException $e) {
            throw new Exception("Connection failed to shard '{$shardName}': " . $e->getMessage());
        }
    }

    /**
     * 全シャード名を取得
     */
    public function getShardNames() {
        return array_keys($this->config['shards']);
    }

    /**
     * 接続テスト
     */
    public function testConnection($shardName) {
        try {
            $pdo = $this->getConnection($shardName);
            $stmt = $pdo->query('SELECT 1');
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * 全接続を閉じる
     */
    public function closeAll() {
        $this->connections = [];
    }
}
