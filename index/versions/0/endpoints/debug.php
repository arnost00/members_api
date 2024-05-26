<?php

namespace Endpoints;

use Pecee\SimpleRouter\SimpleRouter as Router;
use Pecee\SimpleRouter\Exceptions\HttpException;

use Core\Api;
use Core\Endpoint;

use Controllers\RequireTokenMiddleware;
use Controllers\NotificationContent;
use Controllers\Notifications;

use Controllers\Logging;

class Debug implements Endpoint {
    public static function init(): void {
        Router::group(["prefix" => "/debug"], function () {
            $race_id = ["race_id" => "[0-9]+"];
            $user_id = ["user_id" => "[0-9]+"];

            Router::fetch("/{race_id}/notify", [static::class, "notify"])->where($race_id);
            Router::fetch("/logging", [static::class, "logging"]);
            Router::fetch("/notify_topics", [static::class, "notify_topics"]);
            Router::fetch("/zofia", [static::class, "zofia"]);
        });
    }

    public static function notify($race_id) {
        Notifications::send(NotificationContent::race_changed($race_id));
    }

    public static function logging() {
        Logging::info("hello world");
        echo "wrote `hello world` as info";
    }

    public static function notify_topics() {
        $token = "cx5XgardT-C1240cKYuSCi:APA91bGz7UYcWDKXy84wKzHAdQS00jO77-KTVdUmucD5hkORPcqDxOaUFwv9EQiaLW1AygmQ-hA6IKd4fjlEZ9RhyUOOkkiL-XODORCFeMQkgFuNwoo3SbAOvJjESjoi1by9Uzw16u0J";
        return Notifications::topics($token);
    }

    public static function zofia() {
        $child = Api::database()->fetch_assoc("SELECT * FROM `" . TBL_USER . "` WHERE `id` = ? OR `chief_id` = ? ORDER BY CASE WHEN `id` = ? THEN 1 ELSE 2 END", 175, 175, 175);
        $zavxus = Api::database()->fetch_assoc("SELECT zavxus.* FROM `" . TBL_ZAVXUS . "` AS zavxus, `" . TBL_USER . "` AS user WHERE zavxus.id_user = user.id AND user.id = ? AND zavxus.id_zavod = ? LIMIT 1", 175, 169);
        // $output = Api::database()->query("SELECT zavxus.si_chip AS si_chip_temp, zavxus.*, user.* FROM `" . TBL_ZAVXUS . "` AS zavxus, `" . TBL_USER . "` AS user WHERE zavxus.id_user = user.id AND user.id = ? AND `id_zavod` = ?", 175, 169);

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