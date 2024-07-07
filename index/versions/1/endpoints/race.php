<?php

namespace Endpoints;

use Controllers\NotifyContent;
use Pecee\SimpleRouter\SimpleRouter as Router;
use Pecee\SimpleRouter\Exceptions\HttpException;
use Manifest\Manifest;

require_once __DIR__ . "/../controllers/holders.php";
require_once __DIR__ . "/../controllers/middlewares.php";
require_once __DIR__ . "/../controllers/utils.php";

require_once Manifest::$core_directory . "/api.php";
require_once Manifest::$core_directory . "/endpoint.php";
require_once Manifest::$core_directory . "/notify.php";

use Core\Api;
use Core\Endpoint;
use Core\Notifications;

use Controllers\RequireTokenMiddleware;
use Controllers\Policies;
use Controllers\Tables;
use Controllers\Utils;
use Controllers\NotifyGenerator;

class Race implements Endpoint
{
    public static function init(): void {
        Router::form("/races", [static::class, "races"]);
        
        Router::group(["prefix" => "/race"], function () {
            $race_id = ["race_id" => "[0-9]+"];
            $user_id = ["user_id" => "[0-9]+"];

            Router::form("/", [static::class, "index"]);
            Router::form("/{race_id}", [static::class, "detail"])->where($race_id);
            Router::form("/{race_id}/redirect", [static::class, "redirect"])->where($race_id);

            Router::group(["middleware" => RequireTokenMiddleware::class], function () use ($race_id, $user_id) {
                Router::form("/{race_id}/relations", [static::class, "relations"])->where($race_id);
                Router::post("/{race_id}/signin/{user_id}", [static::class, "signin"])->where($race_id + $user_id);
                Router::post("/{race_id}/signout/{user_id}", [static::class, "signout"])->where($race_id + $user_id);
                Router::post("/{race_id}/notify", [static::class, "notify"])->where($race_id);
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

        $output = Api::database()->query("SELECT `id` FROM `" . Tables::$RACE . "` WHERE `datum` >= ? || `datum2` >= ? ORDER BY `datum`", $current_date, $current_date);

        $result = [];
        while ($race = $output->fetch_assoc()) {
            $result[] = self::__get_race_info($race["id"]);
        }

        response()->json($result);
    }

    public static function detail($race_id) {
        $race_id = (int)$race_id;

        $result = self::__get_race_info($race_id);

        // provide information about signed in users
        $output = Api::database()->query("SELECT zavxus.si_chip AS si_chip_temp, zavxus.*, user.* FROM `" . Tables::$ZAVXUS . "` AS zavxus, `" . Tables::$USER . "` AS user WHERE zavxus.id_user = user.id AND `id_zavod` = ?", $race_id);

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
                "transport" => $child["transport"], // value can be 0, 1, 2
                "accommodation" => $child["ubytovani"], // value can be 0, 1, 2
            ];
        }

        response()->json($result);
    }

    public static function relations($race_id) {
        // select user_id (first) and its sheeps
        $output = Api::database()->query("SELECT * FROM `" . Tables::$USER . "` WHERE `id` = ? OR `chief_id` = ? ORDER BY CASE WHEN `id` = ? THEN 1 ELSE 2 END", request()->user_id, request()->user_id, request()->user_id);
        
        $result = [];
        while ($child = $output->fetch_assoc()) {
            $zavxus = Api::database()->fetch_assoc("SELECT zavxus.* FROM `" . Tables::$ZAVXUS . "` AS zavxus, `" . Tables::$USER . "` AS user WHERE zavxus.id_user = user.id AND user.id = ? AND zavxus.id_zavod = ? LIMIT 1", $child["id"], $race_id);

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
                "transport" => @$zavxus["transport"], // value can be 0, 1, 2
                "accommodation" => @$zavxus["ubytovani"], // value can be 0, 1, 2

                "is_signed_in" => $zavxus != null,
            ];
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
        
        self::__check_chief_and_user($chief_id, $user_id); 
        self::__check_locked_user($user_id);

        if (($termin = self::__timeToRegistration($race_id)) === 0) {
            throw new HttpException("The deadline for entry has been exceeded.", 404);
            return;
        }

        self::__check_cancelled_race($race_id);

        // check if the user is already signed in
        $output = Api::database()->fetch_assoc("SELECT * FROM `" . Tables::$ZAVXUS . "` WHERE `id_zavod` = ? AND `id_user` = ? LIMIT 1", $race_id, $user_id);
        if ($output === null) {
            // if not, create a new row with given values
            Api::database()->query("INSERT INTO `" . Tables::$ZAVXUS . "` (`id_user`, `id_zavod`, `kat`, `pozn`, `pozn_in`, `transport`, `ubytovani`, `termin`) VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
                $user_id, $race_id, $result["category"], $result["note"], $result["note_internal"], $result["transport"], $result["accommodation"], $termin);
        } else {
            // else, update the row
            Api::database()->query("UPDATE `" . Tables::$ZAVXUS . "` SET `kat` = ?, `pozn` = ?, `pozn_in` = ?, `transport` = ?, `ubytovani` = ?, `termin` = ? WHERE `id` = ?",
                $result["category"], $result["note"], $result["note_internal"], $result["transport"], $result["accommodation"], $termin, $output["id"]);
        }
        
        response()->json([]);
    }

    public static function signout($race_id, $user_id) {
        // the id of who is signing someone out
        $chief_id = request()->user_id;
        // the id of who is being signed out
        $user_id = (int)$user_id;

        self::__check_chief_and_user($chief_id, $user_id);
        self::__check_locked_user($user_id);
        self::__check_cancelled_race($race_id);

        Api::database()->query("DELETE FROM `" . Tables::$ZAVXUS . "` WHERE `id_zavod` = ? AND `id_user` = ?", $race_id, $user_id);

        response()->json([]);
    }

    public static function notify($race_id) {
        $title = input()->find("title");
        $body = input()->find("body");
        
        $image = input()->find("image");
        
        if ($title === null) {
            throw new HttpException("Field 'title' is required.", 400);
            return;
        }
        
        if ($body === null) {
            throw new HttpException("Field 'body' is required.", 400);
            return;
        }
        
        if ($image !== null && filter_var($image->getValue(), FILTER_VALIDATE_URL) === false) {
            throw new HttpException("Field 'image' must be a valid URL or null.", 400);
            return;
        }
        
        if (!Policies::is_big_manager(request()->user_id)) {
            throw new HttpException("You must be at least big manager to send a notification.", 403);
            return;
        }

        $content = new NotifyContent($title, $body);
        $content->race($race_id);
        $content->image = $image;
        
        Notifications::send($content->export());
    }

    ///////////////
    // Utilities //
    ///////////////

    private static function __check_chief_and_user($chief_id, $user_id) {
        // raises error if chief is is not managing user

        if ($chief_id == $user_id) { // use == operator to accept str == int
            // the chief has obviously access to itself
            return true;
        }

        if (!Policies::is_any_manager($chief_id)) {
            throw new HttpException("You must be at least a small manager.", 401);
            return;
        }
    
        $output = Api::database()->fetch_assoc("SELECT `id` FROM `" . Tables::$USER . "` WHERE `id` = ? AND `chief_id` = ? LIMIT 1", $user_id, $chief_id);
    
        if ($output === null) {
            throw new HttpException("The user you are trying to sign in has to be associated with you.", 401);
            return;
        }
    }

    private static function __check_locked_user($user_id) {
        if (Api::database()->fetch_assoc("SELECT `entry_locked` FROM `" . Tables::$USER . "` WHERE `id` = ?", $user_id)["entry_locked"]) {
            throw new HttpException("Your account is locked.", 403);
            return;
        }
    }

    private static function __check_cancelled_race($race_id) {
        if (Api::database()->fetch_assoc("SELECT `cancelled` FROM `" . Tables::$RACE . "` WHERE `id` = ?", $race_id)["cancelled"] == 1) {
            throw new HttpException("The race you are trying to sign in is cancelled.", 404);
            return;
        }
    }

    private static function __get_race_info($race_id) {
        $race = Api::database()->fetch_assoc("SELECT * FROM " . Tables::$RACE . " WHERE `id` = ?", $race_id);

        if ($race === null) {
            throw new HttpException("The race you are looking for does not exists.", 404);
            return;
        }
    
        $dates = [ Utils::dateToISO($race["datum"]) ]; // always provide date
        if ($race["vicedenni"]) $dates[] = Utils::dateToISO($race["datum2"]); // add second date if exists
    
        $entries = [ Utils::dateToISO($race["prihlasky1"]) ]; // always provide entry
        if ($race["prihlasky2"] != 0 && $race["prihlasky"] > 1 ) $prihlasky[] = Utils::dateToISO($race["prihlasky2"]);
        if ($race["prihlasky3"] != 0 && $race["prihlasky"] > 2 ) $prihlasky[] = Utils::dateToISO($race["prihlasky3"]);
        if ($race["prihlasky4"] != 0 && $race["prihlasky"] > 3 ) $prihlasky[] = Utils::dateToISO($race["prihlasky4"]);
        if ($race["prihlasky5"] != 0 && $race["prihlasky"] > 4 ) $prihlasky[] = Utils::dateToISO($race["prihlasky5"]);

        $zebricek = [];
        for($i=0; $i<Api::config()->g_zebricek_cnt; $i++) {
            if (Api::config()->g_zebricek[$i]["id"] & $race["zebricek"]) {
                $zebricek[] = Api::config()->g_zebricek[$i]["nm"];
            }
        }
        
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
            "type" => Api::config()->g_racetype0[$race["typ0"]],
            "sport" => $sport,
            "rankings" => $zebricek,
            "rank21" => $race["ranking"],
            "note" => $race["poznamka"],
            
            // 0 = No; 1 = Yes; 2 = Auto Yes;
            "transport" => Api::config()->g_enable_race_transport ? $race["transport"] : 0,
            
            // 0 = No; 1 = Yes; 2 = Auto Yes;
            "accommodation" => Api::config()->g_enable_race_accommodation ? $race["ubytovani"] : 0,
            
            // explode returns [""] on empty list
            "categories" => $race["kategorie"] == "" ? [] : explode(";", $race["kategorie"]),
        ];
    }

    private static function __timeToRegistration($race_id) {
        // returns a registration state
        
        // For reg/unreg
        // 1 .. 5 - active term
        // 0 - any active term / cannot process	
        
        $zaznam = Api::database()->fetch_assoc("SELECT `datum`, `prihlasky`, `prihlasky1`, `prihlasky2`, `prihlasky3`, `prihlasky4`, `prihlasky5` FROM `" . Tables::$RACE . "` WHERE `id` = ?", $race_id);
    
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


