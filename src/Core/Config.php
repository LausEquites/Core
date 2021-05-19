<?php


namespace Core;


class Config
{
    private static $config = [];

    /** Get whole config
     *
     * @return array
     */
    public static function getAll()
    {
        if (empty(self::$config)) {
            self::$config = include APP_ROOT . '/backend/config/config.php';
        }

        return self::$config;
    }

    /** Get a value from the config
     *
     * @param $key
     * @return mixed
     * @throws \Exception
     */
    public static function getKey($key)
    {
        if (!isset(self::$config->$key)) {
            throw new \Exception("No config for '$key'");
        }

        return self::$config->$key;
    }
}