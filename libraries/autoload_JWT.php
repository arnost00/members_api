<?php

require_once __DIR__ . "/JWT/JWT.php";
require_once __DIR__ . "/JWT/JWTException.php";

// use Jwt\JWT;

// This JWT Library is from
// https://github.com/adhocore/php-jwt
// by @adhocore

// Thought, I had to make some changes to make it work perfectly:
// - add ValidatesJWT.php import to JWT.php
// - comment out ValidateKid and expiration check
