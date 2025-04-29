<?php

namespace ApiTwo;

use Pecee\Http\middleware\IMiddleware;
use Pecee\Http\Request;

require_once \Manifest::$libraries_directory . "/autoload_JWT.php";
require_once __DIR__ . "/session.php";
require_once __DIR__ . "/clubs.php";

use Core\ApiException;
use Jwt\JWTException;

class AuthRequired implements IMiddleware {
    public function handle(Request $request): void {
        // ignore if it is preflight request
        if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
            return;
        }

        if (Session::$is_logged_in) {
            return;
        }

        $token = Session::get_access_token();

        try {
            $array = Session::jwt()->decode($token);
        } catch (JWTException $error) {
            throw new ApiException($error->getMessage(), 401);
        }

        Session::login_from_token($array);

        if (Session::$is_user_verified) {
            return;
        }

        $output = Database::fetch_assoc("SELECT 1 FROM `" . Tables::$TBL_TOKENS . "` WHERE device = ?", Session::$device);

        if ($output === null) {
            throw new ApiException("The device was removed. Please login again.", 401);
        }

        $output = Database::fetch_assoc("SELECT locked FROM `" . Tables::$TBL_ACCOUNT . "` WHERE id_users = ?", Session::$user_id);

        if ($output === null) {
            throw new ApiException("The account does not exists.", 403);
        }

        if ($output["locked"]) {
            throw new ApiException("You account is locked.", 403);
        }

        Session::$is_user_verified = true;
    }
}

class TokenRequired implements IMiddleware {
    public function handle(Request $request): void {
        // ignore if it is preflight request
        if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
            return;
        }

        if (Session::$is_logged_in) {
            return;
        }

        $token = Session::get_access_token();

        try {
            $array = Session::jwt()->decode($token, false);
        } catch (JWTException $error) {
            throw new ApiException($error->getMessage(), 401);
        }

        Session::login_from_token($array);
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
    }
}
