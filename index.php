<?php

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . "/libraries/autoload.php";
require_once __DIR__ . "/manifest.php";
require_once __DIR__ . "/core/autoload.php";

// production routes

require_once __DIR__ . "/index/routes.php";

use Pecee\SimpleRouter\SimpleRouter as Router;

Router::start();