<?php

namespace Endpoints;

use Pecee\SimpleRouter\SimpleRouter as Router;
use Manifest\Manifest;

require_once Manifest::$core_directory . "/endpoint.php";
require_once Manifest::$core_directory . "/clubs.php";

use Core\Endpoint;
use Core\Clubs;

class General implements Endpoint {
    public static function init(): void {
        Router::form("/clubs", [static::class, "clubs"]);
    }

    public static function clubs() {
        response()->json(Clubs::list());
    }
}