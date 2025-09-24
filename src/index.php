<?php

require_once __DIR__ . '/classes/Config.php';
require_once __DIR__ . '/classes/SessionManager.php';
require_once __DIR__ . '/classes/Utility.php';
require_once __DIR__ . '/classes/Query.php';
require_once __DIR__ . '/classes/WebHandler.php';

function main()
{
    // Initialize configuration
    \MultiDbSqlTool\Config::initialize(__DIR__ . '/config.php');

    $templateFunction = function ($vars) {
        extract($vars);
        ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?php echo $appName . ' ' . $version; ?><?php echo ' ' . $optionalName; ?></title>
  <link rel="icon" type="image/svg+xml" href="favicon.svg">
  <link rel="alternate icon" href="favicon.ico">

  <!-- CSS Libraries -->
  <!--
  <link href="//cdn.jsdelivr.net/npm/normalize.css@8.0.1/normalize.min.css" rel="stylesheet">
  <link href="//cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
  <link href="//cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="//cdn.jsdelivr.net/npm/codemirror@5.65.16/lib/codemirror.min.css" rel="stylesheet">
  <link href="//cdn.jsdelivr.net/npm/codemirror@5.65.16/theme/eclipse.min.css" rel="stylesheet">

  <link href="//cdn.jsdelivr.net/npm/ag-grid-community@31.0.0/styles/ag-grid.min.css" rel="stylesheet">
  <link href="//cdn.jsdelivr.net/npm/ag-grid-community@31.0.0/styles/ag-theme-alpine.min.css" rel="stylesheet">
  -->

  <!-- Custom CSS -->
  <link href="assets/vendor/vendor.css" rel="stylesheet">
  <link href="assets/app.css" rel="stylesheet">
  <link href="assets/codemirror-fix.css" rel="stylesheet">
</head>
<body>

  <div class="container-fluid app-container">
    <!-- header -->
    <div class="header">
      <h1>
        <!-- <img src="favicon.svg" alt="logo" class="app-logo"> -->
        <img src="data:image/png;base64,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" alt="logo" class="app-logo">
        <?php echo $appName . ' ' . $version; ?>
        <span class="optional-name"><?php echo ' ' . $optionalName; ?></span>
      </h1>

      <div class="header-controls">
        <select id="cluster-selector" class="form-select form-select-sm me-2">
          <?php
            foreach ($clausterList as $clusterName) {
              echo "<option value=\"{$clusterName}\">{$clusterName}</option>";
            }
          ?>
        </select>

        <? if ($readOnlyMode): ?>
        <span class="badge bg-warning mode-name">Read Only</span>
        <? else: ?>
        <span class="badge bg-danger mode-name">Write Enabled</span>
        <? endif; ?>
      </div>
    </div>

    <div class="row app-content">
      <!-- sidebar -->
      <div class="col-2 left-pane sidebar">
        <!-- Table list -->
        <div class="sidebar-section">
          <div id="sidebar-tabs" class="sidebar-tabs" role="tablist">
            <button class="sidebar-tab active" type="button" data-target="stab-1" role="tab">TABLE LIST</button>
            <button class="sidebar-tab" type="button" data-target="stab-2" role="tab">DB LIST
              <span class="badge bg-secondary" id="db-count">0</span>
              <span id="db-has-error"></span>
            </button>
          </div>

          <div id="sidebar-content" class="sidebar-content">
            <div class="tab-pane" id="stab-1" role="tabpanel">
              <div id="table-list" class="table-list">
                <!-- Table items will be generated by JavaScript -->
                <!--
                <div class="table-item">
                  <div class="table-physical-name">introduced_user_profile_header</div>
                  <div class="table-logical-name">Introduced User Profile Header (Unique)</div>
                </div>
                -->
              </div>
            </div>

            <div class="tab-pane" id="stab-2" role="tabpanel">
              <select id="db-selector" class="form-select form-select-sm mb-2" multiple>
                <!-- Database options will be generated by JavaScript -->
                <!--
                <option value="shard1">shard1</option>
                <option value="shard2">shard2</option>
                -->
              </select>
            </div>
          </div>
        </div>
      </div>

      <!-- right pane -->
      <div class="col-10 right-pane">
        <!-- Editor Container -->
        <div class="editor-container">

          <!-- Editor Toolbar -->
          <div class="editor-toolbar">
            <div class="toolbar-left">
              <!-- Open dialog and restore SQL in history to editor -->
              <button id="btn-history" class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#sql-history">
                <i class="bi bi-clock-history"></i> History
              </button>
            </div>
            <div class="toolbar-center">
              <!-- Use sql-formatter to clean SQL on editor -->
              <button id="btn-format" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-code"></i> Beautify SQL
              </button>
              <!-- Execute SQL, Ctrl+Enter also fires this event -->
              <!-- When event is triggered, confirmation modal will be shown -->
              <!-- Confirmation modal will ask for user confirmation before executing SQL -->
              <button id="btn-execute" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#execution-confirm">
                <i class="bi bi-play-fill"></i> Run (Ctrl+Enter)
              </button>
            </div>
            <div class="toolbar-right">
              <!-- Export CSV/XLSX by SheetJS -->
              <span class="me-2">Export</span>
              <button id="btn-export-csv" class="btn btn-outline-success btn-sm me-1">CSV</button>
              <button id="btn-export" class="btn btn-outline-success btn-sm">XLSX</button>
            </div>
          </div>

          <!-- SQL Editor -->
          <div class="sql-editor-container">
            <!-- Initialized with CodeMirror -->
            <div id="sql-editor"></div>
          </div>

        </div>

        <!-- Result Container -->
        <div class="result-container">
          <!-- Results Tabs -->
          <div class="results-tabs-container">
            <button class="tab-nav-btn tab-nav-left" id="tab-nav-left">
              <i class="bi bi-chevron-left"></i>
            </button>

            <div id="results-tabs" class="results-tabs" role="tablist">
              <!-- Tabs will be generated by JavaScript -->
              <!-- Format: Query X (Y) , X: Number of query, Y: found rows -->
              <!--
              <button class="results-tab active" type="button" data-target="tab-1" role="tab"><i class="bi bi-check-circle"></i> Query 1 (10)</button>
              <button class="results-tab error" type="button" data-target="tab-2" role="tab"><i class="bi bi-exclamation-triangle"></i> Query 2 (0)</button>
              -->
            </div>

            <button class="tab-nav-btn tab-nav-right" id="tab-nav-right">
              <i class="bi bi-chevron-right"></i>
            </button>
          </div>

          <!-- Results Grid -->
          <div id="results-content" class="results-content">
            <!-- Default view -->
            <div class="p-4 text-center text-muted">
              <i class="bi bi-database" style="font-size: 3rem; opacity: 0.3;"></i>
              <p class="mt-3">Results shown here.</p>
            </div>

            <!-- Tab panes will be generated JavaScript -->
            <!--
            <div class="tab-pane" id="tab-3" role="tabpanel">
              <div class="error-list">
                <div class="alert alert-danger align-items-center sql-error" role="alert">
                  <strong>ERROR [shard1]</strong> syntax error at or near "FROM"
                </div>
                <div class="alert alert-danger align-items-center sql-error" role="alert">
                  <strong>ERROR [shard1]</strong> syntax error at or near "FROM"
                </div>
                <div class="alert alert-danger align-items-center sql-error" role="alert">
                  <strong>ERROR [shard1]</strong> syntax error at or near "FROM"
                </div>
              </div>
            </div>
            -->
          </div>

        </div>
      </div>
    </div>
  </div>


  <!-- Modal: Alert Dialog -->
  <div class="modal fade" id="alert-dialog" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="alert-dialog-label" aria-hidden="true">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <h1 class="modal-title fs-5" id="alert-dialog-label"></h1>
        </div>
        <div class="modal-body"></div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">OK</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Modal: SQL Execution Confirmation -->
  <div class="modal fade" id="execution-confirm" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="execution-confirm-label" aria-hidden="true">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <h1 class="modal-title fs-5" id="execution-confirm-label">🔥Confirm!</h1>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          SQLs will be executed to all databases.<br>
          If updating queries are included, data will be modified.<br>
          Are you sure to execute these SQLs?
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="bi bi-x"></i> Cancel</button>
          <button type="button" class="btn btn-primary" id="btn-confirm-execute"><i class="bi bi-play-fill"></i> Execute</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Modal: SQL History -->
  <div class="modal fade" id="sql-history" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="sql-history-label" aria-hidden="true">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <div class="modal-header">
          <h1 class="modal-title fs-5" id="sql-history-label">📝SQL History</h1>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="bi bi-x"></i> Cancel</button>
        </div>
      </div>
    </div>
  </div>


  <!-- JavaScript Libraries -->
  <!--
  <script src="//cdn.jsdelivr.net/npm/jquery@3.5.1/dist/jquery.min.js"></script>
  <script src="//cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI" crossorigin="anonymous"></script>
  <script src="//cdn.jsdelivr.net/npm/codemirror@5.65.16/lib/codemirror.min.js"></script>
  <script src="//cdn.jsdelivr.net/npm/codemirror@5.65.16/mode/sql/sql.min.js"></script>
  <script src="//cdn.jsdelivr.net/npm/ag-grid-community@31.0.0/dist/ag-grid-community.min.js"></script>
  <script src="//cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
  <script src="//cdn.jsdelivr.net/npm/sql-formatter@15.6.8/dist/sql-formatter.min.js"></script>
  -->

  <script>
    window.MultiDbSql = {
      appShortName: '<?php echo $appShortName; ?>',
      appShortNameLower: '<?php echo $appShortNameLower; ?>',
      version: '<?php echo $version; ?>',
      isReadOnlyMode: <?php echo $readOnlyMode ? 'true' : 'false'; ?>,
    };
  </script>

  <!-- Custom JavaScript -->
  <script src="assets/app.js"></script>
  <script src="assets/vendor/vendor.js"></script>
</body>
</html>
        <?php
    };
    // Handle web requests
    $webHandler = new \MultiDbSqlTool\WebHandler();
    $webHandler->execute($templateFunction);
}

main();
