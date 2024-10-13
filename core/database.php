<?php

namespace Core;

require_once __DIR__ . "/api.php";

use Core\Api;

class Database
{
    private $server;
    private $username;
    private $password;
    private $database;

    private $mysqli;

    function __construct()
    {
        $this->server = Api::config()->g_dbserver;
        $this->username = Api::config()->g_dbuser;
        $this->password = Api::config()->g_dbpass;
        $this->database = Api::config()->g_dbname;

        $this->connect();
    }

    function connect()
    {
        $this->mysqli = new \mysqli($this->server, $this->username, $this->password, $this->database);

        if ($this->mysqli->connect_errno) {
            throw new ApiException("database connect: " . $this->mysqli->connect_error, 500);
            return;
        }
        $this->query("SET CHARACTER SET UTF8");
    }

    function close()
    {
        $this->mysqli->close();
    }

    function query($query, ...$params)
    {
        $types = $this->guess_types_from_params($params);

        try {
            $prepared = $this->mysqli->prepare($query);

            if ($prepared === false) {
                throw new ApiException("database query: " . $this->mysqli->error, 500);
                return;
            }

            if (count($params) > 0) {
                $prepared->bind_param($types, ...$params);
            }

            $prepared->execute();

            $output = $prepared->get_result();
            
            $prepared->close();
        } catch (\Throwable $error) {
            throw new ApiException("database query: " . $error->getMessage(), 500);
            return;
        }

        return $output;
    }

    function fetch_assoc($query, ...$params)
    {
        return $this->query($query, ...$params)->fetch_assoc();
    }

    private function guess_types_from_params($params)
    {
        return join("", array_map(function ($value) {
            switch (gettype($value)) {
                case "boolean":
                case "integer":
                    return "i";
                case "double":
                    return "d";
                case "string":
                default:
                    return "s";
            }
        }, $params));
    }
}
