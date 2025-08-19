<?php

/**
 * SQL解析・実行クラス
 * PSR-12準拠
 */
class SqlExecutor 
{
    private $dbManager;
    private $isReadOnlyMode;

    public function __construct(DatabaseManager $dbManager) 
    {
        $this->dbManager = $dbManager;
        $this->isReadOnlyMode = Config::isReadOnlyMode();
    }

    /**
     * SQLクエリをパースして返す（外部から利用可能）
     */
    public function parseSqlQueries(string $sql): array 
    {
        return $this->parseQueries($sql);
    }

    /**
     * 複数シャードでSQLを実行
     */
    public function executeOnAllShards(string $sql): array 
    {
        $queries = $this->parseQueries($sql);
        $maxQueries = Config::get('limits.max_queries_per_request', 10);
        
        if (count($queries) > $maxQueries) {
            throw new Exception("Too many queries. Maximum {$maxQueries} queries per request.");
        }

        $results = [];
        
        foreach ($this->dbManager->getShardNames() as $shardName) {
            $shardDisplayName = $this->dbManager->getShardDisplayName($shardName);
            $shardResults = [];
            
            foreach ($queries as $index => $query) {
                $startTime = microtime(true);
                
                try {
                    if ($this->isReadOnlyMode && !$this->isReadOnlyQuery($query)) {
                        throw new Exception("Write operations are not allowed in read-only mode");
                    }

                    $queryResult = $this->executeQuery($shardName, $query);
                    $executionTime = round((microtime(true) - $startTime) * 1000, 2);
                    
                    // 結果の先頭にシャード名を追加
                    if (!empty($queryResult)) {
                        foreach ($queryResult as &$row) {
                            $row = ['_shard' => $shardDisplayName] + $row;
                        }
                    }
                    
                    $shardResults[] = [
                        'query_index' => $index + 1,
                        'query' => $query,
                        'success' => true,
                        'data' => $queryResult,
                        'row_count' => count($queryResult),
                        'execution_time_ms' => $executionTime,
                        'error' => null
                    ];
                } catch (Exception $e) {
                    $executionTime = round((microtime(true) - $startTime) * 1000, 2);
                    
                    $shardResults[] = [
                        'query_index' => $index + 1,
                        'query' => $query,
                        'success' => false,
                        'data' => null,
                        'row_count' => 0,
                        'execution_time_ms' => $executionTime,
                        'error' => $e->getMessage()
                    ];
                }
            }
            
            $results[$shardName] = [
                'display_name' => $shardDisplayName,
                'queries' => $shardResults
            ];
        }

        return $results;
    }

    /**
     * SQLクエリを解析・分割
     */
    private function parseQueries(string $sql): array 
    {
        $sql = trim($sql);
        if (empty($sql)) {
            throw new Exception("No SQL queries provided");
        }

        // 基本的な分割（改良版）
        $queries = [];
        $current = '';
        $inString = false;
        $stringChar = '';
        $inComment = false;
        $commentType = '';
        
        $length = strlen($sql);
        for ($i = 0; $i < $length; $i++) {
            $char = $sql[$i];
            $nextChar = $i + 1 < $length ? $sql[$i + 1] : '';
            
            // 行コメントの処理
            if (!$inString && !$inComment && $char === '-' && $nextChar === '-') {
                $inComment = true;
                $commentType = 'line';
                $i++; // 次の文字もスキップ
                continue;
            }
            
            // ブロックコメントの開始
            if (!$inString && !$inComment && $char === '/' && $nextChar === '*') {
                $inComment = true;
                $commentType = 'block';
                $i++; // 次の文字もスキップ
                continue;
            }
            
            // ブロックコメントの終了
            if ($inComment && $commentType === 'block' && $char === '*' && $nextChar === '/') {
                $inComment = false;
                $commentType = '';
                $i++; // 次の文字もスキップ
                continue;
            }
            
            // 行コメントの終了（改行）
            if ($inComment && $commentType === 'line' && ($char === "\n" || $char === "\r")) {
                $inComment = false;
                $commentType = '';
            }
            
            // コメント中は処理をスキップ
            if ($inComment) {
                continue;
            }
            
            // 文字列リテラルの処理
            if (!$inString && ($char === '"' || $char === "'" || $char === '`')) {
                $inString = true;
                $stringChar = $char;
            } elseif ($inString && $char === $stringChar) {
                // エスケープされていない場合のみ文字列終了
                if ($i === 0 || $sql[$i - 1] !== '\\') {
                    $inString = false;
                    $stringChar = '';
                }
            }
            
            // セミコロンでの分割
            if (!$inString && $char === ';') {
                $trimmed = trim($current);
                if ($trimmed !== '') {
                    $queries[] = $trimmed;
                }
                $current = '';
                continue;
            }
            
            $current .= $char;
        }
        
        // 最後のクエリ
        $trimmed = trim($current);
        if ($trimmed !== '') {
            $queries[] = $trimmed;
        }
        
        if (empty($queries)) {
            throw new Exception("No valid SQL queries found");
        }
        
        return $queries;
    }

    /**
     * 読み取り専用クエリかチェック
     */
    private function isReadOnlyQuery(string $query): bool 
    {
        $query = strtoupper(trim($query));
        $readOnlyPatterns = [
            '/^SELECT\s/',
            '/^SHOW\s/',
            '/^DESCRIBE\s/',
            '/^DESC\s/',
            '/^EXPLAIN\s/'
        ];
        
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
    private function executeQuery(string $shardName, string $query): array 
    {
        $pdo = $this->dbManager->getConnection($shardName);
        
        $maxRows = Config::get('limits.max_rows_per_query', 10000);
        
        // SELECT文の場合はLIMIT制限を追加
        if (preg_match('/^SELECT\s/i', trim($query)) && !preg_match('/\sLIMIT\s/i', $query)) {
            $query .= " LIMIT {$maxRows}";
        }
        
        $stmt = $pdo->prepare($query);
        $stmt->execute();
        
        return $stmt->fetchAll();
    }

    /**
     * SQLをフォーマット（簡易版）
     */
    public function formatSql(string $sql): string 
    {
        // 基本的なSQLフォーマット（実際の環境ではSQL Formatterライブラリを使用）
        $sql = trim($sql);
        $sql = preg_replace('/\s+/', ' ', $sql);
        
        $keywords = [
            'SELECT', 'FROM', 'WHERE', 'GROUP BY', 'HAVING', 'ORDER BY', 'LIMIT',
            'INSERT', 'INTO', 'VALUES', 'UPDATE', 'SET', 'DELETE',
            'JOIN', 'LEFT JOIN', 'RIGHT JOIN', 'INNER JOIN', 'OUTER JOIN',
            'UNION', 'UNION ALL'
        ];
        
        foreach ($keywords as $keyword) {
            $sql = preg_replace('/\b' . preg_quote($keyword) . '\b/i', "\n" . $keyword, $sql);
        }
        
        return trim($sql);
    }
}
