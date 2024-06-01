<?php

require_once __DIR__ . "/controllers/autoload.php";
require_once __DIR__ . "/endpoints/autoload.php";

use Pecee\SimpleRouter\SimpleRouter as Router;
use Pecee\SimpleRouter\Exceptions\HttpException;

use Endpoints\User;
use Endpoints\Race;
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
            throw new HttpException("I'm a teapot. How can I know that club?", 418);
            return;
        }

        request()->current = (object)Clubs::info($clubname);

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
    });
});
