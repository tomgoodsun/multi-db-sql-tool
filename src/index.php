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

// 言語初期化
Language::initFromSession();
Language::handleLanguageChange();

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
    <title><?= htmlspecialchars(Language::get('app_name')) ?></title>
    <link rel="icon" type="image/svg+xml" href="favicon.svg">
    <link rel="alternate icon" href="favicon.ico">
    
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
    
    <!-- JavaScript translations -->
    <script>
        window.translations = <?= json_encode([
            'enter_sql_query' => Language::get('enter_sql_query'),
            'query_executed_successfully' => Language::get('query_executed_successfully'),
            'query_execution_failed' => Language::get('query_execution_failed'),
            'format_successful' => Language::get('format_successful'),
            'format_failed' => Language::get('format_failed'),
            'history_load_failed' => Language::get('history_load_failed'),
            'cluster_switched' => Language::get('cluster_switched'),
            'cluster_switch_failed' => Language::get('cluster_switch_failed'),
            'language_changed' => Language::get('language_changed'),
            'initialization_failed' => Language::get('initialization_failed'),
            'no_results_to_export' => Language::get('no_results_to_export'),
            'results_exported_successfully' => Language::get('results_exported_successfully'),
            'csv_export_successful' => Language::get('csv_export_successful'),
            'csv_export_failed' => Language::get('csv_export_failed'),
            'export_failed' => Language::get('export_failed'),
            'sql_execution_confirmation' => Language::get('sql_execution_confirmation'),
            'dangerous_sql_confirmation' => Language::get('dangerous_sql_confirmation'),
            'sql_will_execute_on_all_dbs' => Language::get('sql_will_execute_on_all_dbs'),
            'sql_may_modify_data' => Language::get('sql_may_modify_data'),
            'sql_type' => Language::get('sql_type'),
            'target_databases' => Language::get('target_databases'),
            'current_cluster_all_shards' => Language::get('current_cluster_all_shards'),
            'important_warning' => Language::get('important_warning'),
            'operation_irreversible' => Language::get('operation_irreversible'),
            'production_caution' => Language::get('production_caution'),
            'backup_recommended' => Language::get('backup_recommended'),
            'execute' => Language::get('execute'),
            'cancel' => Language::get('cancel'),
            'query_history' => Language::get('query_history'),
            'no_history_available' => Language::get('no_history_available'),
            'no_data_returned' => Language::get('no_data_returned'),
            'close' => Language::get('close')
        ], JSON_UNESCAPED_UNICODE) ?>;
    </script>
</head>
<body>
    <!-- Alert Container -->
    <div id="alert-container" style="position: fixed; top: 70px; right: 20px; z-index: 1060; width: 400px;"></div>

    <div class="app-container">
        <!-- Header -->
        <div class="header">
            <h1><?= htmlspecialchars(Language::get('app_name')) ?></h1>
            <div class="header-controls">
                <!-- Language Selector -->
                <select id="language-selector" class="form-select form-select-sm me-2" style="width: auto;">
                    <?php foreach (Language::getAvailableLanguages() as $code => $name): ?>
                        <option value="<?= htmlspecialchars($code) ?>" <?= Language::getCurrentLanguage() === $code ? 'selected' : '' ?>>
                            <?= htmlspecialchars($name) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                
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
                    <span class="badge bg-warning"><?= Language::get('read_only') ?></span>
                <?php else: ?>
                    <span class="badge bg-danger"><?= Language::get('write_enabled') ?></span>
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
                        <h5 class="mb-0"><?= Language::get('db_table_status') ?></h5>
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
                <h5><?= Language::get('tables') ?></h5>
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
                                <div class="table-logical-name"><?= Language::get('logical_name') ?> <?= htmlspecialchars($table) ?></div>
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
                        <i class="bi bi-clock-history"></i> <?= Language::get('history') ?>
                    </button>
                </div>
                <div class="toolbar-center">
                    <button id="btn-format" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-code"></i> <?= Language::get('beautify_sql') ?>
                    </button>
                    <button id="btn-execute" class="btn btn-primary btn-sm">
                        <i class="bi bi-play-fill"></i> <?= Language::get('run') ?>
                    </button>
                </div>
                <div class="toolbar-right">
                    <span class="me-2"><?= Language::get('export') ?></span>
                    <button id="btn-export-csv" class="btn btn-outline-success btn-sm me-1"><?= Language::get('csv') ?></button>
                    <button id="btn-export" class="btn btn-outline-success btn-sm"><?= Language::get('xlsx') ?></button>
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
                    <p class="mt-3"><?= Language::get('execute_sql_message') ?></p>
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
