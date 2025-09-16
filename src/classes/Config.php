<?php

namespace MultiDbSqlTool;

class Config
{

    /**
     * App info
     */
    const APP_NAME = 'Multi-DB SQL Tool';
    const APP_SHORT_NAME = 'mDBSQL';
    const VERSION = '1.0.0-alpha';

    const DEFAULT_SESSION_NAME = 'MDBSQL_SESSION';
    const DEFAULT_SESSION_LIFETIME = 86400; // 1 day
    const MAX_QUERY_HISTORY = 50;

    protected $settings = [];

    /**
     * Constructor
     *
     * @param string|null $configPath
     * @throws \RuntimeException
     */
    protected function __construct($configPath = null)
    {
        if (null === $configPath) {
            $configPath = __DIR__ . '/../config/config.php';
        }

        if (!file_exists($configPath)) {
            throw new \RuntimeException("Configuration file not found: {$configPath}");
        }

        // Load configuration settings
        $this->settings = require $configPath;
    }

    /**
     * Get the instance of the Config class.
     * Used to retrieve the singleton instance.
     *
     * @return self
     */
    public static function getInstance()
    {
        static $instance;
        if (null === $instance) {
            $instance = new self();
        }
        return $instance;
    }

    /**
     * Get a configuration value by key.
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function get($key, $default = null)
    {
        return self::searchArrayByPath($this->settings, $key, $default);
    }

    /**
     * Search an array by a dot-notated path.
     *
     * @param array $array
     * @param string $path
     * @param mixed $default
     * @return mixed
     */
    public static function searchArrayByPath(array $array, $path, $default = null)
    {
        $keys = explode('.', $path);
        foreach ($keys as $key) {
            if (is_array($array) && array_key_exists($key, $array)) {
                $array = $array[$key];
            } else {
                return $default;
            }
        }
        return $array;
    }

    /**
     * Get the names of all database clusters.
     *
     * @return string[]
     */
    public static function getClusterNames()
    {
        return array_keys(self::getInstance()->get('dbs', []));
    }

    /**
     * Check if cluster exists
     *
     * @param string $clusterName
     * @return boolean
     */
    public static function clusterExists($clusterName)
    {
        return in_array($clusterName, self::getClusterNames());
    }

    /**
     * Get database settings for a specific cluster.
     *
     * @param string $clusterName
     * @param array $targetShards
     * @return array
     * @throws \InvalidArgumentException
     */
    public static function getDatabaseSettings($clusterName, array $targetShards = [])
    {
        if (!self::clusterExists($clusterName)) {
            throw new \InvalidArgumentException("Cluster '{$clusterName}' not found");
        }

        $dbSettings = self::getInstance()->get("dbs.$clusterName", []);
        if (empty($targetShards)) {
            return $dbSettings;
        }

        $result = [];
        foreach ($targetShards as $shard) {
            if (array_key_exists($shard, $dbSettings)) {
                $result[$shard] = $dbSettings[$shard];
            }
        }
        return $result;
    }

    /**
     * Get shard names for a specific cluster
     *
     * @param string $clusterName
     * @return array
     * @throws \InvalidArgumentException
     */
    public static function getShardNames($clusterName)
    {
        $dbs = self::getDatabaseSettings($clusterName);
        return array_keys($dbs);
    }

    /**
     * Check if the application is in read-only mode.
     *
     * @return bool
     */
    public static function isReadOnlyMode()
    {
        return (bool)self::getInstance()->get('readonly_mode', false);
    }
}
