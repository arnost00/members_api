<?php

namespace ApiTwo;

require_once \Manifest::$libraries_directory . "/autoload_JWT.php";
require_once __DIR__ . "/database.php";
require_once __DIR__ . "/config.php";

use Jwt\JWT;

use Core\ApiException;

class Session {
    private static ?JWT $jwt = null;

    // initialized by Clubs::class
    public static string $clubname;

    public static bool $is_logged_in;
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

    private static function jwt() {
        // bypass constant expression contains invalid operations
        if (static::$jwt === null) {
            static::$jwt = new JWT(base64_decode(Config::$g_jwt_secret_key), "HS512");
        }

        return static::$jwt;
    }

    public static function has($bitmask) {
        // returns true when current user policy bitmask has enough rights described in bitmask
        // Session::policy = static::$MASK_ADM
        // Session::policy_acceptable(static::$MASK_NEWS | static::$MASK_FIN) // true
        return (static::$policy & $bitmask) === $bitmask;
    }

    public static function init() {
        $data = static::get_access_token();
        
        static::$is_logged_in = $data !== null;
                
        if (static::$is_logged_in) {
            if (!Input::validate($data["user_id"], Input::$FILTER_UINT)) {
                throw new ApiException("Invalid token.", 401, "Invalid user_id inside token.");
            }

            static::$user_id = (int)$data["user_id"];
            static::pull_policy_by_user_id(static::$user_id);
        }
    }

    public static function pull_policy_by_user_id($user_id) {
        $result = Database::fetch_assoc("SELECT * FROM " . Tables::$TBL_ACCOUNT . " WHERE `id_users` = ?", $user_id);

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

        // authorization header not found
        if (!$header) {
            return null;
        }

        // extract "Bearer "
        if (substr($header, 0, 7) !== "Bearer ") {
            throw new ApiException("Invalid format, expected 'Bearer ' prefix.", 401);
        }
        
        return static::jwt()->decode(substr($header, 7));
    }

    public static function generate_access_token($user_id, $expiration) {
        return static::jwt()->encode([
            "user_id" => $user_id,
            "exp" => $expiration,
        ]);
    }
}