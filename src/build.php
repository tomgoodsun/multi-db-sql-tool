<?php
/**
 * Build script for Multi-DB SQL Tool
 *
 * This script creates a distribution version of the application:
 * - Merges all PHP classes into a single index.php
 * - Copies necessary assets (vendor files, app files, icons)
 *
 * Usage: php build.php
 */

require_once __DIR__ . '/classes/CodeBuilder.php';

// CLI only
if (php_sapi_name() !== 'cli') {
    die("This script must be run from command line.\n");
}

try {
    echo "=========================================\n";
    echo "Multi-DB SQL Tool - Build Script\n";
    echo "=========================================\n\n";

    // Build distribution
    $sourceDir = __DIR__;
    $outputFile = __DIR__ . '/../dist/html/index.php';

    $builder = new \MultiDbSqlTool\CodeBuilder($sourceDir, $outputFile);
    $builder->build();

    echo "\n=========================================\n";
    echo "✅ Build successful!\n";
    echo "=========================================\n\n";
    echo "📦 Distribution files:\n";
    echo "   dist/html/index.php\n";
    echo "   dist/html/assets/\n";
    echo "   dist/html/config.sample.php\n\n";
    echo "📋 Next steps:\n";
    echo "1. Copy dist/html/ to your web server\n";
    echo "2. Create config.php from config.sample.php\n";
    echo "3. Configure database connections\n\n";

} catch (Exception $e) {
    echo "\n❌ Build failed: " . $e->getMessage() . "\n";
    exit(1);
}
