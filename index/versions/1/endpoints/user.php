<?php

namespace Endpoints;

use Pecee\SimpleRouter\SimpleRouter as Router;

require_once __DIR__ . "/../controllers/middlewares.php";
require_once __DIR__ . "/../controllers/holders.php";

require_once \Manifest::$core_directory . "/api.php";
require_once \Manifest::$core_directory . "/endpoint.php";

use Core\Api;
use Core\ApiException;
use Core\Endpoint;

use Controllers\RequireTokenMiddleware;
use Controllers\Tables;
use Controllers\Policies;

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

        if ($username === null || $password === null) {
            throw new ApiException("Keys username or password are not set.", 400);
        }

        $output = Api::database()->fetch_assoc("SELECT * FROM `" . Tables::$ACCOUNT . "` WHERE `login` = ? LIMIT 1", $username->getValue());

        if (!$output) {
            throw new ApiException("Username does not exists.", 401);
        }

        if (!password_verify(md5($password->getValue()), $output["heslo"])) {
            throw new ApiException("Wrong password.", 401);
        }

        if ($output["locked"]) {
            throw new ApiException("Your account is locked.", 401);
        }

        Api::database()->query("UPDATE `" . Tables::$ACCOUNT . "` SET `last_visit` = ? WHERE `id_users` = ?", time(), $output["id_users"]);

        response()->json([
            "token" => Api::token()->encode([
                "user_id" => $output["id_users"],
            ]),
            "policies" => [
                "policy_adm"  => $output["policy_adm"],
                "policy_news" => $output["policy_adm"] || $output["policy_news"],
                "policy_regs" => $output["policy_adm"] || $output["policy_regs"],
                "policy_fin"  => $output["policy_adm"] || $output["policy_fin"],
                "policy_mng"  => $output["policy_mng"],
            ],
        ]);
    }

    public static function show($user_id) {
        $output = Api::database()->fetch_assoc("SELECT `id`, `jmeno`, `prijmeni`, `reg`, `si_chip` FROM `" . Tables::$USER . "` WHERE `id` = ?", $user_id);

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
        $output = Api::database()->query("SELECT `id`, `jmeno`, `prijmeni`, `reg`, `si_chip` FROM `" . Tables::$USER . "` WHERE `id` = ? OR `chief_id` = ?", request()->user_id, $user_id);

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
        $output = Api::database()->fetch_assoc("SELECT * FROM `" . Tables::$USER . "` WHERE `id` = ?", request()->user_id);

        response()->json([
            "user_id"       => $output["id"],
            "name"          => $output["jmeno"],
            "surname"       => $output["prijmeni"],
            "sort_name"     => $output["sort_name"],
            "email"         => $output["email"],
            "gender"        => $output["poh"], // gender
            "birth_date"    => $output["datum"],
            "birth_number"  => $output["rc"], // birth number
            "nationality"   => $output["narodnost"],
            "address"       => $output["adresa"],
            "city"          => $output["mesto"],
            "postal_code"   => $output["psc"],
            "phone"         => $output["tel_mobil"], // personal phone number
            "phone_home"    => $output["tel_domu"], // home phone number
            "phone_work"    => $output["tel_zam"], // work phone number

            "registration_number" => $output["reg"],
            "chip_number"   => $output["si_chip"],

            "chief_id"      => $output["chief_id"],
            "chief_pay"     => $output["chief_pay"],

            "licence_ob"    => $output["lic"], // licence ob
            "licence_lob"   => $output["lic_lob"], // licence lob
            "licence_mtbo"  => $output["lic_mtbo"], // licence mtbo

            "is_hidden"     => $output["hidden"] != "0",
            "is_entry_locked" => $output["entry_locked"] != "0",
        ]);
    }

    function update() {
        /**
         * $translate = [
         *      "<api key name>" => ["<database key name>", "<can user edit>"]
         * ];
         */
        $translate = [
            "name"          => ["jmeno",        false],
            "surname"       => ["prijmeni",     false],
            "email"         => ["email",        true],
            "gender"        => ["poh",          false],
            "birth_date"    => ["datum",        false],
            "birth_number"  => ["rc",           false],
            "nationality"   => ["narodnost",    false],
            "address"       => ["adresa",       true],
            "city"          => ["mesto",        true],
            "postal_code"   => ["psc",          true],
            "phone"         => ["tel_mobil",    true],
            "phone_home"    => ["tel_domu",     true],
            "phone_work"    => ["tel_zam",      true],
            
            "registration_number" => ["reg",    false],
            "chip_number"   => ["si_chip",      true],
            
            "licence_ob"    => ["lic",          true],
            "licence_lob"   => ["lic_lob",      true],
            "licence_mtbo"  => ["lic_mtbo",     true],
            
            "is_hidden"     => ["hidden",       false],
        ];
            
        $manager_access = Policies::is_any_manager(request()->user_id);
        $push_data = [];
        foreach (input()->all() as $api_key_name => $value) {
            [$push_key, $push_access] = $translate[$api_key_name];

            if (!isset($push_key)) {
                continue;
            }

            if ($manager_access || $push_access) {
                $push_data[$push_key] = $value;
            }
        }

        if (count($push_data) === 0) {
            response()->json([]);
            return;
        }

        // generate `sort_name` when `jmeno` or `prijmeni` is changed
        $name = $push_data["jmeno"];
        $surname = $push_data["prijmeni"];
        if (isset($name) || isset($surname)) {
            if (!isset($name)) {
                $name = Api::database()->fetch_assoc("SELECT `jmeno` FROM `" . Tables::$USER . "` WHERE `id` = ?", request()->user_id)["jmeno"];
            }

            if (!isset($surname)) {
                $surname = Api::database()->fetch_assoc("SELECT `prijmeni` FROM `" . Tables::$USER . "` WHERE `id` = ?", request()->user_id)["prijmeni"];
            }

            $push_data["sort_name"] = $name . " " . $surname;
        }

        // construct a SQL update query
        $query = "UPDATE `" . Tables::$USER . "` SET ";
        $query.= join(", ", array_map(function ($key) {return "`" . $key . "` = ?";}, array_keys($push_data)));
        $query.= " WHERE id=?";

        Api::database()->query($query, ...[...array_values($push_data), request()->user_id]);

        response()->json([
            "pushed" => array_keys($push_data),
        ]);
    }
}
