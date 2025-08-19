<?php

/**
 * 設定管理クラス
 * PSR-12準拠
 */
class Config
{
    private static $config = null;

    /**
     * 設定を読み込み
     */
    public static function load(): array
    {
        if (self::$config === null) {
            $configFile = __DIR__ . '/../config/config.php';
            if (!file_exists($configFile)) {
                throw new Exception('Configuration file not found: ' . $configFile);
            }
            self::$config = require $configFile;
        }
        return self::$config;
    }

    /**
     * 設定値を取得
     */
    public static function get(string $key = null, $default = null) 
    {
        $config = self::load();
        
        if ($key === null) {
            return $config;
        }

        // ドット記法対応 (例: 'session.lifetime')
        $keys = explode('.', $key);
        $value = $config;

        foreach ($keys as $k) {
            if (!isset($value[$k])) {
                return $default;
            }
            $value = $value[$k];
        }

        return $value;
    }

    /**
     * アプリケーション名を取得
     */
    public static function getAppName(): string 
    {
        $optionalName = self::get('optional_name', '');
        $baseName = 'Multi-DB SQL Tool';
        
        return $optionalName ? $baseName . ' for ' . $optionalName : $baseName;
    }

    /**
     * 現在選択中のクラスター名を取得
     */
    public static function getCurrentCluster(): string 
    {
        $clusters = array_keys(self::get('dbs', []));
        return $clusters[0] ?? '';
    }

    /**
     * 指定クラスターのDB設定を取得
     */
    public static function getClusterDbs(string $clusterName): array 
    {
        return self::get("dbs.{$clusterName}", []);
    }

    /**
     * 読み取り専用モードかどうか
     */
    public static function isReadOnlyMode(): bool 
    {
        return (bool) self::get('readonly_mode', true);
    }

    /**
     * Basic認証設定を取得
     */
    public static function getBasicAuth(): array 
    {
        return self::get('basic_auth', []);
    }
}
