<?php

namespace Endpoints;

use Pecee\SimpleRouter\SimpleRouter as Router;
use Pecee\SimpleRouter\Exceptions\HttpException;

use Core\Api;
use Core\Endpoint;

use Controllers\RequireTokenMiddleware;

class User implements Endpoint
{
    public static function init(): void {
        Router::group(["prefix" => "/user"], function () {
            $user_id = ["user_id" => "[0-9]+"];

            Router::form("/login", [static::class, "login"]);
            Router::form("/{user_id}", [static::class, "show"])->where($user_id);

            Router::group(["middleware" => RequireTokenMiddleware::class], function () use ($user_id) {
                Router::form("/{user_id}/managing", [static::class, "managing"])->where($user_id);
                Router::form("/", [static::class, "data"]);
                Router::form("/update", [static::class, "update"]);
            });
        });
    }

    public static function login()
    {
        $username = input()->find("username");
        $password = input()->find("password");

        if (!$username || !$password) {
            throw new HttpException("Keys username or password are not set.", 400);
        }

        $output = Api::database()->fetch_assoc("SELECT `id_users`, `login`, `heslo`, `locked` FROM `" . TBL_ACCOUNT . "` WHERE `login` = ? LIMIT 1", $username->getValue());

        if (!$output) {
            throw new HttpException("Username does not exists.", 401);
        }

        if (!password_verify(md5($password->getValue()), $output["heslo"])) {
            throw new HttpException("Wrong password.", 401);
        }

        if ($output["locked"]) {
            throw new HttpException("Your account is locked.", 401);
        }

        Api::database()->query("UPDATE `" . TBL_ACCOUNT . "` SET `last_visit` = ? WHERE `id_users` = ?", time(), $output["id_users"]);

        response()->json([
            "token" => Api::token()->encode([
                "user_id" => $output["id_users"],
            ])
        ]);
    }

    public static function show($user_id) {
        $output = Api::database()->fetch_assoc("SELECT `id`, `jmeno`, `prijmeni`, `reg`, `si_chip` FROM `" . TBL_USER . "` WHERE `id` = ?", $user_id);

        $result = [
            "user_id" => $output["id"],
            "name" => $output["jmeno"],
            "surname" => $output["prijmeni"],

            "registration_number" => $output["reg"],
            "chip_number" => $output["si_chip"],

            "chief_id" => @$output["chief_id"], // allow null
            "chief_pay" => @$output["chief_pay"], // allow null
        ];

        response()->json($result);
    }

    public static function managing($user_id)
    {
        // TODO: unnecessary selects
        $output = Api::database()->query("SELECT `id`, `jmeno`, `prijmeni`, `reg`, `si_chip` FROM `" . TBL_USER . "` WHERE `id` = ? OR `chief_id` = ?", request()->user_id, $user_id);

        $result = [];
        while ($user = $output->fetch_assoc()) {
            $result[] = [
                "user_id" => $user["id"],
                "name" => $user["jmeno"],
                "surname" => $user["prijmeni"],

                "registration_number" => $user["reg"],
                "chip_number" => $user["si_chip"],

                "chief_id" => @$user["chief_id"], // allow null
                "chief_pay" => @$user["chief_pay"], // allow null
            ];
        }

        response()->json($result);
    }

    public static function data()
    {
        $output = Api::database()->fetch_assoc("SELECT * FROM `" . TBL_USER . "` WHERE `id` = ?", request()->user_id);

        response()->json([
            "user_id" => $output["id"],
            "name" => $output["jmeno"],
            "surname" => $output["prijmeni"],
            "sort_name" => $output["sort_name"],
            "email" => $output["email"],
            "gender" => $output["poh"],
            "birth_date" => $output["datum"],
            "birth_number" => $output["rc"],
            "nationality" => $output["narodnost"],
            "address" => $output["adresa"],
            "city" => $output["mesto"],
            "postal_code" => $output["psc"],
            "phone" => $output["tel_mobil"],
            "phone_home" => $output["tel_domu"],
            "phone_work" => $output["tel_zam"],

            "registration_number" => $output["reg"],
            "chip_number" => $output["si_chip"],

            "chief_id" => $output["chief_id"],
            "chief_pay" => $output["chief_pay"],

            "licence_ob" => $output["lic"],
            "licence_lob" => $output["lic_lob"],
            "licence_mtbo" => $output["lic_mtbo"],

            "is_hidden" => $output["hidden"] != "0",
            "is_entry_locked" => $output["entry_locked"] != "0",
        ]);
    }

    function update() {
        $translate = [
            "name" => "jmeno",
            "surname" => "prijmeni",
            "email" => "email",
            "gender" => "poh",
            "birth_date" => "datum",
            "birth_number" => "rc",
            "nationality" => "narodnost",
            "address" => "adresa",
            "city" => "mesto",
            "postal_code" => "psc",
            "phone" => "tel_mobil",
            "phone_home" => "tel_domu",
            "phone_work" => "tel_zam",

            "registration_number" => "reg",
            "chip_number" => "si_chip",

            "licence_ob" => "lic",
            "licence_lob" => "lic_lob",
            "licence_mtbo" => "lic_mtbo",
            
            // "is_hidden" => "hidden",
        ];

        $push_data = [];

        foreach (input()->all() as $key => $value) {
            $push_key = @$translate[$key];
            
            if (isset($push_key)) {
                $push_data[$push_key] = $value;
            }
        }

        if (count($push_data) > 0) {
            // generate "sort_name" if "name" or "surname" is changed
            $name = input()->find("name");
            $surname = input()->find("surname");
            if ($name || $surname) {
                // fetch "name" if not provided
                if ($name) {
                    $name = $name->getValue();
                } else {
                    $name = Api::database()->fetch_assoc("SELECT `jmeno` FROM `" . TBL_USER . "` WHERE `id` = ?", request()->user_id)["jmeno"];
                }
                // fetch "surname" if not provided
                if ($surname) {
                    $surname = $surname->getValue();
                } else {
                    $surname = Api::database()->fetch_assoc("SELECT `prijmeni` FROM `" . TBL_USER . "` WHERE `id` = ?", request()->user_id)["prijmeni"];
                }
                // construct "sort_name"
                $push_data["sort_name"] = $name . " " . $surname;
            }

            // construct a sql update query
            $query = "UPDATE `" . TBL_USER . "` SET ";
            $query.= join(", ", array_map(function ($key) {return "`" . $key . "` = ?";}, array_keys($push_data)));
            $query.= " WHERE id=?";

            Api::database()->query($query, ...[...array_values($push_data), request()->user_id]);
        }
        
        response()->json([]);
    }
}
