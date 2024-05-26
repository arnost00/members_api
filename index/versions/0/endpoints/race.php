<?php

namespace Endpoints;

use Pecee\SimpleRouter\SimpleRouter as Router;
use Pecee\SimpleRouter\Exceptions\HttpException;

use Core\Api;
use Core\Endpoint;
use Core\Utils;

use Controllers\RequireTokenMiddleware;

class Race implements Endpoint
{
    public static function init(): void {
        Router::fetch("/races", [static::class, "races"]);
        
        Router::group(["prefix" => "/race"], function () {
            $race_id = ["race_id" => "[0-9]+"];
            $user_id = ["user_id" => "[0-9]+"];

            Router::fetch("/", [static::class, "index"]);
            Router::fetch("/{race_id}", [static::class, "detail"])->where($race_id);
            Router::fetch("/{race_id}/redirect", [static::class, "redirect"])->where($race_id);

            Router::group(["middleware" => RequireTokenMiddleware::class], function () use ($race_id, $user_id) {
                Router::fetch("/{race_id}/relations", [static::class, "relations"])->where($race_id);
                Router::post("/{race_id}/signin/{user_id}", [static::class, "signin"])->where($race_id + $user_id);
                Router::post("/{race_id}/signout/{user_id}", [static::class, "signout"])->where($race_id + $user_id);
            });
        });
    }

    public static function index() {
        throw new HttpException("race_id is required.", 404);
        return;
    }

    public static function redirect($race_id) {
        // status code 302, indicates that the resource requested has been temporarily moved
        redirect(Api::config()->g_baseadr . "race_info_show.php?id_zav=" . $race_id);
    }

    public static function races() {
        $current_date = Utils::getCurrentDate();

        $output = Api::database()->query("SELECT * FROM `" . TBL_RACE . "` WHERE `datum` >= ? || `datum2` >= ? ORDER BY `datum`", $current_date, $current_date);

        $result = [];
        while ($race = $output->fetch_assoc()) {
            $result[] = self::__parseRaceSQLResult($race);
        }

        response()->json($result);
    }

    public static function detail($race_id) {
        $race_id = (int)$race_id;

        $output = Api::database()->fetch_assoc("SELECT * FROM `" . TBL_RACE . "` WHERE `id` = ? LIMIT 1", $race_id);

        if ($output === null) {
            throw new HttpException("The race you are looking for does not exists.", 404);
            return;
        }

        // the main data about the race is here
        $result = self::__parseRaceSQLResult($output);

        // provide information about signed in users
        $output = Api::database()->query("SELECT zavxus.si_chip AS si_chip_temp, zavxus.*, user.* FROM `" . TBL_ZAVXUS . "` AS zavxus, `" . TBL_USER . "` AS user WHERE zavxus.id_user = user.id AND `id_zavod` = ?", $race_id);

        $result["everyone"] = [];

        while ($child = $output->fetch_assoc()) {
            $child_id = $child["id_user"];

            $formated_output = [
                "user_id" => $child_id,

                // user
                "name" => $child["jmeno"],
                "surname" => $child["prijmeni"],
                "registration_number" => $child["reg"],
                "chip_number" => $child["si_chip_temp"] ?: $child["si_chip"],

                // zavxus
                "category" => $child["kat"],
                "note" => $child["pozn"],
                "note_internal" => $child["pozn_in"],
                "transport" => $child["transport"], // value can be 0, 1, 2
                "accommodation" => $child["ubytovani"], // value can be 0, 1, 2
            ];

            $result["everyone"][] = $formated_output;
        }

        response()->json($result);
    }

    public static function relations($race_id) {
        // select user_id (first) and its sheeps
        $output = Api::database()->query("SELECT * FROM `" . TBL_USER . "` WHERE `id` = ? OR `chief_id` = ? ORDER BY CASE WHEN `id` = ? THEN 1 ELSE 2 END", request()->user_id, request()->user_id, request()->user_id);
        
        $result = [];

        while ($child = $output->fetch_assoc()) {
            $child_id = $child["id"];

            $zavxus = Api::database()->fetch_assoc("SELECT zavxus.* FROM `" . TBL_ZAVXUS . "` AS zavxus, `" . TBL_USER . "` AS user WHERE zavxus.id_user = user.id AND user.id = ? AND zavxus.id_zavod = ? LIMIT 1", $child_id, $race_id);

            $formated_output = [
                "user_id" => $child_id,

                // user
                "name" => $child["jmeno"],
                "surname" => $child["prijmeni"],
                "registration_number" => $child["reg"],
                "chip_number" => @$zavxus["si_chip"] ?: $child["si_chip"],

                // zavxus
                "category" => @$zavxus["kat"],
                "note" => @$zavxus["pozn"],
                "note_internal" => @$zavxus["pozn_in"],
                "transport" => @$zavxus["transport"], // value can be 0, 1, 2
                "accommodation" => @$zavxus["ubytovani"], // value can be 0, 1, 2

                "is_signed_in" => $zavxus != null,
            ];

            $result[] = $formated_output;
        }

        response()->json($result);
    }

