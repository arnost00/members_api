<?php

namespace Endpoints;

use Pecee\SimpleRouter\SimpleRouter as Router;
use Pecee\SimpleRouter\Exceptions\HttpException;

use Core\Api;
use Core\Endpoint;

class Basic implements Endpoint
{
    public static function init(): void {
        Router::fetch("/redirect", [static::class, "redirect"]);
    }

    public static function redirect() {
        // status code 302, indicates that the resource requested has been temporarily moved
        redirect(Api::config()->g_baseadr);
    }
}