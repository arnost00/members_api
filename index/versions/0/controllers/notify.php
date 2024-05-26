<?php

namespace Controllers;

use Manifest\Manifest;
use Core\Api;
use Jwt\JWT;
use CurlRequest;
use CurlResponse;
use Pecee\SimpleRouter\Exceptions\HttpException;

class Notifications {
    public static $leeway = 120;
    public static $ttl = 3600;
    public static $exp;

    private static $aud = "https://oauth2.googleapis.com/token";
    private static $scope = "https://www.googleapis.com/auth/firebase.messaging";

    private static $_secret;
    private static $_jwt_token;
    private static $_oauth2_token;

    private static function secret() {
        if (static::$_secret === null) {
            if (Manifest::$firebase_config === null) {
                throw new HttpException("Notifications are disabled.", 500);
                return;
            }
            
            static::$_secret = file_get_contents(Manifest::$firebase_config);
            static::$_secret = json_decode(static::$_secret, true);
        }

        return static::$_secret;
    }

    private static function jwt_token() {
        if (static::$_jwt_token === null) {
            $jwt = new JWT(openssl_get_privatekey(static::secret()["private_key"]), "RS256");

            // compute iat and exp time
            $iat = time();
            static::$exp = $iat + static::$ttl;

            $payload = [
                "iss" => static::secret()["client_email"],
                "sub" => static::secret()["client_email"],
                "aud" => static::$aud,
                "iat" => $iat,
                "exp" => static::$exp,
                
                "scope" => static::$scope,
            ];
            
            $headers = [
                "kid" => static::secret()["private_key_id"],
            ];

            static::$_jwt_token = $jwt->encode($payload, $headers);
        }
        
        return static::$_jwt_token;
    }

    private static function oauth2_token() {
        // if there is no cached token or cached token expired, look into database
        if (static::$_oauth2_token === null || time() + static::$leeway >= static::$exp) {
            $db_token_exp = Api::database()->fetch_assoc("SELECT value FROM `config` WHERE param = 'fcm_token_exp';")["value"];
            // echo $db_token_exp;
            // if token in database expired, make a new and push it into database
            if (time() + static::$leeway >= $db_token_exp) {
                $payload = "grant_type=urn:ietf:params:oauth:grant-type:jwt-bearer&assertion=" . static::jwt_token();
                // echo "generating new token";
                $curl = CurlRequest::post("https://oauth2.googleapis.com/token", $payload, [
                    "Content-Type" => "application/x-www-form-urlencoded",
                ]);
                $curl->raise_for_error();
        
                static::$_oauth2_token = $curl->json()["access_token"];
                // echo "got token";
                Api::database()->query("UPDATE `config` SET value = ? WHERE param = 'fcm_token_value';", static::$_oauth2_token);
                Api::database()->query("UPDATE `config` SET value = ? WHERE param = 'fcm_token_exp';", static::$exp);
            } else {
                // token is found, cache and return it
                static::$_oauth2_token = Api::database()->fetch_assoc("SELECT value FROM `config` WHERE param = 'fcm_token_value';")["value"];
                static::$exp = $db_token_exp;
                // echo "token found, expiring : " . $db_token_exp;
            }
        }
        // echo "our token", static::$_oauth2_token;
        
        return static::$_oauth2_token;
    }

    public static function send($payload) {
        $curl = CurlRequest::post("https://fcm.googleapis.com/v1/projects/orientacny-beh/messages:send", json_encode($payload), [
            "Content-Type" => "application/json",
            "Authorization" => "Bearer " . static::oauth2_token(),
        ]);

        if ($curl->is_error()) {
            throw new HttpException($curl->json()["error"]["message"], 400);
            return;
        }

        response()->json($curl->json());
    }

    public static function topics($token) {
        // DEBUG //
        $curl = CurlRequest::get("https://iid.googleapis.com/iid/info/$token?details=true", "", [
            "Content-Type" => "application/json",
            "Authorization" => "Bearer " . static::oauth2_token(),
        ]);

        echo $curl->server;

        // if ($curl->is_error()) {
        //     throw new HttpException($curl->response, 400);
        //     return;
        // }

        echo $curl->response;
    }
}

class NotificationContent {
    // EVENT_BASIC
    // simple event for basic messages
    static $EVENT_BASIC = 1;
    
    // EVENT_RACE_CHANGED
    // race is changed / added / removed
    // - basic info
    // - date / entries
    // - cancelled
    static $EVENT_RACE_CHANGED = 2;

    static $ICON_DEFAULT = "logo";
    static $COLOR_DEFAULT = "#f76d1c";
    
    // max 1 MB
    static $IMAGE_DEFAULT = "https://members.eob.cz/api/favicon.png";

    public static function create($title, $body, $event=null, $data=null, $icon=null, $image=null) {
        return [
            "message" => [
                "topic" => request()->current->clubname,
                "notification" => [
                    "title" => $title,
                    "body" => $body,
                ],
                "android" => [
                    "notification" => [
                        // "icon" => $icon ?? static::$ICON_DEFAULT,
                        "image" => $image,
                        // "color" => static::$COLOR_DEFAULT,
                    ],
                ],
                "data" => array_merge([
                    "event" => (string)($event ?? static::$EVENT_BASIC),
                ], $data ?? []),
            ],
        ];
    }

    public static function race_changed($race_id) {
        $output = Api::database()->fetch_assoc("SELECT `nazev`, `poznamka` FROM `" . TBL_RACE . "` WHERE `id` = ? LIMIT 1", $race_id);

        if ($output === null) {
            throw new HttpException("The race you are looking for does not exists.", 404);
            return;
        }

        $title = "TEST::ZMENA v " . $output["nazev"];
        $note = "TEST::Nastala zmena. " . $output["poznamka"];

        return static::create($title, $note, static::$EVENT_RACE_CHANGED, ["race_id" => (string)$race_id]);
    }
}

// Notification manual
// -------------------
// Notifications::post(NotificationContent::create("Hello, World!", "Simple content."))