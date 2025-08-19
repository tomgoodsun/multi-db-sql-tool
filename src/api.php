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

// APIハンドラーの実行
try {
    $apiHandler = new ApiHandler();
    $apiHandler->handle();
} catch (Exception $e) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
