<?php

namespace ApiTwo;

use Core\ApiException;
use Core\Logging;

require_once __DIR__ . "/config.php";

class Database {
    private static $_mysqli;

    public static function mysqli() {
        if (static::$_mysqli === null) {
            mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
            register_shutdown_function([static::class, "close"]);
            
            // initialize
            static::$_mysqli = new \mysqli(Config::$g_dbserver, Config::$g_dbuser, Config::$g_dbpass, Config::$g_dbname, Config::$g_dbport);

            // set charset to prevent decoding errors
            static::query("SET CHARACTER SET UTF8");

        }

        return static::$_mysqli;
    }

    public static function close() {
        static::mysqli()->close();
    }

    public static function query($query, ...$params) {
        return static::mysqli()->execute_query($query, $params);
    }

    public static function fetch_assoc($query, ...$params) {
        return static::query($query, ...$params)->fetch_assoc();
    }

    public static function fetch_assoc_all($query, ...$params) {
        return static::query($query, ...$params)->fetch_all(MYSQLI_ASSOC);
    }
}