    public static function signin($race_id, $user_id) {
        // the id of who is signing someone in
        $chief_id = request()->user_id;
        // the id of who is being signed in
        $user_id = (int)$user_id;

        $result = input()->all([
            "category",
            "note",
            "note_internal",
            "transport",
            "accommodation",
        ]);
        foreach ($result as $key => $value) {
            if ($value === null) {
                throw new HttpException("Key $key is required in the request.", 404);
                return;
            }
        }
        
        $result["transport"] = $result["transport"] ? 1 : 0;
        $result["accommodation"] = $result["accommodation"] ? 1 : 0;
        
        self::__checkChiefAndUser($chief_id, $user_id); 
        self::__checkLockedUser($user_id);

        if (($termin = self::__timeToRegistration($race_id)) === 0) {
            throw new HttpException("The deadline for entry has been exceeded.", 404);
            return;
        }

        self::__checkCancelledRace($race_id);

        // check if the user is already signed in
        $output = Api::database()->fetch_assoc("SELECT * FROM `" . TBL_ZAVXUS . "` WHERE `id_zavod` = ? AND `id_user` = ? LIMIT 1", $race_id, $user_id);
        if ($output === null) {
            // if not, create a new row with given values
            Api::database()->query("INSERT INTO `" . TBL_ZAVXUS . "` (`id_user`, `id_zavod`, `kat`, `pozn`, `pozn_in`, `transport`, `ubytovani`, `termin`) VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
                $user_id, $race_id, $result["category"], $result["note"], $result["note_internal"], $result["transport"], $result["accommodation"], $termin);
        } else {
            // else, update the row
            Api::database()->query("UPDATE `" . TBL_ZAVXUS . "` SET `kat` = ?, `pozn` = ?, `pozn_in` = ?, `transport` = ?, `ubytovani` = ?, `termin` = ? WHERE `id` = ?",
                $result["category"], $result["note"], $result["note_internal"], $result["transport"], $result["accommodation"], $termin, $output["id"]);
        }
        
        response()->json([]);
    }

    public static function signout($race_id, $user_id) {
        // the id of who is signing someone out
        $chief_id = request()->user_id;
        // the id of who is being signed out
        $user_id = (int)$user_id;

        self::__checkChiefAndUser($chief_id, $user_id);
        self::__checkLockedUser($user_id);
        self::__checkCancelledRace($race_id);

        Api::database()->query("DELETE FROM `" . TBL_ZAVXUS . "` WHERE `id_zavod` = ? AND `id_user` = ?", $race_id, $user_id);

        response()->json([]);
    }

    ///////////////
    // Utilities //
    ///////////////

    private static function __checkChiefAndUser($chief_id, $user_id) {
        // raises error if chief is is not managing user

        if ($chief_id == $user_id) { // use == operator to accept str == int
            // the chief has obviously access to itself
            return true;
        }
        
        $output = Api::database()->fetch_assoc("SELECT `policy_mng` FROM `" . TBL_ACCOUNT . "` WHERE `id_users` = ? LIMIT 1", $chief_id);
    
        if ($output["policy_mng"] != _MNG_SMALL_INT_VALUE_ && $output["policy_mng"] != _MNG_BIG_INT_VALUE_) {
            throw new HttpException("You must be at least a small manager.", 401);
            return;
        }
    
        $output = Api::database()->fetch_assoc("SELECT `id` FROM `" . TBL_USER . "` WHERE `id` = ? AND `chief_id` = ? LIMIT 1", $user_id, $chief_id);
    
        if ($output === null) {
            throw new HttpException("The user you are trying to sign in has to be associated with you.", 401);
            return;
        }
    }

    private static function __checkLockedUser($user_id) {
        if (Api::database()->fetch_assoc("SELECT `entry_locked` FROM `" . TBL_USER . "` WHERE `id` = ?", $user_id)["entry_locked"]) {
            throw new HttpException("Your account is locked.", 403);
            return;
        }
    }

    private static function __checkCancelledRace($race_id) {
        if (Api::database()->fetch_assoc("SELECT `cancelled` FROM `" . TBL_RACE . "` WHERE `id` = ?", $race_id)["cancelled"] == 1) {
            throw new HttpException("The race you are trying to sign in is cancelled.", 404);
            return;
        }
    }

