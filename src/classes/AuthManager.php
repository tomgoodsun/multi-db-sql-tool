<?php

/**
 * Basic認証管理クラス
 * PSR-12準拠
 */
class AuthManager 
{
    /**
     * Basic認証チェック
     */
    public static function checkBasicAuth(): bool 
    {
        $authConfig = Config::getBasicAuth();
        
        // Basic認証が設定されていない場合は認証不要
        if (empty($authConfig)) {
            return true;
        }

        // Basic認証ヘッダーの確認
        if (!isset($_SERVER['PHP_AUTH_USER']) || !isset($_SERVER['PHP_AUTH_PW'])) {
            self::requireAuth();
            return false;
        }

        $inputUser = $_SERVER['PHP_AUTH_USER'];
        $inputPass = $_SERVER['PHP_AUTH_PW'];

        // 設定された認証情報と照合
        foreach ($authConfig as $credentials) {
            if (count($credentials) >= 2 && 
                $credentials[0] === $inputUser && 
                $credentials[1] === $inputPass) {
                return true;
            }
        }

        self::requireAuth();
        return false;
    }

    /**
     * Basic認証を要求
     */
    private static function requireAuth(): void 
    {
        $appName = Config::getAppName();
        header('WWW-Authenticate: Basic realm="' . $appName . '"');
        header('HTTP/1.0 401 Unauthorized');
        echo '<h1>401 Unauthorized</h1>';
        echo '<p>Access denied. Please provide valid credentials.</p>';
        exit;
    }
}
