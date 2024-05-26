<?php

namespace Controllers;

use Pecee\Http\middleware\IMiddleware;
use Pecee\Http\Request;

use Core\Api;
use Core\Clubs;

class LoaderMiddleware implements IMiddleware
{
    // loads club configurations into the enviroment
    public function handle(Request $request): void
    {
        Clubs::import($request->current->clubname);
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