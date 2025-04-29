<?php

namespace ApiTwo;

require_once \Manifest::$libraries_directory . "/autoload_JWT.php";
require_once __DIR__ . "/database.php";
require_once __DIR__ . "/config.php";

use Jwt\JWT;

use Core\ApiException;
use Jwt\JWTException;

class Session {
    private static ?JWT $jwt = null;

    // initialized by Clubs::class
    public static string $clubname;

    public static bool $is_logged_in = false;
    public static bool $is_user_verified = false;
    public static int $user_id;
    public static string $device;

    /**
     * $policy = 0b000000
     *             │││││└─ $policy_news (1 bit)
     *             ││││└── $policy_regs (1 bit)
     *             │││└─── $policy_fin  (1 bit)
     *             ││└──── $policy_sadm (1 bit)
     *             │└───── $policy_mng_small (1 bit)
     *             └────── $policy_mng_big   (1 bit)
     */
    public static int $policy;

    public static int $MASK_ANYONE    = 0b000000;
    public static int $MASK_NEWS      = 0b000001;
    public static int $MASK_REGS      = 0b000010;
    public static int $MASK_FIN       = 0b000100;
    public static int $MASK_SADM      = 0b001000;
    public static int $MASK_MNG_SMALL = 0b010000;
    public static int $MASK_MNG_BIG   = 0b100000;
    public static int $MASK_ADM       = 0b111111;

    public static bool $policy_news; // editor
    public static bool $policy_regs; // registrator
    public static bool $policy_fin;  // financial
    public static bool $policy_sadm; // small admin
    public static bool $policy_adm;  // root admin
    public static bool $policy_mng_small; // small manager 
    public static bool $policy_mng_big;   // big manager

    public static function jwt() {
        // bypass constant expression contains invalid operations
        if (static::$jwt === null) {
            static::$jwt = new JWT(base64_decode(Config::$g_jwt_secret_key), "HS512");
        }

        return static::$jwt;
    }

    public static function all($bitmask) {
        // returns true when current user policy bitmask has enough rights described in bitmask
        // Session::policy = static::$MASK_ADM
        // Session::policy_acceptable(static::$MASK_NEWS | static::$MASK_FIN) // true
        return (static::$policy & $bitmask) === $bitmask;
    }

    public static function any($bitmask) {
        return static::$policy & $bitmask;
    }

    public static function login_from_token($array) {
        static::$user_id = (int)$array["user_id"];
        static::$device = $array["device"];
        static::pull_policy_by_user_id(static::$user_id);
        static::$is_logged_in = true;
    }

    public static function pull_policy_by_user_id($user_id) {
        $result = Database::fetch_assoc("SELECT * FROM " . Tables::$TBL_ACCOUNT . " WHERE `id_users` = ?", $user_id);

        if ($result === null) {
            return;
        }

        // admin is hardcoded
        static::$policy_adm = $result["id"] == Enums::$g_www_admin_id;

        if (static::$policy_adm) {
            static::$policy_news = true;
            static::$policy_regs = true;
            static::$policy_sadm = true;
            static::$policy_fin = true;
            static::$policy_mng_small = true;
            static::$policy_mng_big = true;
        } else {
            static::$policy_news = (bool)$result["policy_news"];
            static::$policy_regs = (bool)$result["policy_regs"];
            static::$policy_sadm = (bool)$result["policy_adm"];
            static::$policy_fin = (bool)$result["policy_fin"];
            static::$policy_mng_big = $result["policy_mng"] === Enums::$_MNG_BIG_INT_VALUE_;
            static::$policy_mng_small = $result["policy_mng"] === Enums::$_MNG_SMALL_INT_VALUE_ || static::$policy_mng_big;
        }

        static::$policy = static::$policy_news
            | (static::$policy_regs << 1)
            | (static::$policy_fin << 2)
            | (static::$policy_sadm << 3)
            | (static::$policy_mng_small << 4)
            | (static::$policy_mng_big << 5);
    }

    public static function get_access_token() {
        $header = request()->getHeader("Authorization");

        if (!$header) {
            throw new ApiException("This route requires authorization token.", 401);
        }

        // token starts with "Bearer "
        if (substr($header, 0, 7) !== "Bearer ") {
            throw new ApiException("Invalid format, expected 'Bearer ' prefix.", 401);
        }

        return substr($header, 7);

        // try {
        //     return static::jwt()->decode(substr($header, 7));
        // } catch (JWTException $error) {
        //     throw new ApiException($error->getMessage(), 401);
        // }
    }

    public static function generate_access_token(int $user_id, int $expiration, string $device) {
        return static::jwt()->encode([
            "user_id" => $user_id,
            "device" => $device,
            "exp" => $expiration,
        ]);
    }
}
