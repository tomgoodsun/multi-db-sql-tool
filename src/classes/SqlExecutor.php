<?php

/**
 * SQL解析・実行クラス
 * PHP 7.0対応
 */
class SqlExecutor {
    private $dbManager;
    private $restrictToReadOnly;

    public function __construct(DatabaseManager $dbManager, $restrictToReadOnly = true) {
        $this->dbManager = $dbManager;
        $this->restrictToReadOnly = $restrictToReadOnly;
    }

    /**
     * 複数シャードでSQLを実行
     */
    public function executeOnAllShards($sql) {
        $queries = $this->parseQueries($sql);
        $results = [];

        foreach ($this->dbManager->getShardNames() as $shardName) {
            $shardResults = [];
            foreach ($queries as $index => $query) {
                try {
                    if ($this->restrictToReadOnly && !$this->isReadOnlyQuery($query)) {
                        throw new Exception("Write operations are not allowed");
                    }

                    $result = $this->executeQuery($shardName, $query);
                    $shardResults[] = [
                        'query_index' => $index,
                        'query' => $query,
                        'success' => true,
                        'data' => $result,
                        'error' => null
                    ];
                } catch (Exception $e) {
                    $shardResults[] = [
                        'query_index' => $index,
                        'query' => $query,
                        'success' => false,
                        'data' => null,
                        'error' => $e->getMessage()
                    ];
                }
            }
            $results[$shardName] = $shardResults;
        }

        return $results;
    }

    /**
     * SQLクエリを解析・分割
     */
    private function parseQueries($sql) {
        // 改行を削除してセミコロンで分割（簡易版）
        $sql = preg_replace('/\s+/', ' ', trim($sql));
        $queries = explode(';', $sql);
        
        $result = [];
        foreach ($queries as $query) {
            $trimmed = trim($query);
            if (!empty($trimmed)) {
                $result[] = $trimmed;
            }
        }
        
        return $result;
    }

    /**
     * 読み取り専用クエリかチェック
     */
    private function isReadOnlyQuery($query) {
        $query = strtoupper(trim($query));
        $readOnlyPatterns = ['/^SELECT\s/', '/^SHOW\s/', '/^DESCRIBE\s/', '/^DESC\s/', '/^EXPLAIN\s/'];
        
        foreach ($readOnlyPatterns as $pattern) {
            if (preg_match($pattern, $query)) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * 単一クエリを実行
     */
    private function executeQuery($shardName, $query) {
        $pdo = $this->dbManager->getConnection($shardName);
        $stmt = $pdo->prepare($query);
        $stmt->execute();
        
        return $stmt->fetchAll();
    }
}
