<?php

namespace MultiDbSqlTool;

class CodeBuilder
{
    private $sourceDir;
    private $outputDir;
    private $outputFile;
    private $copyFiles = [
        'favicon.ico',
        'favicon.svg',
        'config.sample.php',
        // Vendor files
        'assets/vendor/vendor.css',
        'assets/vendor/vendor.js',
        'assets/vendor/fonts/bootstrap-icons.woff',
        'assets/vendor/fonts/bootstrap-icons.woff2',
        // Application files (keep as external files)
        'assets/app.css',
        'assets/app.js',
        'assets/codemirror-fix.css',
    ];
    private $requires = [];
    private $indexResultLines = [];
    private $builtContent = "<?php\n\nnamespace MultiDbSqlTool;\n\n";

    /**
     * Constructor
     *
     * @param string $sourceDir
     * @param string $outputFile
     */
    public function __construct($sourceDir, $outputFile)
    {
        $this->sourceDir = rtrim($sourceDir, '/');
        $this->outputFile = $outputFile;
        $this->outputDir = dirname($outputFile);
    }

    /**
     * Build the project
     *
     * @return void
     */
    public function build()
    {
        echo "Starting build process...\n";
        $this->parseIndex();
        
        // Merge PHP classes
        foreach ($this->requires as $file) {
            echo "Including: {$file}\n";
            $this->builtContent .= "\n" . $this->readClassFile($file) . "\n";
        }
        
        // Add index content
        $this->builtContent .= "\n" . implode("\n", $this->indexResultLines) . "\n";
        
        // Replace dev mode blocks with production paths
        $this->builtContent = $this->replaceDevMode();
        
        // Write output
        $this->writeOutput();
        
        // Copy asset files
        $this->copyFiles();
    }

    /**
     * Parse the index file
     *
     * @return void
     */
    private function parseIndex()
    {
        $indexFile = $this->sourceDir . '/index.php';
        if (!file_exists($indexFile)) {
            throw new \Exception("Index file not found: {$indexFile}");
        }

        $content = file_get_contents($indexFile);
        $lines = explode("\n", $content);
        $lineCount = 0;

        foreach ($lines as $line) {
            $trimmedLine = trim($line);
            
            // Skip opening <?php tag
            if (empty($trimmedLine) || preg_match('/^<\?php/', $trimmedLine)) {
                if (++$lineCount === 1) {
                    continue;
                }
                $this->indexResultLines[] = $line;
                continue;
            }

            // Extract require_once for classes
            if (preg_match('/^require_once\s+__DIR__\s*\.\s*[\'"](.+\.php)[\'"]/', $trimmedLine, $matches)) {
                $this->requires[] = $this->sourceDir . $matches[1];
            } else {
                $this->indexResultLines[] = $line;
            }
        }
    }

    /**
     * Read a class file
     *
     * @param string $filePath
     * @return string
     */
    private function readClassFile($filePath)
    {
        if (!file_exists($filePath)) {
            throw new \Exception("Class file not found: {$filePath}");
        }

        $content = file_get_contents($filePath);
        // Remove opening tag and namespace
        $content = preg_replace('/^<\?php\s*\n?/', '', $content);
        $content = preg_replace('/^namespace\s+[^;]+;\s*/m', '', $content);

        return trim($content);
    }

    /**
     * Replace dev mode blocks with production links
     *
     * @return string
     */
    private function replaceDevMode()
    {
        $content = $this->builtContent;

        // Replace CSS dev mode block with vendor CSS link
        $cssReplacement = '<link rel="stylesheet" href="assets/vendor/vendor.css">';
        $content = preg_replace('/<\?php if \(\$cssDevMode\): \?>(.*?)<\?php endif; \?>/s', $cssReplacement, $content);

        // Replace JS dev mode block with vendor JS link
        $jsReplacement = '<script src="assets/vendor/vendor.js"></script>';
        $content = preg_replace('/<\?php if \(\$jsDevMode\): \?>(.*?)<\?php endif; \?>/s', $jsReplacement, $content);

        return $content;
    }

    /**
     * Write the output to the specified file
     *
     * @return void
     */
    private function writeOutput()
    {
        file_put_contents($this->outputFile, $this->builtContent);
        echo "\nBuild complete!\n";
        echo "📁 Output: {$this->outputFile}\n";
        echo "📦 Size: " . number_format(filesize($this->outputFile) / 1024, 1) . " KB\n";
    }

    /**
     * Copy necessary files to the output directory
     *
     * @return void
     */
    private function copyFiles()
    {
        echo "\nCopying assets...\n";
        foreach ($this->copyFiles as $file) {
            $src = $this->sourceDir . '/' . $file;
            $dest = $this->outputDir . '/' . $file;
            $destDir = dirname($dest);
            
            if (!is_dir($destDir)) {
                mkdir($destDir, 0755, true);
            }
            
            if (file_exists($src)) {
                copy($src, $dest);
                echo "  ✓ {$file}\n";
            } else {
                echo "  ✗ Not found: {$file}\n";
            }
        }
    }
}
