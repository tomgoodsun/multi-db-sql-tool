<?php

/**
 * 設定管理クラス
 * PHP 7.0対応
 */
class Config {
    
    /**
     * デフォルト設定を取得
     */
    public static function getDefaultConfig() {
        return [
            'clusters' => [
                'development' => [
                    'name' => 'Development Shards',
                    'shards' => [
                        'shard1' => [
                            'name' => 'Shard 1',
                            'host' => 'mysql-shard1',
                            'port' => 3306,
                            'database' => 'shard1',
                            'username' => 'dbuser',
                            'password' => 'dbpass'
                        ],
                        'shard2' => [
                            'name' => 'Shard 2', 
                            'host' => 'mysql-shard2',
                            'port' => 3306,
                            'database' => 'shard2',
                            'username' => 'dbuser',
                            'password' => 'dbpass'
                        ],
                        'shard3' => [
                            'name' => 'Shard 3',
                            'host' => 'mysql-shard3',
                            'port' => 3306,
                            'database' => 'shard3',
                            'username' => 'dbuser',
                            'password' => 'dbpass'
                        ]
                    ]
                ]
            ],
            'settings' => [
                'read_only_mode' => true,
                'max_rows' => 1000,
                'query_timeout' => 30
            ]
        ];
    }

    /**
     * 指定されたクラスター設定を取得
     */
    public static function getClusterConfig($clusterName = 'development') {
        $config = self::getDefaultConfig();
        
        if (!isset($config['clusters'][$clusterName])) {
            throw new Exception("Cluster '{$clusterName}' not found in configuration");
        }
        
        return [
            'shards' => $config['clusters'][$clusterName]['shards'],
            'settings' => $config['settings']
        ];
    }
}
