<?php

namespace Core;

require_once __DIR__ . "/api.php";

use Core\Api;

class Clubs {
    // clubs that are avaible
    //
    // clubs must be without slash in the end

    public static function list() {
        $result = [];

        foreach (\Manifest::$available_clubs as $clubname) {
            // null values are ignored by php
            $result[] = static::info($clubname);
        }

        return $result;
    }

    public static function info($clubname) {
        if ($clubname === null) {
            return null;
        }

        // do not use include_once to prevent future errors
        // do not use require* to prevent raising error
        if (!@include \Manifest::$path_to_clubs . $clubname . "/cfg/_cfg.php") {
            return null;
        }

        return [
            // clubname is required by the middleware
            "clubname" => $clubname,

            "is_release" => $g_is_release ?? false,

            "fullname" => $g_fullname ?? "",
            "shortcut" => $g_shortcut ?? "",

            "baseadr" => $g_baseadr ?? "",
            "mainwww" => $g_mainwww ?? "",
            
            "emailadr" => $g_emailadr ?? "",
        ];
    }

    public static function import($clubname) {
        $___cache___ = get_defined_vars();

        if (!is_dir(\Manifest::$path_to_clubs . $clubname . "/cfg/")) {
            throw new ApiException("Club not found. Attempting to join a non-existent club?", 404);
        }

        // do not use include_once to prevent future errors
        // do not use require* to prevent raising error
        if (!@(include \Manifest::$path_to_clubs . $clubname . "/cfg/_cfg.php")) {
            throw new ApiException("file '_cfg.php' in specified club does not exists", 404);
        }
        
        if (!@(include \Manifest::$path_to_clubs . $clubname . "/cfg/_tables.php")) {
            throw new ApiException("file '_tables.php' in specified club does not exists", 404);
        }
        
        if (!@(include \Manifest::$path_to_clubs . $clubname . "/cfg/race_enums.php")) {
            throw new ApiException("file 'race_enums.php' in specified club does not exists", 404);
        }
        
        if (!@(include \Manifest::$path_to_clubs . $clubname . "/cfg/session_enums.php")) {
            throw new ApiException("file 'session_enums.php' in specified club does not exists", 404);
        }
        
        // old approach is to import only variables starting with g_

        // Api::config()->update(array_filter(get_defined_vars(), function ($key) {
        //     return substr($key, 0, 2) === "g_";
        // }, ARRAY_FILTER_USE_KEY));
        
        $import = array_diff_key(get_defined_vars(), $___cache___);
        unset($import["___cache___"]);
        unset($___cache___);

        Api::config()->update($import);
    }
}