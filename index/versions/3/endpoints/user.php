<?php

namespace ApiTwo;

use Pecee\SimpleRouter\SimpleRouter as Router;

require_once __DIR__ . "/../boilerplate/middlewares.php";
require_once __DIR__ . "/../boilerplate/session.php";
require_once __DIR__ . "/../boilerplate/database.php";
require_once __DIR__ . "/../boilerplate/endpoint.php";
require_once __DIR__ . "/../boilerplate/config.php";

use Core\ApiException;

class User implements Endpoint {
    public static function init(): void {
        Router::partialGroup("/user", function () {
            Router::group(["middleware" => AuthRequired::class], function () {
                $user_id = ["user_id" => "[0-9]+"];

                Router::get("/{user_id}", [static::class, "detail"])->where($user_id);
                Router::get("/{user_id}/managing", [static::class, "managing"])->where($user_id);
                Router::get("/{user_id}/races", [static::class, "user_races"])->where($user_id);
                Router::get("/{user_id}/devices", [static::class, "user_devices"])->where($user_id);
                Router::post("/{user_id}/notify", [static::class, "user_send_notify"])->where($user_id);
                Router::get("/{user_id}/profile", [static::class, "user_profile"])->where($user_id);
                Router::post("/{user_id}/profile", [static::class, "user_profile_update"])->where($user_id);
                Router::get("/{user_id}/policies", [static::class, "user_policies"])->where($user_id);

                Router::get("/device/{device}", [static::class, "user_device"]);
                Router::delete("/device/{device}", [static::class, "user_device_delete"]);

                // current user
                Router::get("/", [static::class, "user_profile"]); // @deprecated
                Router::post("/", [static::class, "user_profile_update"]); // @deprecated
                Router::get("/managing", [static::class, "my_managing"]);
                Router::get("/profile", [static::class, "my_profile"]);
                Router::post("/profile", [static::class, "my_profile_update"]);
                Router::get("/notify", [static::class, "notify"]);
                Router::post("/notify", [static::class, "notify_update"]);
                Router::get("/devices", [static::class, "my_devices"]);
                Router::get("/policies", [static::class, "my_policies"]);

                Router::get("/list", [static::class, "list"]);
                Router::get("/send_notify", [static::class, "send_notify_everyone"]);
                Router::get("/statistics", [static::class, "statistics"]);
            });
        });
    }

    public static function detail($user_id) {
        $result = Database::fetch_assoc(
            "SELECT 
                id AS user_id,
                jmeno AS name,
                prijmeni AS surname,
                sort_name,
                reg,
                si_chip,
                chief_id,
                chief_pay
            FROM `" . Tables::$TBL_USER . "`
            WHERE id = ?",
            $user_id
        );

        if ($result === null) {
            throw new ApiException("User not found.", 404);
        }

        response()->json($result);
    }

    public static function my_policies() {
        response()->json([
            "policy_adm" => Session::$policy_adm,
            "policy_adm_small" => Session::$policy_sadm,
            "policy_news" => Session::$policy_news,
            "policy_regs" => Session::$policy_regs,
            "policy_fin" => Session::$policy_fin,
            "policy_mng_big" => Session::$policy_mng_big,
            "policy_mng_small" => Session::$policy_mng_small,
        ]);
    }

    public static function policies($user_id) {
        throw new ApiException("Not implemented yet. Sorry!", 404);
    }


    public static function my_managing() {
        return static::managing(Session::$user_id);
    }

    public static function managing($user_id) {
        $result = Database::fetch_assoc_all(
            "SELECT
                id AS user_id,
                jmeno AS name,
                prijmeni AS surname,
                reg,
                si_chip,
                chief_id,
                chief_pay
            FROM `" . Tables::$TBL_USER . "`
            WHERE `id` = ? OR `chief_id` = ?",
            $user_id,
            $user_id
        );

        response()->json($result);
    }

    public static function my_profile() {
        return static::user_profile(Session::$user_id);
    }

