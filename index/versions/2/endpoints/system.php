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
            Router::get("/cron", [static::class, "cron"]);
            Router::post("/login", [static::class, "login"]);
            
            Router::group(["prefix" => "/fcm_token", "middleware" => LoginRequired::class], function () {
                Router::post("/update", [static::class, "fcm_token_update"]);
                Router::post("/delete", [static::class, "fcm_token_delete"]);
            });
        });
    }

    public static function index() {
        echo "<h1>System</h1>";
    }

    public static function login() {
        $username = Input::key("username");
        $password = Input::key("password");
        
        $output = Database::fetch_assoc("SELECT `id_users`, `heslo`, `locked` FROM `" . Tables::$TBL_ACCOUNT . "` WHERE `login` = ? LIMIT 1", $username);
        
        if ($output === null) {
            throw new ApiException("Username does not exists.", 401);
        }
        
        $user_id = $output["id_users"];
        
        if ($output["locked"]) {
            throw new ApiException("Your account is locked.", 401);
        }

        if (!password_verify(md5($password), $output["heslo"])) {
            throw new ApiException("Wrong password.", 401);
        }

        Database::query("UPDATE `" . Tables::$TBL_ACCOUNT . "` SET `last_visit` = ? WHERE `id_users` = ?", time(), $user_id);
        Session::pull_policy_by_user_id($output["id_users"]);

        // expires in 90 days
        $expiration = time() + 90 * 86400;

        response()->json([
            "access_token" => Session::generate_access_token($user_id, $expiration),
            "expiration" => $expiration,
            "policies" => [
                "policy_adm" => Session::$policy_adm,
                "policy_sadm" => Session::$policy_sadm,
                "policy_news" => Session::$policy_news,
                "policy_regs" => Session::$policy_regs,
                "policy_fin" => Session::$policy_fin,
                "policy_mng_big" => Session::$policy_mng_big,
                "policy_mng_small" => Session::$policy_mng_small,
            ],
        ]);
    }

    public static function fcm_token_update() {
        $token = Input::key("token");
        $device = Input::key("device", filter: Input::$FILTER_DEVICE);

        Database::query("INSERT INTO " . Tables::$TBL_TOKENS . " (`device`, `user_id`, `fcm_token`, `fcm_token_timestamp`) VALUES (?, ?, ?, FROM_UNIXTIME(?)) ON DUPLICATE KEY UPDATE `fcm_token` = VALUES(`fcm_token`), `fcm_token_timestamp` = VALUES(`fcm_token_timestamp`)", $device, Session::$user_id, $token, time());
    }

    public static function fcm_token_delete() {
        $device = Input::key("device", filter: Input::$FILTER_DEVICE);

        Database::query("UPDATE " . Tables::$TBL_TOKENS . " SET `fcm_token` = NULL, `fcm_token_timestamp` = FROM_UNIXTIME(?) WHERE `device` = ? AND `user_id` = ?", time(), $device, Session::$user_id);
    }

    public static function cron() {
        // import only if we actually need to run cron
        require_once __DIR__ . "/../boilerplate/cron/cron.php";
        Cron::start();
    }
}   
