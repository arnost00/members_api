<?php

namespace Controllers;

use Pecee\Http\middleware\IMiddleware;
use Pecee\Http\Request;
use Manifest\Manifest;

require_once Manifest::$core_directory . "/api.php";
require_once Manifest::$core_directory . "/clubs.php";
require_once __DIR__ . "/../controllers/holders.php";

use Core\Api;
use Core\Clubs;
use Controllers\Tables;
use Controllers\Policies;

class LoaderMiddleware implements IMiddleware
{
    // loads club configurations into the enviroment
    public function handle(Request $request): void
    {
        Clubs::import($request->current->clubname);

        // init tables and policies
        Tables::init();
        Policies::init();
    }
}

class RequireTokenMiddleware implements IMiddleware
{
    // checks for token validity
    public function handle(Request $request): void
    {
        // ignore if it is preflight
        if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
            return;
        }

        $request->user_id = (int)Api::token()->require_user_id();
    }
}