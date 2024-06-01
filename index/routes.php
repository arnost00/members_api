<?php

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: *, Authorization");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");

// the json header should not be set globally
// because it is handled by the router if needed
//
// header("Content-Type: application/json");

use Core\ApiException;
use Pecee\SimpleRouter\SimpleRouter as Router;
use Pecee\SimpleRouter\Exceptions\HttpException;

use Manifest\Manifest;
use Core\ApiExceptionHandler;

// fallback values
request()->debug = false;
request()->current = null;
request()->version = null;

Router::group(["prefix" => "/api/", "exceptionHandler" => ApiExceptionHandler::class], function () {
    Router::form("/", function () {
        throw new ApiException("lolol", 400);
        return "<h1>Members API; Production version</h1>";
    });

    Router::group(["prefix" => "/{version}"], function ($version = null) {
        // early return to allow other routers to load
        if ($version === null) {
            return;
        }

        // allow only digits
        // double check the version to make sure it can't be exploited
        if (!preg_match("/^\d+$/", $version)) {
            throw new HttpException("I'm a teapot.", 418);
            return;
        }
        
        require Manifest::$versions_directory . $version . "/autoload.php";
    })->where(["version" => "\d+"]);
});