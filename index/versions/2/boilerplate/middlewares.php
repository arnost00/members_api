<?php

namespace ApiTwo;

use Pecee\Http\middleware\IMiddleware;
use Pecee\Http\Request;

require_once __DIR__ . "/session.php";
require_once __DIR__ . "/clubs.php";

use Core\ApiException;

class LoginRequired implements IMiddleware {
    // checks for token validity
    public function handle(Request $request): void {
        // ignore if it is preflight
        if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
            return;
        }

        if (!Session::$is_logged_in) {
            throw new ApiException("This route requires logging in.", 401);
        }
    }
}

class ConfigLoader implements IMiddleware {
    public function handle(Request $request): void {
        // ignore if it is preflight
        if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
            return;
        }

        // whitelist only alphanumeric chars
        if (!preg_match("/^\w+$/", $request->clubname)) {
            throw new ApiException("The club you're looking for is as real as a teapot handling coffee. Try a different name!", 418);
            return;
        }

        if (!Clubs::exists($request->clubname)) {
            throw new ApiException("Club not found. Attempting to join a non-existent club?", 404);
            return;
        }

        // import club
        Clubs::import($request->clubname);

        // set debug mode
        if (Config::$g_is_release === false) {
            request()->debug = true;
        }

        // initialize session
        Session::init();
    }
}