<?php
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . "/controllers/middlewares.php";
require_once __DIR__ . "/endpoints/user.php";
require_once __DIR__ . "/endpoints/race.php";
require_once __DIR__ . "/endpoints/debug.php";

use Pecee\SimpleRouter\SimpleRouter as Router;

use Endpoints\User;
use Endpoints\Race;
use Endpoints\Debug;
use Controllers\LoaderMiddleware;

use Core\Clubs;
use Core\ApiException;

Router::form("/", function () {
    return "<h1>API version: 1</h1>";
});

Router::form("/clubs", function () {
    response()->json(Clubs::list());
})->setPreflightRequestsEnabled(true);

Router::partialGroup("/{clubname}", function ($clubname) {
    // whitelist only alphanumeric chars
    if (!preg_match("/^\w+$/", $clubname)) {
        var_dump($clubname);
        throw new ApiException("The club you're looking for is as real as a teapot handling coffee. Try a different name!", 418);
        return;
    }

    $result = Clubs::info($clubname);

    if ($result === null) {
        throw new ApiException("Club not found. Attempting to join a non-existent club?", 404);
        return;
    }

    request()->current = (object)$result;

    if (request()->current->is_release === false) {
        request()->debug = true;
    }

    Router::group(["middleware" => LoaderMiddleware::class], function () {
        Router::form("/", function ($clubname) {
            return "<h1>Welcome, traveller!</h1><p>You have just landed on the REST APIv3 of <b>" . $clubname . "</b>.</p>\n<pre>" . json_encode(request()->current, JSON_PRETTY_PRINT) . "</pre>";
        });

        User::init();
        Race::init();
        Debug::init();
    });
    // clubname must not be "clubs" to allow /clubs route
})->where(["clubname" => "(?!clubs)\w+"])->setPreflightRequestsEnabled(true);
