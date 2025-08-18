<?php
session_start();

// オートローダー
spl_autoload_register(function ($class_name) {
    $file = __DIR__ . '/classes/' . $class_name . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});

// エラーハンドリング
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    // 設定読み込み
    $config = Config::getClusterConfig('development');
    
    // データベースマネージャー初期化
    $dbManager = new DatabaseManager($config);
    $sqlExecutor = new SqlExecutor($dbManager, $config['settings']['read_only_mode']);
    
    // POSTリクエストの処理
    $results = null;
    $error = null;
    
    if ($_POST && isset($_POST['sql'])) {
        try {
            $results = $sqlExecutor->executeOnAllShards($_POST['sql']);
            
            // セッションに履歴を保存
            if (!isset($_SESSION['query_history'])) {
                $_SESSION['query_history'] = [];
            }
            array_unshift($_SESSION['query_history'], [
                'sql' => $_POST['sql'],
                'timestamp' => date('Y-m-d H:i:s')
            ]);
            // 履歴は最新20件まで
            $_SESSION['query_history'] = array_slice($_SESSION['query_history'], 0, 20);
            
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
    
} catch (Exception $e) {
    $error = 'Initialization error: ' . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Multi-DB SQL Tool</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/3.4.1/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/codemirror.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/theme/monokai.min.css" rel="stylesheet">
    <style>
        html, body { height: 100%; margin: 0; padding: 0; }
        .container-fluid { height: 100vh; display: flex; flex-direction: column; }
        .header { background: #2c3e50; color: white; padding: 10px; }
        .main-content { flex: 1; display: flex; overflow: hidden; }
        .sidebar { width: 250px; background: #ecf0f1; padding: 15px; overflow-y: auto; }
        .content-area { flex: 1; display: flex; flex-direction: column; }
        .sql-editor { height: 40vh; }
        .results-area { flex: 1; overflow: hidden; padding: 10px; }
        .CodeMirror { height: 100%; border: 1px solid #ddd; }
        .table-responsive { max-height: 100%; overflow: auto; }
        .shard-label { font-weight: bold; color: #2c3e50; }
        .query-history { max-height: 300px; overflow-y: auto; }
        .history-item { cursor: pointer; padding: 5px; border-bottom: 1px solid #ddd; }
        .history-item:hover { background: #f8f9fa; }
    </style>
</head>
<body>
    <div class="container-fluid">
        <!-- ヘッダー -->
        <div class="header">
            <h4 class="margin-0">Multi-DB SQL Tool</h4>
        </div>
        
        <div class="main-content">
            <!-- サイドバー -->
            <div class="sidebar">
                <h5>接続状況</h5>
                <ul class="list-unstyled">
                    <?php if (isset($dbManager)): ?>
                        <?php foreach ($dbManager->getShardNames() as $shardName): ?>
                            <li>
                                <span class="label <?= $dbManager->testConnection($shardName) ? 'label-success' : 'label-danger' ?>">
                                    <?= htmlspecialchars($shardName) ?>
                                </span>
                            </li>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </ul>
                
                <hr>
                
                <h5>サンプルテーブル</h5>
                <ul class="list-unstyled">
                    <li><code>users</code> - ユーザーマスタ</li>
                    <li><code>orders</code> - 注文データ</li>
                </ul>
                
                <hr>
                
                <h5>クエリ履歴</h5>
                <div class="query-history">
                    <?php if (isset($_SESSION['query_history'])): ?>
                        <?php foreach ($_SESSION['query_history'] as $history): ?>
                            <div class="history-item" onclick="setQuery('<?= htmlspecialchars(addslashes($history['sql'])) ?>')">
                                <small class="text-muted"><?= htmlspecialchars($history['timestamp']) ?></small><br>
                                <code><?= htmlspecialchars(substr($history['sql'], 0, 50)) ?><?= strlen($history['sql']) > 50 ? '...' : '' ?></code>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- コンテンツエリア -->
            <div class="content-area">
                <!-- SQLエディター -->
                <div class="sql-editor">
                    <form method="post" id="sqlForm">
                        <div class="form-group">
                            <textarea id="sqlEditor" name="sql" class="form-control"><?= isset($_POST['sql']) ? htmlspecialchars($_POST['sql']) : 'SELECT * FROM users LIMIT 10;' ?></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <span class="glyphicon glyphicon-play"></span> 実行
                        </button>
                        <button type="button" class="btn btn-default" onclick="clearEditor()">
                            <span class="glyphicon glyphicon-remove"></span> クリア
                        </button>
                    </form>
                </div>
                
                <!-- 結果表示エリア -->
                <div class="results-area">
                    <?php if ($error): ?>
                        <div class="alert alert-danger">
                            <strong>エラー:</strong> <?= htmlspecialchars($error) ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($results): ?>
                        <?php foreach ($results as $shardName => $shardResults): ?>
                            <h4 class="shard-label"><?= htmlspecialchars($shardName) ?></h4>
                            
                            <?php foreach ($shardResults as $result): ?>
                                <?php if ($result['success']): ?>
                                    <?php if (!empty($result['data'])): ?>
                                        <div class="table-responsive">
                                            <table class="table table-striped table-bordered table-condensed">
                                                <thead>
                                                    <tr>
                                                        <?php foreach (array_keys($result['data'][0]) as $column): ?>
                                                            <th><?= htmlspecialchars($column) ?></th>
                                                        <?php endforeach; ?>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($result['data'] as $row): ?>
                                                        <tr>
                                                            <?php foreach ($row as $value): ?>
                                                                <td><?= htmlspecialchars($value ?? '') ?></td>
                                                            <?php endforeach; ?>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php else: ?>
                                        <p class="text-muted">結果なし</p>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <div class="alert alert-warning">
                                        <strong>クエリエラー:</strong> <?= htmlspecialchars($result['error']) ?>
                                    </div>
                                <?php endif; ?>
                                <hr>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/codemirror.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/mode/sql/sql.min.js"></script>
    <script>
        // CodeMirrorの初期化
        var editor = CodeMirror.fromTextArea(document.getElementById('sqlEditor'), {
            mode: 'text/x-mysql',
            theme: 'monokai',
            lineNumbers: true,
            indentUnit: 2,
            smartIndent: true,
            extraKeys: {
                "Ctrl-Enter": function(cm) {
                    document.getElementById('sqlForm').submit();
                }
            }
        });
        
        function setQuery(sql) {
            editor.setValue(sql);
        }
        
        function clearEditor() {
            editor.setValue('');
        }
    </script>
</body>
</html>
