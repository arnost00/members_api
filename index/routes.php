<?php

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: *, Authorization");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");

require_once __DIR__ . "/../core/exceptions.php";

// the json header should not be set globally
// because it is handled by the router if needed
//
// header("Content-Type: application/json");

use Pecee\SimpleRouter\SimpleRouter as Router;
use Pecee\SimpleRouter\Exceptions\HttpException;

use Manifest\Manifest;
use Core\ApiCrashReport;

set_exception_handler([ApiCrashReport::class, "report"]);

// fallback values
request()->debug = false;
request()->current = null;
request()->version = null;

Router::group(["prefix" => "/api/"], function () {
    Router::form("/", function () {
        return "<h1>Members API</h1>";
    });

    Router::form("/error", function () {
        throw new HttpException("As you wish!", 418);
    });

    Router::group(["prefix" => "/{version}"], function ($version = null) {
        // early return to allow other routers to load
        if ($version === null) {
            return;
        }

        // allow only digits
        // double check the version to make sure it can't be exploited
        if (!preg_match("/^\d+$/", $version)) {
            throw new HttpException("I'm a teapot!", 418);
        }
        
        if (!@(include Manifest::$versions_directory . "/" . $version . "/autoload.php")) {
            throw new HttpException("Oops! Looks like the API version you're searching for is playing hide and seek with us. Please check if it slipped into another dimension or try a different version!", 404);
        }
    })->where(["version" => "\d+"]);
});