    private static function __parseRaceSQLResult($race) {
        // expects race row from "SELECT * FROM " . TBL_RACE
    
        $dates = [ Utils::dateToISO($race["datum"]) ]; // always provide date
        if ($race["vicedenni"]) $dates[] = Utils::dateToISO($race["datum2"]); // add second date if exists
    
        $entries = [ Utils::dateToISO($race["prihlasky1"]) ]; // always provide entry
        if ($race["prihlasky2"] != 0 && $race["prihlasky"] > 1 ) $entries[] = Utils::dateToISO($race["prihlasky2"]);
        if ($race["prihlasky3"] != 0 && $race["prihlasky"] > 2 ) $entries[] = Utils::dateToISO($race["prihlasky3"]);
        if ($race["prihlasky4"] != 0 && $race["prihlasky"] > 3 ) $entries[] = Utils::dateToISO($race["prihlasky4"]);
        if ($race["prihlasky5"] != 0 && $race["prihlasky"] > 4 ) $entries[] = Utils::dateToISO($race["prihlasky5"]);
    
        // 0 = No; 1 = Yes; 2 = Auto Yes;
        $transport = Api::config()->g_enable_race_transport ? $race["transport"] : 0;
    
        // 0 = No; 1 = Yes; 2 = Auto Yes;
        $accommodation = Api::config()->g_enable_race_accommodation ? $race["ubytovani"] : 0;
    
        // use enums to parse attributes
        $type = Api::config()->g_racetype0[$race["typ0"]];
    
        // parse rankings
        $rankings = [];
        for($i=0; $i<Api::config()->g_zebricek_cnt; $i++) {
            if (Api::config()->g_zebricek[$i]["id"] & $race["zebricek"]) {
                $rankings[] = Api::config()->g_zebricek[$i]["nm"];
            }
        }
    
        // parse sport
        $sport = null;
        for ($i=0; $i<Api::config()->g_racetype_cnt; $i++) {
            if (Api::config()->g_racetype[$i]["enum"] == $race["typ0"]) {
                $sport = Api::config()->g_racetype[$i]["nm"];
                break;
            }
        }
    
        $link = $race["odkaz"];
        // https://stackoverflow.com/a/14701491/14900791
        if ($link && parse_url(ltrim($link, "/"), PHP_URL_SCHEME) === null) {
            $link = "http://" . $link; // add some protocol if does not exists
        }
    
        // explode returns [""] on empty list
        $categories = $race["kategorie"] == "" ? [] : explode(";", $race["kategorie"]);
    
        return [
            "race_id" => $race["id"],
            // "is_registered" => $is_registered,
            "dates" => $dates,
            "entries" => $entries,
            "name" => $race["nazev"],
            "is_cancelled" => $race["cancelled"] == 1,
            "club" => $race["oddil"],
            "link" => $link,
            "place" => $race["misto"],
            "type" => $type,
            "sport" => $sport,
            "rankings" => $rankings,
            "rank21" => $race["ranking"],
            "note" => $race["poznamka"],
            "transport" => $transport,
            "accommodation" => $accommodation,
            "categories" => $categories,
        ];
    }

    private static function __timeToRegistration($race_id) {
        // returns a registration state
        
        // For reg/unreg
        // 1 .. 5 - active term
        // 0 - any active term / cannot process	
        
        $zaznam = Api::database()->fetch_assoc("SELECT `datum`, `prihlasky`, `prihlasky1`, `prihlasky2`, `prihlasky3`, `prihlasky4`, `prihlasky5` FROM `" . TBL_RACE . "` WHERE `id` = ?", $race_id);
    
        if (Utils::getTimeToRace($zaznam["datum"]) <= 0) {
            return 0;
        }
        if ($zaznam["prihlasky"] == 0) {
            return 1;
        }
        if (Utils::getTimeToReg($zaznam["prihlasky1"]) != -1 ) {
            return 1;
        }
        if ($zaznam["prihlasky"] > 1 && Utils::getTimeToReg($zaznam["prihlasky2"]) != -1 ) {
            return 2;
        }
        if ($zaznam["prihlasky"] > 2 && Utils::getTimeToReg($zaznam["prihlasky3"]) != -1 ) {
            return 3;
        }
        if ($zaznam["prihlasky"] > 3 && Utils::getTimeToReg($zaznam["prihlasky4"]) != -1 ) {
            return 4;
        }
        if ($zaznam["prihlasky"] > 4 && Utils::getTimeToReg($zaznam["prihlasky5"]) != -1 ) {
            return 5;
        }
    
        return 0;
    }
};    


