<?php

namespace ApiTwo;

use Pecee\SimpleRouter\SimpleRouter as Router;

require_once __DIR__ . "/../boilerplate/database.php";
require_once __DIR__ . "/../boilerplate/endpoint.php";
require_once __DIR__ . "/../boilerplate/config.php";
require_once __DIR__ . "/../boilerplate/middlewares.php";

use Core\ApiException;

class System implements Endpoint {
    public static function init(): void {
        Router::partialGroup("/system", function () {
            Router::form("/", [static::class, "index"]);
            Router::post("/login", [static::class, "login"]);
            Router::get("/cron", [static::class, "cron"]);

            Router::group(["middleware" => AuthRequired::class, "prefix" => "/device"], function () {
                Router::post("/fcm_token", [static::class, "fcm_token_update"]);
                Router::delete("/fcm_token", [static::class, "fcm_token_delete"]);
                Router::post("/", [static::class, "device_update"]);
            });

            Router::group(["middleware" => TokenRequired::class, "prefix" => "/device"], function () {
                Router::delete("/", [static::class, "device_delete"]);
            });
        });
    }

    public static function index() {
        echo "<h1>System</h1>";
    }

    public static function login() {
        $username = Input::key("username");
        $password = Input::key("password");

        // accept empty values (fallback)
        $app_version = Input::key("app_version", required: false, default: "");
        $device_name = Input::key("device_name", required: false, default: "");

        $output = Database::fetch_assoc("SELECT `id_users`, `heslo`, `locked` FROM `" . Tables::$TBL_ACCOUNT . "` WHERE `login` = ? LIMIT 1", $username);

        if ($output === null) {
            throw new ApiException("Username does not exists.", 403);
        }

        $user_id = $output["id_users"];

        if ($output["locked"]) {
            throw new ApiException("Your account is locked.", 403);
        }

        if (!password_verify(md5($password), $output["heslo"])) {
            throw new ApiException("Wrong password.", 403);
        }

        Database::query("UPDATE `" . Tables::$TBL_ACCOUNT . "` SET `last_visit` = ? WHERE `id_users` = ?", time(), $user_id);
        Session::pull_policy_by_user_id($output["id_users"]);

        // expires in 90 days
        $expiration = time() + 2 * 86400;

        // generate device uuid
        $device = openssl_random_pseudo_bytes(16);
        $device[6] = chr(ord($device[6]) & 0x0f | 0x40);
        $device[8] = chr(ord($device[8]) & 0x3f | 0x80);
        $device = vsprintf("%s%s-%s-%s-%s-%s%s%s", str_split(bin2hex($device), 4));

        Database::insert(Tables::$TBL_TOKENS, [
            "user_id" => $user_id,
            "device" => $device,
            "device_name" => $device_name,
            "app_version" => $app_version,
        ]);

        response()->json([
            "access_token" => Session::generate_access_token($user_id, $expiration, $device),
            "expiration" => $expiration,
            "device" => $device,
            "user_id" => $user_id,
        ]);
    }

    public static function fcm_token_update() {
        $token = Input::key("token");

        Database::query("
            INSERT INTO " . Tables::$TBL_TOKENS . " (
                `device`,
                `user_id`,
                `fcm_token`,
                `fcm_token_timestamp`
            ) VALUES (?, ?, ?, FROM_UNIXTIME(?))
            ON DUPLICATE KEY UPDATE
                `fcm_token` = VALUES(`fcm_token`),
                `fcm_token_timestamp` = VALUES(`fcm_token_timestamp`)
        ", Session::$device, Session::$user_id, $token, time());
    }

    public static function fcm_token_delete() {
        Database::query("UPDATE `" . Tables::$TBL_TOKENS . "` SET `fcm_token` = NULL WHERE `device` = ? AND `user_id` = ?", Session::$device, Session::$user_id);
    }

    public static function device_update() {
        if (Session::$device === null) {
            throw new ApiException("Device is required.", 400);
        }

        $device_name = Input::key("device_name");
        $app_version = Input::key("app_version");

        Database::query("
            INSERT INTO `" . Tables::$TBL_TOKENS . "` (
                device,
                user_id,
                device_name,
                app_version,
                app_last_opened
            ) VALUES (?, ?, ?, ?, FROM_UNIXTIME(?))
            ON DUPLICATE KEY UPDATE
                device_name = VALUES(device_name),
                app_version = VALUES(app_version),
                app_last_opened = VALUES(app_last_opened)
        ", Session::$device, Session::$user_id, $device_name, $app_version, time());
    }

    public static function device_delete() {
        if (Session::$device === null) {
            throw new ApiException("Device is required.", 400);
        }

        Database::query("DELETE FROM `" . Tables::$TBL_TOKENS . "` WHERE `device` = ? AND `user_id` = ?", Session::$device, Session::$user_id);
    }

    public static function cron() {
        // import only if we actually need to run cron
        require_once __DIR__ . "/../boilerplate/cron/cron.php";
        Cron::start();
    }
}
