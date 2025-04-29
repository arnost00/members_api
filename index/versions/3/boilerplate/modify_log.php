<?php

namespace ApiTwo;

require_once __DIR__ . "/database.php";
require_once __DIR__ . "/session.php";

class ModifyLog {
    public static function query(string $action, string $table, string $description) {
        return Database::query("INSERT INTO " . Tables::$TBL_MODLOG . " (`timestamp`, `action`, `table`, `description`, `author`) VALUES (?, ?, ?, ?, ?)", time(), $action, $table, $description, Session::$user_id);
    }

    public static function add(string $table, string $description) {
        return static::query("add", $table, $description);
    }

    public static function edit(string $table, string $description) {
        return static::query("edit", $table, $description);
    }

    public static function delete(string $table, string $description) {
        return static::query("delete", $table, $description);
    }
}
