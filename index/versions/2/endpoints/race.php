<?php

namespace ApiTwo;

use Pecee\SimpleRouter\SimpleRouter as Router;

require_once __DIR__ . "/../boilerplate/middlewares.php";
require_once __DIR__ . "/../boilerplate/session.php";
require_once __DIR__ . "/../boilerplate/utils.php";
require_once __DIR__ . "/../boilerplate/database.php";
require_once __DIR__ . "/../boilerplate/endpoint.php";
require_once __DIR__ . "/../boilerplate/notify.php";
require_once __DIR__ . "/../boilerplate/config.php";
require_once __DIR__ . "/../boilerplate/input.php";

use Core\ApiException;

class Race implements Endpoint
{
    public static function init(): void {
        Router::get("/races", [static::class, "races"]);
        
        Router::partialGroup("/race", function () {
            $race_id = ["race_id" => "[0-9]+"];
            $user_id = ["user_id" => "[0-9]+"];

            Router::form("/", [static::class, "warn_race_required"]);
            Router::get("/{race_id}", [static::class, "detail"])->where($race_id);
            Router::get("/{race_id}/redirect", [static::class, "redirect"])->where($race_id);

            Router::group(["middleware" => LoginRequired::class], function () use ($race_id, $user_id) {
                Router::get("/{race_id}/relations", [static::class, "relations"])->where($race_id);
                Router::post("/{race_id}/signin/{user_id}", [static::class, "signin"])->where($race_id + $user_id);
                Router::post("/{race_id}/signout/{user_id}", [static::class, "signout"])->where($race_id + $user_id);
                Router::post("/{race_id}/notify", [static::class, "notify"])->where($race_id);
            });
        });
    }

    public static function warn_race_required() {
        throw new ApiException("race_id is required.", 404);
        return;
    }

    public static function redirect($race_id) {
        // status code 302, indicates that the resource requested has been temporarily moved
        redirect(Config::$g_baseadr . "race_info_show.php?id_zav=" . $race_id);
    }

    public static function races() {
        $current_date = Utils::getCurrentDate();

        $output = Database::query("SELECT `id` FROM `" . Tables::$TBL_RACE . "` WHERE `datum` >= ? || `datum2` >= ? ORDER BY `datum`", $current_date, $current_date);

        $result = [];
        while ($race = $output->fetch_assoc()) {
            $result[] = self::__get_race_info($race["id"]);
        }

        response()->json($result);
    }

    public static function detail($race_id) {
        $result = self::__get_race_info($race_id);

        // provide information about signed in users
        $output = Database::query("SELECT zavxus.si_chip AS si_chip_temp, zavxus.*, user.* FROM `" . Tables::$TBL_ZAVXUS . "` AS zavxus, `" . Tables::$TBL_USER . "` AS user WHERE zavxus.id_user = user.id AND `id_zavod` = ?", $race_id);

        $result["everyone"] = [];

        while ($child = $output->fetch_assoc()) {
            $result["everyone"][] = [
                "user_id" => $child["id_user"],

                // user
                "name" => $child["jmeno"],
                "surname" => $child["prijmeni"],
                "registration_number" => $child["reg"],
                "chip_number" => $child["si_chip_temp"] ?: $child["si_chip"],

                // zavxus
                "category" => $child["kat"],
                "note" => $child["pozn"],
                "note_internal" => $child["pozn_in"],
                // 0 = No; 1 = Yes; 2 = Auto Yes; 3 = Shared;
                "transport" => $child["transport"],
                "transport_shared" => $child["sedadel"],
                // 0 = No; 1 = Yes; 2 = Auto Yes;
                "accommodation" => $child["ubytovani"],
            ];
        }

        response()->json($result);
    }

    public static function relations($race_id) {
        // select user_id (first) and its sheeps
        $output = Database::query("SELECT * FROM `" . Tables::$TBL_USER . "` WHERE `id` = ? OR `chief_id` = ? ORDER BY CASE WHEN `id` = ? THEN 1 ELSE 2 END", Session::$user_id, Session::$user_id, Session::$user_id);
        
        $result = [];
        while ($child = $output->fetch_assoc()) {
            $zavxus = Database::fetch_assoc("SELECT zavxus.* FROM `" . Tables::$TBL_ZAVXUS . "` AS zavxus, `" . Tables::$TBL_USER . "` AS user WHERE zavxus.id_user = user.id AND user.id = ? AND zavxus.id_zavod = ? LIMIT 1", $child["id"], $race_id);

            $result[] = [
                "user_id" => $child["id"],

                // user
                "name" => $child["jmeno"],
                "surname" => $child["prijmeni"],
                "registration_number" => $child["reg"],
                "chip_number" => @$zavxus["si_chip"] ?: $child["si_chip"],

                // zavxus
                "category" => @$zavxus["kat"],
                "note" => @$zavxus["pozn"],
                "note_internal" => @$zavxus["pozn_in"],
                // 0 = No; 1 = Yes; 2 = Auto Yes; 3 = Shared;
                "transport" => @$zavxus["transport"],
                "transport_shared" => @$zavxus["sedadel"],
                // 0 = No; 1 = Yes; 2 = Auto Yes;
                "accommodation" => @$zavxus["ubytovani"],

                "is_signed_in" => $zavxus != null,
            ];
        }

        response()->json($result);
    }

