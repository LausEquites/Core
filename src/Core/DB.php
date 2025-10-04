<?php

namespace Core;

/**
 * Core\DB
 *
 * Simple PDO connection factory/singleton used by Core components (e.g., ActiveRecord).
 *
 * Behavior:
 * - DB::get() returns a singleton PDO instance for the lifetime of the PHP request.
 * - DB::getFromConfig() constructs a PDO from an application configuration loaded via Core\Config.
 *
 * Configuration formats supported (via Config::getAll()):
 * - Array or stdClass with a top-level "db" key/object.
 * - Within "db":
 *   - Either provide a full DSN as 'dsn' plus 'user' and 'pass'.
 *   - Or provide parts: 'host', 'db' (database name), optional 'port', optional 'charset' (default utf8mb4), plus 'user' and 'pass'.
 *   - Optional 'options' (array) to pass as PDO options; merged over defaults.
 */
class DB
{
    /** @var \PDO */
    private static $db;

    /**
     * Get the shared PDO connection (singleton per request).
     *
     * @return \PDO
     * @throws \Exception When configuration is missing or invalid.
     */
    public static function get()
    {
        if (self::$db === null) {
            self::$db = self::getFromConfig();
        }

        return self::$db;
    }

    /**
     * Create a PDO instance using application configuration.
     *
     * Accepted config examples:
     * - Array form:
     *   [
     *     'db' => [ 'dsn' => 'mysql:host=localhost;dbname=app;charset=utf8mb4', 'user' => 'app', 'pass' => 'secret' ]
     *   ]
     * - Object form:
     *   (object) ['db' => (object) ['host' => 'localhost', 'db' => 'app', 'user' => 'app', 'pass' => 'secret']]
     *
     * If 'dsn' is omitted, a MySQL DSN is assembled from host/db[/port] and charset (default utf8mb4).
     *
     * @return \PDO
     * @throws \Exception When the required DB config entries are missing.
     */
    public static function getFromConfig()
    {
        $config = Config::getAll();

        // Extract top-level db config from array or object
        if (is_array($config)) {
            $dbConf = $config['db'] ?? null;
        } elseif (is_object($config)) {
            $dbConf = $config->db ?? null;
        } else {
            throw new \Exception('Invalid config format: expected array or object');
        }

        if (!$dbConf) {
            throw new \Exception('No DB in config');
        }

        // Helper to read values from array or object
        $get = function ($conf, $key, $default = null) {
            if (is_array($conf)) {
                return $conf[$key] ?? $default;
            }
            if (is_object($conf)) {
                return isset($conf->$key) ? $conf->$key : $default;
            }
            return $default;
        };

        $dsn = $get($dbConf, 'dsn');
        $user = $get($dbConf, 'user');
        $password = $get($dbConf, 'pass');

        if (!$dsn) {
            $host = $get($dbConf, 'host');
            $dbName = $get($dbConf, 'db');
            $port = $get($dbConf, 'port');
            $charset = $get($dbConf, 'charset', 'utf8mb4');

            if (!$host || !$dbName) {
                throw new \Exception("DB config missing required keys: 'dsn' or ('host' and 'db')");
            }

            $dsn = "mysql:dbname={$dbName};host={$host};charset={$charset}";
            if ($port) {
                $dsn .= ";port={$port}";
            }
        }

        // Default PDO options
        $options = [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
        ];
        $extraOptions = $get($dbConf, 'options');
        if (is_array($extraOptions)) {
            // Merge, allowing user-provided options to override defaults
            $options = $extraOptions + $options;
        }

        return new \PDO($dsn, $user, $password, $options);
    }
}
