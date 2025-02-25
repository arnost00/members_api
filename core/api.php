<?php

namespace Core;

require_once __DIR__ . "/database.php";

use Jwt\JWT;
use Jwt\JWTException;

use Core\Database;

class Api {
    private static ?Config $_config = null;
    private static ?Database $_database = null;
    private static ?Token $_token = null;

    public static function config() {
        if (static::$_config === null) {
            static::$_config = new Config();
        }

        return static::$_config;
    }

    public static function database() {
        if (static::$_database === null) {
            static::$_database = new Database();
        }

        return static::$_database;
    }

    public static function token() {
        if (static::$_token === null) {
            static::$_token = new Token();
        }

        return static::$_token;
    }
}

class Config {
    private $data = [];

    public function __isset($name): bool {
        return array_key_exists($name, $this->data) === true;
    }

    public function __set($name, $value = null) {
        $this->data[$name] = $value;
    }

    public function __get($name) {
        return $this->data[$name] ?? null;
    }

    public function update($data) {
        $this->data += $data;
    }
}

class Token {
    public ?JWT $jwt = null;

    public function __construct() {
        $this->jwt = new JWT(base64_decode(Api::config()->g_jwt_secret_key), "HS512");
    }

    public function encode($array) {
        return $this->jwt->encode($array);
    }

    public function decode($array) {
        return $this->jwt->decode($array);
    }

    public function require_user_id() {
        $token = request()->getHeader("authorization");

        if (substr($token, 0, 7) !== "Bearer ") { // extract "Bearer "
            throw new ApiException("bearer authorization not found");
        }

        $token = substr($token, 7); // length of "Bearer "

        $user_id = $this->decode($token)["user_id"];

        if ($user_id === null) {
            throw new JWTException("user_id not found");
        }

        return $user_id;
    }
}
