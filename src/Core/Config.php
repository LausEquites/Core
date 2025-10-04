<?php


namespace Core;

/**
 * Core\Config
 *
 * Lazy-loading configuration accessor for applications using Core.
 *
 * Behavior:
 * - Loads the application config file on first access from APP_ROOT . '/backend/config/config.php'.
 * - The config file may return either an associative array or an stdClass.
 * - Values can be accessed via getAll() or getKey($key).
 *
 * Notes:
 * - Ensure APP_ROOT is defined by your bootstrap and points to your app root.
 * - This class does not cache across processes; it only caches values per-request.
 */
class Config
{
    /**
     * @var array|object Loaded configuration (associative array or stdClass)
     */
    private static $config = [];

    /**
     * Get the whole configuration structure.
     *
     * On first call, the config is loaded from APP_ROOT . '/backend/config/config.php'.
     *
     * @return array|object Associative array or stdClass, depending on your config.php return type.
     */
    public static function getAll()
    {
        if (empty(self::$config)) {
            self::$config = include APP_ROOT . '/backend/config/config.php';
        }

        return self::$config;
    }

    /**
     * Get a single config value by key.
     *
     * Works with both array-based and object-based configs.
     *
     * @param string $key Configuration key
     * @return mixed The value for the provided key
     * @throws \Exception If the key does not exist in the config
     */
    public static function getKey($key)
    {
        // Ensure configuration is loaded
        if (empty(self::$config)) {
            self::getAll();
        }

        // Array-based config
        if (is_array(self::$config)) {
            if (!array_key_exists($key, self::$config)) {
                throw new \Exception("No config for '$key'");
            }
            return self::$config[$key];
        }

        // Object-based config (stdClass)
        if (is_object(self::$config)) {
            if (!property_exists(self::$config, $key)) {
                throw new \Exception("No config for '$key'");
            }
            return self::$config->$key;
        }

        // Unsupported config type
        throw new \Exception('Invalid config format: expected array or object');
    }
}