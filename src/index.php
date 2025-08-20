<?php
// Basic認証チェック
require_once __DIR__ . '/classes/AuthManager.php';
require_once __DIR__ . '/classes/Config.php';

if (!AuthManager::checkBasicAuth()) {
    exit;
}

// オートローダー
spl_autoload_register(function ($className) {
    $file = __DIR__ . '/classes/' . $className . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});

// エラーハンドリング
error_reporting(E_ALL);
ini_set('display_errors', 0); // 本番では非表示

try {
    $sessionManager = new SessionManager();
    $currentCluster = $sessionManager->getCurrentCluster();
    $dbManager = new DatabaseManager($currentCluster);
    
    // 初期ステータス読み込み
    $tablesInfo = $dbManager->getAllTablesInfo();
    $appName = Config::getAppName();
    $clusters = array_keys(Config::get('dbs', []));
    
} catch (Exception $e) {
    $initError = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($appName ?? 'Multi-DB SQL Tool') ?></title>
    
    <!-- CSS Libraries -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/normalize/8.0.1/normalize.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.10.0/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/codemirror.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/theme/eclipse.min.css" rel="stylesheet">
    
    <!-- AG Grid -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/ag-grid/31.0.0/ag-grid.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/ag-grid/31.0.0/ag-theme-alpine.min.css" rel="stylesheet">
    
    <!-- Custom CSS -->
    <link href="assets/css/app.css" rel="stylesheet">
</head>
<body>
    <!-- Alert Container -->
    <div id="alert-container" style="position: fixed; top: 70px; right: 20px; z-index: 1060; width: 400px;"></div>

    <div class="app-container">
        <!-- Header -->
        <div class="header">
            <h1><?= htmlspecialchars($appName ?? 'Multi-DB SQL Tool') ?></h1>
            <div class="header-controls">
                <?php if (!empty($clusters) && count($clusters) > 1): ?>
                    <select id="cluster-selector" class="form-select form-select-sm">
                        <?php foreach ($clusters as $cluster): ?>
                            <option value="<?= htmlspecialchars($cluster) ?>" <?= $cluster === $currentCluster ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cluster) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                <?php endif; ?>
                
                <?php if (Config::isReadOnlyMode()): ?>
                    <span class="badge bg-warning">READ ONLY</span>
                <?php else: ?>
                    <span class="badge bg-danger">WRITE ENABLED</span>
                <?php endif; ?>
            </div>
        </div>

        <!-- Sidebar -->
        <div class="sidebar">
            <!-- Connection Status -->
            <div class="sidebar-section">
                <div class="d-flex align-items-center mb-2">
                    <input type="checkbox" id="db-table-status" class="form-check-input me-2" checked>
                    <label for="db-table-status" class="form-check-label">
                        <h5 class="mb-0">DB & table status</h5>
                    </label>
                </div>
                <div id="connection-status" class="connection-status">
                    <?php if (isset($tablesInfo)): ?>
                        <?php foreach ($tablesInfo as $shardName => $shardInfo): ?>
                            <div class="status-item">
                                <div class="status-indicator <?= $shardInfo['connection']['status'] === 'connected' ? 'connected' : 'failed' ?>"></div>
                                <span><?= htmlspecialchars($shardInfo['display_name']) ?></span>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Tables -->
            <div class="sidebar-section">
                <h5>Tables</h5>
                <div id="tables-list" class="table-list">
                    <?php if (isset($tablesInfo)): ?>
                        <?php
                        $allTables = [];
                        foreach ($tablesInfo as $shardInfo) {
                            $allTables = array_merge($allTables, $shardInfo['tables']);
                        }
                        $allTables = array_unique($allTables);
                        sort($allTables);
                        ?>
                        <?php foreach ($allTables as $table): ?>
                            <div class="table-item">
                                <div class="table-physical-name"><?= htmlspecialchars($table) ?></div>
                                <div class="table-logical-name">Table <?= htmlspecialchars($table) ?></div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Editor Area -->
        <div class="editor-area">
            <div class="editor-toolbar">
                <div class="toolbar-left">
                    <button id="btn-history" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-clock-history"></i> History
                    </button>
                </div>
                <div class="toolbar-center">
                    <button id="btn-format" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-code"></i> Beautify SQL
                    </button>
                    <button id="btn-execute" class="btn btn-primary btn-sm">
                        <i class="bi bi-play-fill"></i> Run (Ctrl+Enter)
                    </button>
                </div>
                <div class="toolbar-right">
                    <span class="me-2">Export:</span>
                    <button id="btn-export-csv" class="btn btn-outline-success btn-sm me-1">CSV</button>
                    <button id="btn-export" class="btn btn-outline-success btn-sm">XLSX</button>
                </div>
            </div>
            <div class="sql-editor-container">
                <div id="sql-editor"></div>
            </div>
        </div>

        <!-- Results Area -->
        <div class="results-area">
            <div class="results-tabs-container">
                <button class="tab-nav-btn tab-nav-left" id="tab-nav-left">
                    <i class="bi bi-chevron-left"></i>
                </button>
                <div id="results-tabs" class="results-tabs">
                    <!-- Tabs will be generated by JavaScript -->
                </div>
                <button class="tab-nav-btn tab-nav-right" id="tab-nav-right">
                    <i class="bi bi-chevron-right"></i>
                </button>
            </div>
            <div id="results-content" class="results-content">
                <div class="p-4 text-center text-muted">
                    <i class="bi bi-database" style="font-size: 3rem; opacity: 0.3;"></i>
                    <p class="mt-3">Execute a SQL query to see results here</p>
                </div>
            </div>
        </div>
    </div>

    <!-- JavaScript Libraries -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/codemirror.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/sql/sql.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/ag-grid/31.0.0/ag-grid-community.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    
    <!-- Custom JavaScript -->
    <script src="assets/js/app.js"></script>

    <?php if (isset($initError)): ?>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            document.getElementById('alert-container').innerHTML = `
                <div class="alert alert-danger alert-dismissible fade show">
                    <strong>Initialization Error:</strong> <?= htmlspecialchars($initError) ?>
                    <button type="button" class="btn-close" onclick="this.parentElement.remove()"></button>
                </div>
            `;
        });
    </script>
    <?php endif; ?>
</body>
</html>
