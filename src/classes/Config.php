<?php

namespace MultiDbSqlTool;

class Config
{
    protected $settings = [];

    /**
     * Undocumented function
     */
    protected function __construct($configPath = null)
    {
        if ($configPath === null) {
            $configPath = __DIR__ . '/../config/config.php';
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
        if ($instance === null) {
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
     * Get database settings for a specific cluster.
     *
     * @param string $clusterName
     * @return array
     */
    public static function getDatabaseSettings($clusterName)
    {
        return self::getInstance()->get("dbs.$clusterName", []);
    }

    public static function getShardNames($clusterName)
    {
        $dbs = self::getDatabaseSettings($clusterName);
        return array_keys($dbs);
    }
}
