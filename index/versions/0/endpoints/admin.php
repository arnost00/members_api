<?php

namespace Endpoints;

use Pecee\SimpleRouter\SimpleRouter as Router;
use Pecee\SimpleRouter\Exceptions\HttpException;

use Core\Api;
use Core\Endpoint;
use Core\Logging;
use Core\RequireAdminMiddleware;

$levelmap = [
    Logging::$LEVEL_INFO => "level-info",
    Logging::$LEVEL_WARNING => "level-warning",
    Logging::$LEVEL_ERROR => "level-error",
    Logging::$LEVEL_FATAL => "level-fatal",
];

use Pecee\Http\middleware\IMiddleware;
use Pecee\Http\Request;


class AdminLayout implements IMiddleware
{
    // checks for admin credentials
    public function handle(Request $request): void
    {
        require __DIR__ . "/../admin/layout.php";
    }
}


class Admin implements Endpoint {
    public static function init(): void {
        Router::group(["prefix" => "/admin", "middleware" => RequireAdminMiddleware::class], function () {
            Router::group(["middleware" => AdminLayout::class], function () {
                Router::fetch("/", [static::class, "index"]);
                Router::fetch("/logging", [static::class, "logging"]);
            });
        });
    }

    public static function index() {
        echo "Hello";
    }

    public static function logging() {
        echo "<table><tr><th>Timestamp</th><th>Clubname</th><th>IP</th><th>Level</th><th>Data</th></tr>";
        foreach (Logging::parse("./.logging/2024/April/2024-04-01.log") as $logline) {
            echo "<tr>";

            echo "<td>" . $logline["timestamp"] . "</td>";
            echo "<td>" . $logline["clubname"] . "</td>";
            echo "<td>" . $logline["ip"] . "</td>";
            echo "<td>" . $logline["level"] . "</td>";
            echo "<td>" . $logline["data"] . "</td>";

            echo "</tr>";
        }
        echo "</table>";
    }
}