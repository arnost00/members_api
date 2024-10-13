<?php

namespace ApiTwo;

use Pecee\SimpleRouter\SimpleRouter as Router;

require_once __DIR__ . "/../boilerplate/middlewares.php";
require_once __DIR__ . "/../boilerplate/session.php";
require_once __DIR__ . "/../boilerplate/database.php";
require_once __DIR__ . "/../boilerplate/endpoint.php";
require_once __DIR__ . "/../boilerplate/config.php";

use Core\ApiException;

class User implements Endpoint
{
    public static function init(): void {
        Router::partialGroup("/user", function () {
            $user_id = ["user_id" => "[0-9]+"];
            
            Router::get("/{user_id}", [static::class, "show"])->where($user_id);

            Router::group(["middleware" => LoginRequired::class], function () use ($user_id) {
                Router::get("/{user_id}/managing", [static::class, "managing"])->where($user_id);
                Router::get("/", [static::class, "data"]);
                Router::post("/", [static::class, "data_update"]);
                Router::get("/notify", [static::class, "notify"]);
                Router::post("/notify", [static::class, "notify_update"]);
            });
        });
    }

    public static function show($user_id) {
        $output = Database::fetch_assoc("SELECT `id`, `jmeno`, `prijmeni`, `reg`, `si_chip` FROM `" . Tables::$TBL_USER . "` WHERE `id` = ?", $user_id);

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
        $output = Database::query("SELECT `id`, `jmeno`, `prijmeni`, `reg`, `si_chip` FROM `" . Tables::$TBL_USER . "` WHERE `id` = ? OR `chief_id` = ?", Session::$user_id, $user_id);

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
        $output = Database::fetch_assoc("SELECT * FROM `" . Tables::$TBL_USER . "` WHERE `id` = ?", Session::$user_id);

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

    public static function data_update() {
        $input = new Input();
        $input->add("name", "jmeno", permission: Session::$MASK_MNG_SMALL);
        $input->add("surname", "prijmeni", permission: Session::$MASK_MNG_SMALL);
        $input->add("email", "email", filter: FILTER_VALIDATE_EMAIL);
        $input->add("gender", "poh", permission: Session::$MASK_MNG_SMALL);
        $input->add("birth_date", "datum", permission: Session::$MASK_MNG_SMALL);
        $input->add("birth_number", "rc", permission: Session::$MASK_MNG_SMALL);
        $input->add("nationality", "narodnost", permission: Session::$MASK_MNG_SMALL);
        $input->add("address", "adresa");
        $input->add("city", "mesto");
        $input->add("postal_code", "psc", filter: Input::$FILTER_UINT);
        $input->add("phone", "tel_mobil", filter: Input::$FILTER_PHONE);
        $input->add("phone_home", "tel_domu", filter: Input::$FILTER_PHONE);
        $input->add("phone_work", "tel_zam", filter: Input::$FILTER_PHONE);
        $input->add("registration_number", "reg", filter: Input::$FILTER_UINT, permission: Session::$MASK_MNG_SMALL);
        $input->add("chip_number", "si_chip", filter: Input::$FILTER_UINT);
        $input->add("licence_ob", "lic");
        $input->add("licence_lob", "lic_lob");
        $input->add("licence_mtbo", "lic_mtbo");
        $input->add("is_hidden", "hidden", permission: Session::$MASK_MNG_SMALL);
        $input = $input->collect();

        if (count($input) === 0) {
            response()->json([
                "pushed" => [],
            ]);
            return;
        }

        // generate `sort_name` when `jmeno` or `prijmeni` is changed
        if (isset($input["jmeno"]) || isset($input["prijmeni"])) {
            $name = isset($input["jmeno"]) ? $input["jmeno"] : Database::fetch_assoc("SELECT `jmeno` FROM `" . Tables::$TBL_USER . "` WHERE `id` = ?", Session::$user_id)["jmeno"];
            $surname = isset($input["prijmeni"]) ? $input["prijmeni"] : Database::fetch_assoc("SELECT `prijmeni` FROM `" . Tables::$TBL_USER . "` WHERE `id` = ?", Session::$user_id)["prijmeni"];

            $input["sort_name"] = $name . " " . $surname;
        }

        // construct a SQL update query
        $query = "UPDATE `" . Tables::$TBL_USER . "` SET ";
        $query.= join(", ", array_map(function ($key) {return "`" . $key . "` = ?";}, array_keys($input)));
        $query.= " WHERE `id` = ?";

        Database::query($query, ...[...array_values($input), Session::$user_id]);

        response()->json([
            "pushed" => array_keys($input),
        ]);
    }

    public static function notify() {
        $result = Database::fetch_assoc("SELECT * FROM `" . Tables::$TBL_MAILINFO . "` WHERE `id_user` = ?", Session::$user_id);

        if ($result === null) {
            $result = static::__default_notify_values();
        }
        
        function _parse_flags($value, $scheme) {
            $result = [];
            foreach ($scheme as $item) {
                $result[] = [
                    "name" => $item["nm"],
                    "id" => $item["id"],
                    "value" => (bool)($item["id"] & $value),
                ];
            }
            return $result;
        }
        
        $finances_enabled = Config::$g_enable_finances;
        
        response()->json([
            "notify_type" => _parse_flags($result["notify_type"], Enums::$g_notify_type_flag),
            "email" => $result["email"],
            
            "send_news" => (bool)$result["active_news"],
            
            // notify when race is about to expire
            "send_races" => (bool)$result["active_tf"],
            "days_before" => $result["daysbefore"],
            "days_before_min" => Config::$g_mailinfo_minimal_daysbefore,
            "days_before_max" => Config::$g_mailinfo_maximal_daysbefore,
            "race_types" => _parse_flags($result["type"], Enums::$g_racetype),
            "rankings" => _parse_flags($result["sub_type"], Enums::$g_zebricek),

            // notify when race is changed
            "send_changes" => (bool)$result["active_ch"],
            "send_changes_data" => _parse_flags($result["ch_data"], Enums::$g_modify_flag),

            // FINANCES ENABLED
            // notify financial status
            "send_finances" => $finances_enabled ? (bool)$result["active_fin"] : null,
            "send_finances_data" => $finances_enabled ? _parse_flags($result["fin_type"], Enums::$g_fin_mail_flag) : null,
            "financial_limit" => $finances_enabled ? $result["fin_limit"] : null,
            
            // FINANCE ONLY
            // notify when member does not have money, finance only
            "send_member_minus" => ($finances_enabled && Session::$policy_fin) ? (bool)$result["active_finf"] : null,

            // REGISTRATOR ONLY
            "send_internal_entry_expired" => Session::$policy_regs ? (bool)$result["active_rg"] : null,
        ]);
    }

    public static function notify_update() {
        $finances = Config::$g_enable_finances ? 0 : Session::$MASK_SADM;
        
        $input = new Input();
        $input->add("notify_type", filter: Input::$FILTER_UINT);
        $input->add("email", filter: FILTER_VALIDATE_EMAIL);
        $input->add("send_news", "active_news", filter: Input::$FILTER_BOOL);
        // notify when race is about to expire
        $input->add("send_races", "active_tf", filter: Input::$FILTER_BOOL);
        $input->add("days_before", "daysbefore", filter: Input::$FILTER_UINT);
        $input->add("race_types", "type", filter: Input::$FILTER_UINT);
        $input->add("rankings", "sub_type", filter: Input::$FILTER_UINT);
        // notify when race is changed
        $input->add("send_changes", "active_ch", filter: Input::$FILTER_BOOL);
        $input->add("send_changes_data", "ch_data", filter: Input::$FILTER_UINT);
        // FINANCES ENABLED
        // notify financial status
        $input->add("send_finances", "active_fin", permission: $finances, filter: Input::$FILTER_BOOL);
        $input->add("send_finances_data", "fin_type", permission: $finances, filter: Input::$FILTER_UINT);
        $input->add("financial_limit", "fin_limit", permission: $finances, filter: Input::$FILTER_UINT);
        // FINANCE ONLY
        // notify when member does not have money, finance only
        $input->add("send_member_minus", "active_finf", permission: Config::$g_enable_finances ? Session::$MASK_FIN : Session::$MASK_SADM, filter: Input::$FILTER_BOOL);
        // REGISTRATOR ONLY
        $input->add("send_internal_entry_expired", "active_rg", permission: Session::$MASK_REGS, filter: Input::$FILTER_BOOL);

        $input = $input->collect();

        if (count($input) === 0) {
            response()->json([
                "pushed" => [],
            ]);
            return;
        }

        $defaults = static::__default_notify_values();

        if (isset($input["daysbefore"])) {
            if ($input["daysbefore"] < Config::$g_mailinfo_minimal_daysbefore) {
                $input["daysbefore"] = Config::$g_mailinfo_minimal_daysbefore;
            }
            if ($input["daysbefore"] > Config::$g_mailinfo_maximal_daysbefore) {
                $input["daysbefore"] = Config::$g_mailinfo_maximal_daysbefore;
            }
        }

        if (isset($input["active_tf"]) && !$input["active_tf"]) {
            // if races are not selected, clear selected races options
            $input["daysbefore"] = $defaults["daysbefore"];
            $input["type"] = $defaults["type"];
            $input["sub_type"] = $defaults["sub_type"];
        }

        if (isset($input["active_ch"]) && !$input["active_ch"]) {
            // if changes are not selected, clear selected changes options
            $input["ch_data"] = $defaults["ch_data"];
        }
        
        if (isset($input["active_fin"]) && !$input["active_fin"]) {
            // if finances are not selected, clear selected finance options
            $input["fin_type"] = $defaults["fin_type"];
            $input["fin_limit"] = $defaults["fin_limit"];
        }

        // insert when user id does not exists, otherwise update
        if (Database::fetch_assoc("SELECT `id` FROM " . Tables::$TBL_MAILINFO . " WHERE `id_user` = ?", Session::$user_id) === null) {
            // row does not exist, to make an insert, $input must contain every value
            $input = array_merge(static::__default_notify_values(), $input);

            $query = "INSERT INTO `" . Tables::$TBL_MAILINFO . "` (`email`, `daysbefore`, `type`, `sub_type`, `active_tf`, `active_ch`, `ch_data`, `active_rg`, `active_fin`, `active_finf`, `fin_type`, `fin_limit`, `active_news`, `id_user`) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            Database::query($query, $input["email"], $input["daysbefore"], $input["type"], $input["sub_type"], $input["active_tf"], $input["active_ch"], $input["ch_data"], $input["active_rg"], $input["active_fin"], $input["active_finf"], $input["fin_type"], $input["fin_limit"], $input["active_news"], Session::$user_id);
        } else {
            // input only provided values
            $query = "UPDATE `" . Tables::$TBL_MAILINFO . "` SET ";
            $query.= join(", ", array_map(function ($key) {return "`" . $key . "` = ?";}, array_keys($input)));
            $query.= " WHERE `id_user` = ?";
            
            Database::query($query, ...[...array_values($input), Session::$user_id]);
        }

        response()->json([
            "pushed" => array_keys($input),
        ]);
    }

    private static function __default_notify_values() {
        $output = Database::fetch_assoc("SELECT `email` FROM " . Tables::$TBL_USER . " WHERE `id` = ? LIMIT 1", Session::$user_id);
        
        // use empty string when query fails
        $email = $output === null ? "" : $output["email"];

        return [
            "email" => $email,
            "daysbefore" => 3,
            "type" => 0,
            "sub_type" => 0,
            "active_tf" => 0,
            "active_ch" => 0,
            "ch_data" => 0,
            "active_rg" => 0,
            "active_fin" => 0,
            "active_finf" => 0,
            "fin_type" => 0,
            "fin_limit" => 0,
            "active_news" => 0,
            "notify_type" => 1,
        ];
    }
}