    public static function signin($race_id, $user_id) {
        // the id of who is being signed in
        $input = new Input(required: true);
        $input->add("category");
        $input->add("note");
        $input->add("note_internal");
        $input->add("transport", filter: Input::$FILTER_BOOL);
        $input->add("accommodation", filter: Input::$FILTER_BOOL);
        $input = $input->collect();

        $output = Database::fetch_assoc("SELECT transport FROM " . Tables::$TBL_RACE . " WHERE id = ?", $race_id);
        
        // if transport is not shared, disable transport_shared, use weak comparison
        if ($output["transport"] == 3) {
            // require transport_shared only when needed
            $input["transport_shared"] = Input::key("transport_shared");
            // if transport_shared is empty string, disable transport
            if ($input["transport_shared"] === "") {
                $input["transport"] = 0;
            }
        } else {
            $input["transport_shared"] = null;
        }
        
        self::__check_current_user_is_managing($user_id); 
        self::__check_locked_user($user_id);

        if (($termin = self::__timeToRegistration($race_id)) === 0) {
            throw new ApiException("The deadline for entry has been exceeded.", 404);
            return;
        }

        self::__check_cancelled_race($race_id);

        // check if the user is already signed in
        $output = Database::fetch_assoc("SELECT * FROM `" . Tables::$TBL_ZAVXUS . "` WHERE `id_zavod` = ? AND `id_user` = ? LIMIT 1", $race_id, $user_id);
        if ($output === null) {
            // if not, create a new row with given values
            Database::query("INSERT INTO `" . Tables::$TBL_ZAVXUS . "` (`id_user`, `id_zavod`, `kat`, `pozn`, `pozn_in`, `transport`, `sedadel`, `ubytovani`, `termin`) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
                $user_id, $race_id, $input["category"], $input["note"], $input["note_internal"], $input["transport"], $input["transport_shared"], $input["accommodation"], $termin);
        } else {
            // else, update the row
            Database::query("UPDATE `" . Tables::$TBL_ZAVXUS . "` SET `kat` = ?, `pozn` = ?, `pozn_in` = ?, `transport` = ?, `sedadel` = ?, `ubytovani` = ?, `termin` = ? WHERE `id` = ?",
                $input["category"], $input["note"], $input["note_internal"], $input["transport"], $input["transport_shared"], $input["accommodation"], $termin, $output["id"]);
        }
    }

    public static function signout($race_id, $user_id) {
        // the id of who is being signed out
        self::__check_current_user_is_managing($user_id);
        self::__check_locked_user($user_id);
        self::__check_cancelled_race($race_id);

        Database::query("DELETE FROM `" . Tables::$TBL_ZAVXUS . "` WHERE `id_zavod` = ? AND `id_user` = ?", $race_id, $user_id);
    }

    public static function notify($race_id) {
        $title = Input::key("title");
        $body = Input::key("body");
        $image = Input::key("image", required: false);
        
        if ($image !== null && filter_var($image, FILTER_VALIDATE_URL) === false) {
            throw new ApiException("Field 'image' must be a valid URL or null.", 400);
            return;
        }
        
        if (!Session::$policy_mng_big) {
            throw new ApiException("You must be at least big manager to send a notification.", 403);
            return;
        }
        
        $content = new NotifyContent($title, $body);
        $content->topic(Session::$clubname);
        $content->custom_event(NotifyContent::$EVENT_RACE, $race_id);
        $content->image($image);
        
        response()->json(Notifications::send($content->export()));
    }

    ///////////////
    // Utilities //
    ///////////////

    private static function __check_current_user_is_managing($user_id) {
        // raises error if current user is is not managing user

        if (Session::$user_id == $user_id) { // use == operator to accept str == int
            // the chief has obviously access to itself
            return true;
        }

        if (!Session::$policy_mng_small) {
            throw new ApiException("You must be at least a small manager.", 401);
            return;
        }
    
        $output = Database::fetch_assoc("SELECT `id` FROM `" . Tables::$TBL_USER . "` WHERE `id` = ? AND `chief_id` = ? LIMIT 1", $user_id, Session::$user_id);
    
        if ($output === null) {
            throw new ApiException("The user you are trying to sign in has to be associated with you.", 401);
            return;
        }
    }

    private static function __check_locked_user($user_id) {
        if (Database::fetch_assoc("SELECT `entry_locked` FROM `" . Tables::$TBL_USER . "` WHERE `id` = ?", $user_id)["entry_locked"]) {
            throw new ApiException("Your account is locked.", 403);
            return;
        }
    }

