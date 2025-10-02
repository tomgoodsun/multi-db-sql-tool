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
        'assets/vendor/vendor.css',
        'assets/vendor/vendor.js',
        'assets/vendor/fonts/bootstrap-icons.woff',
        'assets/vendor/fonts/bootstrap-icons.woff2',
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
        foreach ($this->requires as $file) {
            echo "Including: {$file}\n";
            $this->builtContent .= "\n" . $this->readClassFile($file) . "\n";
        }
        $this->builtContent .= "\n" . implode("\n", $this->indexResultLines) . "\n";
        $this->builtContent = $this->readAssets();
        $this->writeOutput();
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
            if (empty($trimmedLine) || preg_match('/^<\?php/', $trimmedLine)) {
                if (++$lineCount === 1) {
                    // Skip the opening <?php tag
                    continue;
                }
                $this->indexResultLines[] = $line;
                continue;
            }

            if (preg_match('/^require_once\s.*(\/classes\/.*\.php)/', $trimmedLine, $matches)) {
                $this->requires[] = $this->sourceDir . '/' . $matches[1];
            } else {
                $this->indexResultLines[] = $line;
            }
        }
    }

    /**
     * Read a class file
     *
     * @param string $filePath
     * @return void
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
     * Read the assets and inline them
     *
     * @return string
     */
    private function readAssets()
    {
        $content = $this->builtContent;

        // Replace if ($cssDevMode) or ($jsDevMode) block with vendor scripts/styles
        $cssPath = 'assets/vendor/vendor.css';
        $cssContent = sprintf('<link rel="stylesheet" href="%s">', $cssPath);
        $content = preg_replace('/<\?php if \(\$cssDevMode\): \?>(.*?)<\?php endif; \?>/s', $cssContent, $content);

        // vendor.js includes <?xml which breaks the PHP parser, so we replace the whole block
        $jsPath = 'assets/vendor/vendor.js';
        $jsContent = sprintf('<script src="%s"></script>', $jsPath);
        $content = preg_replace('/<\?php if \(\$jsDevMode\): \?>(.*?)<\?php endif; \?>/s', $jsContent, $content);

        $cssPath = 'assets/app.css';
        $cssContent = file_get_contents($this->sourceDir . '/' . $cssPath);
        $cssContent = sprintf("<style>\n/* %s */\n%s\n</style>", $cssPath, $cssContent);
        $content = preg_replace('/<link\s.*href=["\'](assets\/app\.css)["\'].*>/', $cssContent, $content);

        $cssPath = 'assets/codemirror-fix.css';
        $cssContent = file_get_contents($this->sourceDir . '/' . $cssPath);
        $cssContent = sprintf("<style>\n/* %s */\n%s\n</style>", $cssPath, $cssContent);
        $content = preg_replace('/<link\s.*href=["\'](assets\/codemirror-fix\.css)["\'].*>/', $cssContent, $content);

        $jsPath = 'assets/app.js';
        $jsContent = file_get_contents($this->sourceDir . '/' . $jsPath);
        $jsContent = sprintf("<script>\n/* %s */\n%s\n</script>", $jsPath, $jsContent);
        $content = preg_replace('/<script\s.*src=["\'](assets\/app\.js)["\'].*><\/script>/', $jsContent, $content);

        return trim($content);
    }

    /**
     * Write the output to the specified file
     *
     * @return void
     */
    private function writeOutput()
    {
        file_put_contents($this->outputFile, $this->builtContent);
        echo "Build complete!\n";
        echo "📁 Output: {$this->outputFile}\n";
        echo "📦 Size: " . number_format(filesize($this->outputFile) / 1024, 1) . " KB\n"   ;
    }

    /**
     * Copy necessary files to the output directory
     *
     * @return void
     */
    private function copyFiles()
    {
        foreach ($this->copyFiles as $file) {
            $src = $this->sourceDir . '/' . $file;
            $dest = $this->outputDir . '/' . $file;
            $destDir = dirname($dest);
            if (!is_dir($destDir)) {
                mkdir($destDir, 0755, true);
            }
            if (file_exists($src)) {
                copy($src, $dest);
                echo "Copied file: {$file}\n";
            } else {
                echo "File not found, skipping: {$file}\n";
            }
        }
    }
}
