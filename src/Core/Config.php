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
}