    private static function __check_cancelled_race($race_id) {
        if (Database::fetch_assoc("SELECT `cancelled` FROM `" . Tables::$TBL_RACE . "` WHERE `id` = ?", $race_id)["cancelled"] == 1) {
            throw new ApiException("The race you are trying to sign in is cancelled.", 404);
            return;
        }
    }

    private static function __get_race_info($race_id) {
        $race = Database::fetch_assoc("SELECT * FROM " . Tables::$TBL_RACE . " WHERE `id` = ?", $race_id);

        if ($race === null) {
            throw new ApiException("The race you are looking for does not exists.", 404);
            return;
        }
    
        $dates = [ Utils::dateToISO($race["datum"]) ]; // always provide date
        if ($race["vicedenni"]) $dates[] = Utils::dateToISO($race["datum2"]); // add second date if exists
    
        $entries = [ Utils::dateToISO($race["prihlasky1"]) ]; // always provide entry
        if ($race["prihlasky2"] != 0 && $race["prihlasky"] > 1 ) $entries[] = Utils::dateToISO($race["prihlasky2"]);
        if ($race["prihlasky3"] != 0 && $race["prihlasky"] > 2 ) $entries[] = Utils::dateToISO($race["prihlasky3"]);
        if ($race["prihlasky4"] != 0 && $race["prihlasky"] > 3 ) $entries[] = Utils::dateToISO($race["prihlasky4"]);
        if ($race["prihlasky5"] != 0 && $race["prihlasky"] > 4 ) $entries[] = Utils::dateToISO($race["prihlasky5"]);

        $zebricek = [];
        for($i=0; $i<Enums::$g_zebricek_cnt; $i++) {
            if (Enums::$g_zebricek[$i]["id"] & $race["zebricek"]) {
                $zebricek[] = Enums::$g_zebricek[$i]["nm"];
            }
        }
        
        $sport = null;
        for ($i=0; $i<Enums::$g_racetype_cnt; $i++) {
            if (Enums::$g_racetype[$i]["enum"] == $race["typ0"]) {
                $sport = Enums::$g_racetype[$i]["nm"];
                break;
            }
        }
        
        $link = $race["odkaz"];
        // https://stackoverflow.com/a/14701491/14900791
        if ($link && parse_url(ltrim($link, "/"), PHP_URL_SCHEME) === null) {
            // guess protocol
            $link = "http://" . $link;
        }
        
        return [
            "race_id" => $race["id"],
            
            "dates" => $dates,
            "entries" => $entries,
            "name" => $race["nazev"],
            
            // not sure cancelled is string, use weak comparision
            "cancelled" => $race["cancelled"] == 1,
            "club" => $race["oddil"],
            "link" => $link,
            "place" => $race["misto"],
            "type" => Enums::$g_racetype0[$race["typ0"]],
            "sport" => $sport,
            "rankings" => $zebricek,
            "rank21" => $race["ranking"],
            "note" => $race["poznamka"],
            
            // 0 = No; 1 = Yes; 2 = Auto Yes; 3 = Shared;
            "transport" => Config::$g_enable_race_transport ? $race["transport"] : 0,
            
            // 0 = No; 1 = Yes; 2 = Auto Yes; 3 = Shared;
            "accommodation" => Config::$g_enable_race_accommodation ? $race["ubytovani"] : 0,
            
            // explode returns [""] on empty list
            "categories" => $race["kategorie"] == "" ? [] : explode(";", $race["kategorie"]),
        ];
    }

    private static function __timeToRegistration($race_id) {
        // returns a registration state
        
        // For reg/unreg
        // 1 .. 5 - active term
        // 0 - any active term / cannot process	
        
        $zaznam = Database::fetch_assoc("SELECT `datum`, `prihlasky`, `prihlasky1`, `prihlasky2`, `prihlasky3`, `prihlasky4`, `prihlasky5` FROM `" . Tables::$TBL_RACE . "` WHERE `id` = ?", $race_id);
    
        if (Utils::getTimeToRace($zaznam["datum"]) <= 0) return 0;
        if ($zaznam["prihlasky"] == 0) return 1;
        if (Utils::getTimeToReg($zaznam["prihlasky1"]) != -1 ) return 1;
        if ($zaznam["prihlasky"] > 1 && Utils::getTimeToReg($zaznam["prihlasky2"]) != -1 ) return 2;
        if ($zaznam["prihlasky"] > 2 && Utils::getTimeToReg($zaznam["prihlasky3"]) != -1 ) return 3;
        if ($zaznam["prihlasky"] > 3 && Utils::getTimeToReg($zaznam["prihlasky4"]) != -1 ) return 4;
        if ($zaznam["prihlasky"] > 4 && Utils::getTimeToReg($zaznam["prihlasky5"]) != -1 ) return 5;
    
        return 0;
    }
};    


