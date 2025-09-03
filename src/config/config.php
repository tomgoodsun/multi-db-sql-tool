<?php
return [
    // Set optional name
    // If this options has text, append "for <Optional Name>" after 'Multi-DB SQL Tool'
    'optional_name' => 'Development Environment',

    // DB targets
    'dbs' => [
        'development_cluster' => [
            'shard1' => [
                'name' => 'Development Shard 1',
                'host' => 'mysql-shard1',
                'port' => '3306',
                'username' => 'dbuser',
                'password' => 'dbpass',
                'dbname' => 'shard1'
            ],
            'shard2' => [
                'name' => 'Development Shard 2',
                'host' => 'mysql-shard2',
                'port' => '3306',
                'username' => 'dbuser',
                'password' => 'dbpass',
                'dbname' => 'shard2'
            ],
            'shard3' => [
                'name' => 'Development Shard 3',
                'host' => 'mysql-shard3',
                'port' => '3306',
                'username' => 'dbuser',
                'password' => 'dbpass',
                'dbname' => 'shard3'
            ],
        ],
    ],

    // Read-only mode
    // true: select, show, describe, desc, explain only
    // false: all queries are available
    'readonly_mode' => true,

    // To enable basic authentication, set ID/password at this option.
    'basic_auth' => [
        // ['user', 'password'],
    ],

    // Session configuration
    'session' => [
        'name' => 'MDBSQL_SESSION',
        'lifetime' => 86400, // 24 hours
        'max_history' => 50,
    ],

    // Query execution limits
    'limits' => [
        'max_execution_time' => 30,
        'max_rows_per_query' => 10000,
        'max_queries_per_request' => 10,
    ],

    // UI settings
    'ui' => [
        'theme' => 'light', // 'light' or 'dark'
        'editor_theme' => 'default', // CodeMirror theme
        'items_per_page' => 100,
    ],
];
