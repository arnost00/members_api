<?php

namespace Endpoints;

use Pecee\SimpleRouter\SimpleRouter as Router;
use Pecee\SimpleRouter\Exceptions\HttpException;

use Core\Api;
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