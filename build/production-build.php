<?php
/**
 * Multi-DB SQL Tool - Production Builder
 */

class ProductionBuilder
{
    private $sourceDir;
    private $outputFile;

    public function __construct($sourceDir = 'src', $outputFile = 'dist/multi-db-sql-tool.php')
    {
        $this->sourceDir = rtrim($sourceDir, '/');
        $this->outputFile = $outputFile;
    }

    public function build()
    {
        echo "Building production single file...\n";

        // Simply copy the existing working file and optimize it
        $sourceFile = $this->sourceDir . '/multi-db-sql-tool.php';

        if (!file_exists($sourceFile)) {
            throw new Exception("Source file not found: {$sourceFile}. Please create it first.");
        }

        $content = file_get_contents($sourceFile);
        $optimized = $this->optimizeContent($content);

        // Write file
        $this->writeOutputFile($optimized);

        echo "\n✅ Build completed successfully!\n";
        echo "📁 Output: {$this->outputFile}\n";
        echo "📦 Size: " . number_format(filesize($this->outputFile) / 1024, 1) . " KB\n";
    }

    private function optimizeContent($content)
    {
        echo "  ✓ Optimizing content...\n";

        // Remove single-line comments (but keep important ones)
        $content = preg_replace('/^\s*\/\/(?!.*TODO|.*FIXME|.*NOTE).*$/m', '', $content);

        // Remove multi-line comments (but keep important ones)
        $content = preg_replace('/\/\*(?!.*\*\/.*TODO|.*\*\/.*FIXME).*?\*\//s', '', $content);

        // Remove excessive blank lines (3+ blank lines become 1)
        $content = preg_replace('/\n\s*\n\s*\n+/', "\n\n", $content);

        // Trim each line (remove trailing spaces)
        $lines = explode("\n", $content);
        $lines = array_map('rtrim', $lines);
        $content = implode("\n", $lines);

        // Remove blank lines at the beginning and end
        $content = trim($content);

        return $content;
    }

    private function writeOutputFile($content)
    {
        // Ensure output directory exists
        $outputDir = dirname($this->outputFile);
        if (!file_exists($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        file_put_contents($this->outputFile, $content);

        // Create sample config
        $this->createSampleConfig();
    }

    private function createSampleConfig()
    {
        $configFile = dirname($this->outputFile) . '/config.sample.php';

        // Copy existing config.sample.php if available
        $sourceConfigSample = $this->sourceDir . '/config.sample.php';
        if (file_exists($sourceConfigSample)) {
            copy($sourceConfigSample, $configFile);
            echo "  ✓ Copied: config.sample.php\n";
        } else {
            // Create default config if source doesn't exist
            $config = "<?php\n";
            $config .= "return [\n";
            $config .= "    'optional_name' => 'Development Environment',\n";
            $config .= "    'dbs' => [\n";
            $config .= "        'default_cluster' => [\n";
            $config .= "            'shard1' => [\n";
            $config .= "                'name' => 'Database 1',\n";
            $config .= "                'host' => 'localhost',\n";
            $config .= "                'port' => '3306',\n";
            $config .= "                'username' => 'username',\n";
            $config .= "                'password' => 'password',\n";
            $config .= "                'dbname' => 'database1'\n";
            $config .= "            ],\n";
            $config .= "        ],\n";
            $config .= "    ],\n";
            $config .= "    'readonly_mode' => true,\n";
            $config .= "    'session' => [\n";
            $config .= "        'name' => 'MDBSQL_SESSION',\n";
            $config .= "        'lifetime' => 86400,\n";
            $config .= "        'max_history' => 50,\n";
            $config .= "    ],\n";
            $config .= "];\n";

            file_put_contents($configFile, $config);
            echo "  ✓ Created: config.sample.php\n";
        }
    }

    public function buildFromScratch()
    {
        echo "Building from individual source files...\n";

        $classes = $this->readClasses();
        $indexContent = $this->readIndex();

        $output = $this->combineFiles($classes, $indexContent);
        $this->writeOutputFile($output);

        echo "\n✅ Build from scratch completed!\n";
        echo "📁 Output: {$this->outputFile}\n";
        echo "📦 Size: " . number_format(filesize($this->outputFile) / 1024, 1) . " KB\n";
    }

    private function readClasses()
    {
        $classes = [];
        $files = ['Config.php', 'SessionManager.php', 'Utility.php', 'Query.php', 'WebHandler.php'];

        foreach ($files as $file) {
            $path = $this->sourceDir . '/classes/' . $file;
            if (file_exists($path)) {
                $content = file_get_contents($path);

                // Remove opening tag and namespace
                $content = preg_replace('/^<\?php\s*\n?/', '', $content);
                $content = preg_replace('/^namespace\s+[^;]+;\s*/m', '', $content);

                $classes[] = trim($content);
                echo "  ✓ Read: {$file}\n";
            }
        }

        return $classes;
    }

    private function readIndex()
    {
        $indexFile = $this->sourceDir . '/index.php';
        if (file_exists($indexFile)) {
            $content = file_get_contents($indexFile);

            // Remove opening tag and requires
            $content = preg_replace('/^<\?php\s*\n?/', '', $content);
            $content = preg_replace('/require_once[^;]+;\s*\n?/m', '', $content);

            echo "  ✓ Read: index.php\n";
            return trim($content);
        }

        return '';
    }

    private function combineFiles($classes, $indexContent)
    {
        $output = "<?php\n";
        $output .= "namespace MultiDbSqlTool;\n";

        // Add classes
        foreach ($classes as $class) {
            $output .= $class . "\n";
        }

        // Add index content
        $output .= $indexContent . "\n";

        return $output;
    }
}

// CLI execution
if (php_sapi_name() === 'cli') {
    try {
        $builder = new ProductionBuilder(__DIR__ . '/../src', __DIR__ . '/../dist/multi-db-sql-tool.php');
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
