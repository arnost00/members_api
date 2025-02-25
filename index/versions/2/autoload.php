<?php

namespace ApiTwo;

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

// this is the magic that solves output already started error
ob_start();
register_shutdown_function("ob_end_flush");

require_once __DIR__ . "/boilerplate/middlewares.php";
require_once __DIR__ . "/boilerplate/input.php";

require_once __DIR__ . "/endpoints/user.php";
require_once __DIR__ . "/endpoints/race.php";
require_once __DIR__ . "/endpoints/system.php";

use Pecee\SimpleRouter\SimpleRouter as Router;

Router::form("/", function () {
    return "<h1>API version: 2</h1>";
});

Router::get("/clubs", function () {
    response()->json(Clubs::list());
})->setPreflightRequestsEnabled(true);

Router::partialGroup("/{clubname}", function ($clubname) {
    // should only be used for middlewares
    request()->clubname = $clubname;

    Router::form("/", function ($clubname) {
        return "<h1>Welcome, traveler!</h1><p>You have just landed on the REST API of <b>" . $clubname . "</b>.</p>\n<pre>request()->debug = " . (request()->debug ? "true" : "false") . "</pre>";
    });

    Router::group(["middleware" => ConfigLoader::class], function () {
        User::init();
        Race::init();
        System::init();
    });

    // clubname must not be "clubs" to allow /clubs route
})->where(["clubname" => "(?!clubs)\w+"])->setPreflightRequestsEnabled(true);
