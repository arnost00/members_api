<?php

namespace Core;

use Pecee\Http\middleware\IMiddleware;
use Pecee\Http\Request;

class RequireAdminMiddleware implements IMiddleware
{
    // checks for admin credentials
    public function handle(Request $request): void
    {
        $username = @$_SERVER["PHP_AUTH_USER"];
        $password = @$_SERVER["PHP_AUTH_PW"];
        
        $user_agent = $_SERVER["HTTP_USER_AGENT"];

        if (!isset($username)) {
            response()->auth("Admin");
            die("401 Unauthorized. Did not found credentials.");
        }
        
        if ($username !== "admin" || $password !== md5($user_agent)) {
            response()->auth("Admin");
            die("401 Unauthorized. Username or password is incorrect.");
        }

        $request->$username = $username;
        $request->$password = $password;
    }
}
