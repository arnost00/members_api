<?php

namespace Endpoints;

use Pecee\SimpleRouter\SimpleRouter as Router;

require_once __DIR__ . "/../controllers/content.php";
require_once __DIR__ . "/../controllers/holders.php";

require_once \Manifest::$core_directory . "/api.php";
require_once \Manifest::$core_directory . "/endpoint.php";
require_once \Manifest::$core_directory . "/notify.php";
require_once \Manifest::$core_directory . "/logging.php";

use Core\Api;
use Core\Endpoint;
use Core\Notifications;
use Core\Logging;

use Controllers\NotificationContent;
use Controllers\Tables;
use Controllers\Policies;

class Debug implements Endpoint {
    public static function init(): void {
        Router::group(["prefix" => "/debug"], function () {
            $race_id = ["race_id" => "[0-9]+"];
            $user_id = ["user_id" => "[0-9]+"];

            Router::form("/{race_id}/notify", [static::class, "notify"])->where($race_id);
            Router::form("/policies/{user_id}", [static::class, "policies"])->where($user_id);
            Router::form("/logging", [static::class, "logging"]);
            Router::form("/version", [static::class, "version"]);
            Router::form("/notify_topics", [static::class, "notify_topics"]);
            Router::form("/zofia", [static::class, "zofia"]);
        });
    }

    public static function notify($race_id) {
        Notifications::send(NotificationContent::race_changed($race_id));
    }

    public static function logging() {
        Logging::info("hello world");
        echo "wrote `hello world` as info";
    }

    public static function version() {
        return phpversion();
    }

    public static function notify_topics() {
        $token = "cx5XgardT-C1240cKYuSCi:APA91bGz7UYcWDKXy84wKzHAdQS00jO77-KTVdUmucD5hkORPcqDxOaUFwv9EQiaLW1AygmQ-hA6IKd4fjlEZ9RhyUOOkkiL-XODORCFeMQkgFuNwoo3SbAOvJjESjoi1by9Uzw16u0J";
        return Notifications::topics($token);
    }

    public static function policies($user_id) {
        $output = Api::database()->fetch_assoc("SELECT * FROM `" . Tables::$ACCOUNT . "` WHERE `id_users` = ? LIMIT 1", $user_id);

        echo "SMALL MANAGER: " . Policies::$MNG_SMALL;
        echo "BIG MANAGER: " . Policies::$MNG_BIG;
        
        return "<pre>" . json_encode($output, JSON_PRETTY_PRINT) . "</pre>";
    }

    public static function zofia() {
        $child = Api::database()->fetch_assoc("SELECT * FROM `" . Tables::$USER . "` WHERE `id` = ? OR `chief_id` = ? ORDER BY CASE WHEN `id` = ? THEN 1 ELSE 2 END", 175, 175, 175);
        $zavxus = Api::database()->fetch_assoc("SELECT zavxus.* FROM `" . Tables::$ZAVXUS . "` AS zavxus, `" . Tables::$USER . "` AS user WHERE zavxus.id_user = user.id AND user.id = ? AND zavxus.id_zavod = ? LIMIT 1", 175, 169);
        // $output = Api::database()->query("SELECT zavxus.si_chip AS si_chip_temp, zavxus.*, user.* FROM `" . Tables::$ZAVXUS . "` AS zavxus, `" . Tables::$USER . "` AS user WHERE zavxus.id_user = user.id AND user.id = ? AND `id_zavod` = ?", 175, 169);

        // $result = [];
        // while ($temp = $output->fetch_assoc()) {
        //     var_dump($temp["si_chip_temp"] ?: $temp["si_chip"]);
        //     $result[] = $temp;
        // }
        var_dump($zavxus);
        var_dump($child);

        var_dump($zavxus["si_chip"] ?: $child["si_chip"]);

    }
}