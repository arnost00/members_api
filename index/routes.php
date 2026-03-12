<?php

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: *, Authorization");
header("Access-Control-Allow-Methods: *");

require_once Manifest::$libraries_directory . "/autoload_Pecee.php";
require_once Manifest::$core_directory . "/exceptions.php";

use Pecee\SimpleRouter\SimpleRouter as Router;

use Core\ApiCrashReport;
use Core\ApiException;

ApiCrashReport::init();

// fallback values
request()->debug = false;
request()->version = null;

Router::group(["prefix" => "/api/"], function () {
    Router::form("/", function () {
        return "<h1>Members API</h1><p style='color:red;'>NON-PRODUCTION MODE</p>";
    });

    Router::form("/error", function () {
        throw new ApiException("As you wish!", 418);
    });

    Router::partialGroup("/{version}", function ($version) {
        // allow only digits
        // double check the version to make sure it can't be exploited
        if (!preg_match("/^\d+$/", $version)) {
            throw new ApiException("I'm a teapot!", 418);
        }

        if (!@(include Manifest::$versions_directory . "/" . $version . "/autoload.php")) {
            throw new ApiException("Unable to load this API version.", 404);
        }
    })->where(["version" => "\d+"]);

    Router::partialGroup("/members", function () {
        if (!@(include Manifest::$versions_directory . "/members/autoload.php")) {
            throw new ApiException("Oops! Looks like the members autoload file is not there.", 500);
        }
    });

    Router::partialGroup("/logging", function () {
        include __DIR__ . "/logging.php";
    });
});
