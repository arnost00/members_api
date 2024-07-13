<?php
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . "/controllers/middlewares.php";
require_once __DIR__ . "/endpoints/user.php";
require_once __DIR__ . "/endpoints/race.php";
require_once __DIR__ . "/endpoints/debug.php";
require_once __DIR__ . "/endpoints/general.php";

use Pecee\SimpleRouter\SimpleRouter as Router;
use Pecee\SimpleRouter\Exceptions\HttpException;

use Endpoints\User;
use Endpoints\Race;
use Endpoints\Debug;
use Endpoints\General;
use Controllers\LoaderMiddleware;

use Core\Clubs;

Router::form("/", function () {
    return "<h1>Greetings, traveller!</h1>";
});

General::init();

Router::group(["prefix" => "/{clubname}", "preflight" => true], function ($clubname = null) {
    // prevent load on boot
    if ($clubname !== null) {
        // whitelist only alphanumeric chars
        if (!preg_match("/^\w+$/", $clubname)) {
            throw new HttpException("The club you're looking for is as real as a teapot handling coffee. Try a different name!", 418);
            return;
        }

        $result = Clubs::info($clubname);

        if ($result === null) {
            throw new HttpException("Club not found. Attempting to join a non-existent club?", 404);
            return;
        }

        request()->current = (object)$result;

        // use @ to suppress errors when club is not found
        if (@request()->current->is_release === false) {
            request()->debug = true;
        }
    }
    
    Router::group(["middleware" => LoaderMiddleware::class], function () {
        Router::form("/", function ($clubname) {
            return "<h1>Welcome, traveller!</h1><p>You have just landed on the REST APIv3 of <b>" . $clubname . "</b>.</p>\n<pre>" . json_encode(request()->current, JSON_PRETTY_PRINT) . "</pre>";
        });
        
        User::init();
        Race::init();
        Debug::init();
    });
});
