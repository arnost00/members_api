<?php

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

// use instead boot manager because of club dynamic loading
$request_uri = &$_SERVER["REQUEST_URI"];
if (strpos($request_uri, "/api/club/") === 0) {
    $request_uri = "/api/0/" . substr($request_uri, strlen("/api/club/"));
}
if (strpos($request_uri, "/api/clubs") === 0) {
    $request_uri = "/api/0/clubs/";
}

// must be imported
require_once __DIR__ . "/manifest.php";

// production routes

require_once __DIR__ . "/index/routes.php";

use Pecee\SimpleRouter\SimpleRouter as Router;

Router::start();
