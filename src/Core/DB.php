<?php

namespace Core;

class DB
{
    /** @var \PDO */
    private static $db;

    /** Get DB connection
     *
     * @return \PDO
     * @throws \Exception
     */
    public static function get()
    {
        if (self::$db === null) {
            self::$db = self::getFromConfig();
        }

        return self::$db;
    }

    /**
     * @return \PDO
     * @throws \Exception
     */
    public static function getFromConfig()
    {
        $config = Config::getAll();
        if (empty($config->db)) {
            throw new \Exception("No DB in config");
        }

        $dbConf = $config->db;
        $dsn = "mysql:dbname={$dbConf->db};host={$dbConf->host}";
        $user = $dbConf->user;
        $password = $dbConf->pass;

        return new \PDO($dsn, $user, $password);
    }
}