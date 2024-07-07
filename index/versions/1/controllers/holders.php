<?php

namespace Controllers;

use Pecee\SimpleRouter\Exceptions\HttpException;
use Manifest\Manifest;
use Core\Api;

require_once Manifest::$core_directory . "/api.php";

class Holder {
    /**
     * A simple holder for constants.
     * Function `static::init()` should be called after club import at `middlewares.php`.
     */

    protected static array $translate = [];
    protected static bool $_is_loaded = false;

    public static function init() {
        foreach (static::$translate as $pointer => $alias) {
            if (!defined($pointer)) {
                throw new HttpException("Constant '" . $pointer . "' (alias '" . $alias . "') is not defined.", 500);
                return;
            }

            static::$$alias = constant($pointer);
        }

        static::$_is_loaded = true;
    }

    public static function is_loaded() {
        return static::$_is_loaded;
    }
}

class Tables extends Holder {
    // from `_tables.php`
    //
    // define ('TBL_RACE','spe_zavod');
    // define ('TBL_NEWS','spe_news');
    // define ('TBL_USER','spe_users');
    // define ('TBL_ACCOUNT','spe_accounts');
    // define ('TBL_USXUS','spe_usxus'); // from build 553 not used, but don't erase this line (needed for_SQL folder)
    // define ('TBL_ZAVXUS','spe_zavxus');
    // define ('TBL_MODLOG','spe_modify_log');
    // define ('TBL_MAILINFO','spe_mailinfo');
    // define ('TBL_FINANCE','spe_finance');
    // define ('TBL_CLAIM','spe_claim');
    // define ('TBL_FINANCE_TYPES','spe_finance_types');
    // define ('TBL_CATEGORIES_PREDEF','spe_categories_predef');

    public static $RACE;
    public static $NEWS;
    public static $USER;
    public static $ACCOUNT;
    public static $USXUS;
    public static $ZAVXUS;
    public static $MODLOG;
    public static $MAILINFO;
    public static $FINANCE;
    public static $CLAIM;
    public static $FINANCE_TYPES;
    public static $CATEGORIES_PREDEF;

    protected static array $translate = [
        "TBL_RACE" => "RACE",
        "TBL_NEWS" => "NEWS",
        "TBL_USER" => "USER",
        "TBL_ACCOUNT" => "ACCOUNT",
        "TBL_USXUS" => "USXUS",
        "TBL_ZAVXUS" => "ZAVXUS",
        "TBL_MODLOG" => "MODLOG",
        "TBL_MAILINFO" => "MAILINFO",
        "TBL_FINANCE" => "FINANCE",
        "TBL_CLAIM" => "CLAIM",
        "TBL_FINANCE_TYPES" => "FINANCE_TYPES",
        "TBL_CATEGORIES_PREDEF" => "CATEGORIES_PREDEF",
    ];
}

class Policies extends Holder {
    // from `session_enums.php`
    // 
    // define('_MNG_BIG_INT_VALUE_',4);
    // define('_MNG_SMALL_INT_VALUE_',2);
    // 
    // define('_USER_GROUP_ID_',200);
    // define('_MANAGER_GROUP_ID_',500);
    // define('_SMALL_MANAGER_GROUP_ID_',600);
    // define('_REGISTRATOR_GROUP_ID_',400);
    // define('_SMALL_ADMIN_GROUP_ID_',700);
    // define('_ADMIN_GROUP_ID_',300);
    // define('_FINANCE_GROUP_ID_',800);

    public static $MNG_BIG;
    public static $MNG_SMALL;
    public static $USER_GROUP_ID;
    public static $MANAGER_GROUP_ID;
    public static $SMALL_MANAGER_GROUP_ID;
    public static $REGISTRATOR_GROUP_ID;
    public static $SMALL_ADMIN_GROUP_ID;
    public static $ADMIN_GROUP_ID;
    public static $FINANCE_GROUP_ID;

    protected static array $translate = [
        "_MNG_BIG_INT_VALUE_" => "MNG_BIG",
        "_MNG_SMALL_INT_VALUE_" => "MNG_SMALL",
        "_USER_GROUP_ID_" => "USER_GROUP_ID",
        "_MANAGER_GROUP_ID_" => "MANAGER_GROUP_ID",
        "_SMALL_MANAGER_GROUP_ID_" => "SMALL_MANAGER_GROUP_ID",
        "_REGISTRATOR_GROUP_ID_" => "REGISTRATOR_GROUP_ID",
        "_SMALL_ADMIN_GROUP_ID_" => "SMALL_ADMIN_GROUP_ID",
        "_ADMIN_GROUP_ID_" => "ADMIN_GROUP_ID",
        "_FINANCE_GROUP_ID_" => "FINANCE_GROUP_ID",
    ];

    public static function get_policy($user_id) {
        $output = Api::database()->fetch_assoc("SELECT `policy_mng` FROM `" . Tables::$ACCOUNT . "` WHERE `id_users` = ? LIMIT 1", $user_id);

        if (!$output) {
            throw new HttpException("Username does not exists.", 401);
        }

        return $output["policy_mng"];
    }

    public static function is_big_manager($user_id) {
        return static::get_policy($user_id) === static::$MNG_BIG;
    }

    public static function is_small_manager($user_id) {
        return static::get_policy($user_id) === static::$MNG_SMALL;
    }

    public static function is_any_manager($user_id) {
        // save one database query
        $policy_mng = static::get_policy($user_id);
        return $policy_mng === static::$MNG_BIG || $policy_mng === static::$MNG_SMALL;
    }
}