    public static function user_profile($user_id) {
        $access = Session::$policy_mng_big || Session::$policy_sadm || SessionUtils::is_managing_this_user($user_id);

        if (!$access) {
            throw new ApiException("You have to be either admin, big manager, manage this user or yourself to access profile.", 403);
            return;
        }

        $output = Database::fetch_assoc("SELECT * FROM `" . Tables::$TBL_USER . "` WHERE `id` = ?", $user_id);

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

            "reg" => $output["reg"],
            "si_chip"   => $output["si_chip"],

            "chief_id"      => $output["chief_id"],
            "chief_pay"     => $output["chief_pay"],

            "licence_ob"    => $output["lic"], // licence ob
            "licence_lob"   => $output["lic_lob"], // licence lob
            "licence_mtbo"  => $output["lic_mtbo"], // licence mtbo

            "is_hidden"     => $output["hidden"] != "0",
            "is_entry_locked" => $output["entry_locked"] != "0",
        ]);
    }

    public static function my_profile_update() {
        return static::user_profile_update(Session::$user_id);
    }

    public static function user_profile_update($user_id) {
        $access = Session::$policy_mng_big || Session::$policy_sadm;

        if (!$access && !SessionUtils::is_managing_this_user($user_id)) {
            throw new ApiException("You have to be either admin, big manager, manage this user or yourself to update profile.", 403);
            return;
        }

        $input = new Input();
        $input->add("name", "jmeno", access: $access);
        $input->add("surname", "prijmeni", access: $access);
        $input->add("gender", "poh", access: $access);
        $input->add("birth_date", "datum", access: $access, filter: Input::$FILTER_ISO_DATE);
        $input->add("birth_number", "rc", access: $access, filter: Input::$FILTER_UINT);
        $input->add("nationality", "narodnost", access: $access);
        $input->add("reg", filter: Input::$FILTER_UINT, access: $access, nullable: true);
        $input->add("is_hidden", "hidden", permission: Session::$MASK_SADM, filter: Input::$FILTER_BOOL);

        $input->add("address", "adresa", nullable: true);
        $input->add("city", "mesto", nullable: true);
        $input->add("email", "email", filter: FILTER_VALIDATE_EMAIL, nullable: true);
        $input->add("postal_code", "psc", filter: Input::$FILTER_UINT, nullable: true);
        $input->add("phone", "tel_mobil", filter: Input::$FILTER_PHONE, nullable: true);
        $input->add("phone_home", "tel_domu", filter: Input::$FILTER_PHONE, nullable: true);
        $input->add("phone_work", "tel_zam", filter: Input::$FILTER_PHONE, nullable: true);
        $input->add("si_chip", filter: Input::$FILTER_UINT, nullable: true);
        $input->add("licence_ob", "lic");
        $input->add("licence_lob", "lic_lob");
        $input->add("licence_mtbo", "lic_mtbo");
        $input = $input->collect();

        if (!$input) {
            response()->json([
                "pushed" => [],
            ]);
            return;
        }

        $name = isset($input["jmeno"]) ? $input["jmeno"] : Database::fetch_assoc("SELECT `jmeno` FROM `" . Tables::$TBL_USER . "` WHERE `id` = ?", $user_id)["jmeno"];
        $surname = isset($input["prijmeni"]) ? $input["prijmeni"] : Database::fetch_assoc("SELECT `prijmeni` FROM `" . Tables::$TBL_USER . "` WHERE `id` = ?", $user_id)["prijmeni"];

        // generate `sort_name` when `jmeno` or `prijmeni` is changed
        if (isset($input["jmeno"]) || isset($input["prijmeni"])) {
            $input["sort_name"] = "$name $surname";
        }

        Database::update(Tables::$TBL_USER, $input, "WHERE id = ?", $user_id);
        ModifyLog::edit(Tables::$TBL_USER, "$name $surname: user_id = $user_id");

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
        $input->add("email", filter: FILTER_VALIDATE_EMAIL, nullable: true);
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

        if (!$input) {
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

        // use array_key_exists as email is nullable
        if (array_key_exists("email", $input) && $input["email"] === null) {
            // if email is null then use default email and disable email notifications
            $input["email"] = $defaults["email"];
            $input["notify_type"] &= ~1;
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

        if (Database::fetch_assoc("SELECT 1 FROM `" . Tables::$TBL_MAILINFO . "` WHERE id_user = ?", Session::$user_id) === null) {
            $defaults = static::__default_notify_values();
            $defaults["id_user"] = Session::$user_id;

            Database::insert(Tables::$TBL_MAILINFO, $defaults);
        }

        Database::update(Tables::$TBL_MAILINFO, $input, "WHERE id_user = ?", Session::$user_id);

        response()->json([
            "pushed" => array_keys($input),
        ]);
    }

    private static function __default_notify_values() {
        $output = Database::fetch_assoc("SELECT `email` FROM " . Tables::$TBL_USER . " WHERE `id` = ? LIMIT 1", Session::$user_id);

        return [
            "email" => $output["email"] ?? "",
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

    public static function list() {
        $result = Database::fetch_assoc_all(
            "SELECT
                id AS user_id,
                prijmeni AS surname,
                jmeno AS name,
                sort_name,
                si_chip,
                reg,
                chief_id,
                chief_pay
            FROM `" . Tables::$TBL_USER . "`
            WHERE hidden = 0
            ORDER BY prijmeni ASC"
        );

        response()->json($result);
    }

    public static function user_races($user_id) {
        $result = Database::fetch_assoc_all(
            "SELECT
                race.id AS race_id,
                race.nazev AS name,
                zavxus.kat AS category
            FROM `" . Tables::$TBL_ZAVXUS . "` AS zavxus
            LEFT JOIN `" . Tables::$TBL_RACE . "` AS race ON zavxus.id_zavod = race.id
            WHERE id_user = ?
            ORDER BY race.datum DESC",
            $user_id
        );

        response()->json($result);
    }

    public static function my_devices() {
        return static::user_devices(Session::$user_id);
    }

    public static function user_devices($user_id) {
        if (Session::$user_id != $user_id && !Session::$policy_mng_big) {
            throw new ApiException("You must be at least big manager or the user to use this endpoint.", 403);
        }

        $result = Database::fetch_assoc_all(
            "SELECT
                device,
                device_name,
                fcm_token_timestamp,
                fcm_token != '' AS fcm_status,
                app_last_opened
            FROM `" . Tables::$TBL_TOKENS . "`
            WHERE user_id = ?
            ORDER BY app_last_opened DESC",
            $user_id
        );

        response()->json($result);
    }

    public static function user_device($device) {
        if (!Session::$policy_mng_big) {
            $result = Database::fetch_assoc("SELECT user_id FROM `" . Tables::$TBL_TOKENS . "` WHERE device = ?", $device);

            if ($result === null || Session::$user_id != $result["user_id"]) {
                throw new ApiException("You must be at least big manager or the user to use this endpoint.", 403);
            }
        }

        $result = Database::fetch_assoc(
            "SELECT
                device,
                device_name,
                fcm_token_timestamp,
                fcm_token != '' AS fcm_status,
                app_last_opened
            FROM `" . Tables::$TBL_TOKENS . "`
            WHERE `device` = ?",
            $device,
        );

        response()->json($result);
    }

    public static function user_device_delete($device) {
        if (!Session::$policy_mng_big) {
            $result = Database::fetch_assoc("SELECT user_id FROM `" . Tables::$TBL_TOKENS . "` WHERE device = ?", $device);

            if ($result === null || Session::$user_id != $result["user_id"]) {
                throw new ApiException("You must be at least big manager or the user to use this endpoint.", 403);
            }
        }

        Database::query("DELETE FROM `" . Tables::$TBL_TOKENS . "` WHERE device = ?", $device);
    }

    public static function user_send_notify($user_id) {
        if (!Session::$policy_mng_big) {
            throw new ApiException("You must be at least big manager to use this endpoint.", 403);
            return;
        }

        $title = Input::key("title");
        $body = Input::key("body");
        $image = Input::key("image", required: false, nullable: true, filter: FILTER_VALIDATE_URL);
        $device = Input::key("device", required: false, nullable: true, filter: Input::$FILTER_DEVICE);

        if ($device === null) {
            $tokens = Database::fetch_assoc_all("SELECT fcm_token FROM `" . Tables::$TBL_TOKENS . "` WHERE user_id = ? AND fcm_token != ''", $user_id);
        } else {
            $tokens = Database::fetch_assoc_all("SELECT fcm_token FROM `" . Tables::$TBL_TOKENS . "` WHERE device = ? AND fcm_token != ''", $device);
        }

        if ($tokens === null) {
            throw new ApiException("This device has not notifications activated.", 404);
            return;
        }

        $result = [];

        foreach ($tokens as ["fcm_token" => $token]) {
            $content = new NotifyContent($title, $body);
            $content->token($token);
            $content->image($image);

            $result[] = Notifications::send($content->export());
        }

        response()->json($result);
    }

    public static function send_notify_everyone() {
        if (!Session::$policy_mng_big) {
            throw new ApiException("You must be at least big manager to use this endpoint.", 403);
            return;
        }

        $title = Input::key("title");
        $body = Input::key("body");
        $image = Input::key("image", required: false, nullable: true, filter: FILTER_VALIDATE_URL);

        $content = new NotifyContent($title, $body);
        $content->topic(Session::$clubname);
        $content->image($image);

        $result = Notifications::send($content->export());

        response()->json($result);
    }

    public static function statistics() {
        if (!Session::$policy_mng_big) {
            throw new ApiException("You must be at least big manager to use this endpoint.", 403);
            return;
        }

        $result = Database::fetch_assoc_all(
            "SELECT
                user.id AS user_id,
                user.jmeno AS name,
                user.prijmeni AS surname,
                user.sort_name,
                COUNT(tokens.user_id) AS device_count,
                CAST(SUM(CASE WHEN tokens.fcm_token != '' THEN 1 ELSE 0 END) AS UNSIGNED) AS fcm_count
            FROM `" . Tables::$TBL_USER . "` AS user
            LEFT JOIN `" . Tables::$TBL_TOKENS . "` AS tokens ON user.id = tokens.user_id
            WHERE user.hidden = 0
            GROUP BY user.id
            ORDER BY prijmeni ASC
            "
        );

        response()->json($result);
    }
}
