<?php

require_once __DIR__ . '/classes/CodeBuilder.php';

// CLI execution
if (php_sapi_name() === 'cli') {
    try {
        //$builder = new \MultiDbSqlTool\CodeBuilder(__DIR__ . '/', __DIR__ . '/multi-db-sql-tool1.php');
        $builder = new \MultiDbSqlTool\CodeBuilder(__DIR__ . '/', __DIR__ . '/../dist/html/index.php');
        $builder->build();

        echo "\n📋 Next steps:\n";
        echo "1. Copy config.sample.php to config.php\n";
        echo "2. Edit config.php with your database settings\n";
        echo "3. Deploy the single file to your web server\n";
        echo "\n🚀 Ready for production deployment!\n";
    } catch (Exception $e) {
        echo "\n❌ Build failed: " . $e->getMessage() . "\n";
        exit(1);
    }
} else {
    echo "This script must be run from command line.\n";
}

