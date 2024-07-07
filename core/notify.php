<?php

namespace Core;

require_once __DIR__ . "/curl.php";

use Manifest\Manifest;
use Jwt\JWT;
use CurlRequest;
use Pecee\SimpleRouter\Exceptions\HttpException;

class Notifications {
    private static $aud = "https://oauth2.googleapis.com/token";
    private static $scope = "https://www.googleapis.com/auth/firebase.messaging";
    private static $leeway = 120;
    private static $ttl = 3600;
    private static $exp;
    private static $_adminsdk;
    private static $_jwt_token;
    private static $_oauth2_token;

    private static function adminsdk() {
        if (static::$_adminsdk === null) {
            static::$_adminsdk = file_get_contents(Manifest::$firebase_adminsdk);
            static::$_adminsdk = json_decode(static::$_adminsdk, true);
        }

        return static::$_adminsdk;
    }
    
    private static function jwt_token() {
        if (static::$_jwt_token === null) {
            // load jwt instance from private key
            $jwt = new JWT(openssl_get_privatekey(static::adminsdk()["private_key"]), "RS256");
            
            $iat = time();
            
            // expiration must be saved, required by static::oauth2_token()
            static::$exp = $iat + static::$ttl;

            // generate token
            static::$_jwt_token = $jwt->encode([
                "iss" => static::adminsdk()["client_email"],
                "sub" => static::adminsdk()["client_email"],
                "aud" => static::$aud,
                "iat" => $iat,
                "exp" => static::$exp,
                "scope" => static::$scope,
            ], [
                "kid" => static::adminsdk()["private_key_id"],
            ]);
        }
        
        return static::$_jwt_token;
    }
    
    private static function oauth2_token() {
        if (static::$_oauth2_token === null) {
            $tokens = file_get_contents(Manifest::$firebase_tokens);
            $tokens = json_decode($tokens, true);

            if (isset($tokens["exp"]) && time() + static::$leeway < $tokens["exp"]) {
                // token is still valid
    
                static::$exp = $tokens["exp"];
                static::$_oauth2_token = $tokens["oauth2_token"];
            } else {
                // token is expired

                $form_data = "grant_type=urn:ietf:params:oauth:grant-type:jwt-bearer&assertion=" . static::jwt_token();
                $curl = CurlRequest::post("https://oauth2.googleapis.com/token", $form_data, [
                    "Content-Type" => "application/x-www-form-urlencoded",
                ]);
                $curl->raise_for_error();

                // expiration is already set by static::jwt_token()
                static::$_oauth2_token = $curl->json()["access_token"];

                // save new token
                file_put_contents(Manifest::$firebase_tokens, json_encode([
                    "oauth2_token" => static::$_oauth2_token,
                    "exp" => static::$exp,
                ], JSON_PRETTY_PRINT), LOCK_EX);
            }
        }

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

// Notification manual
// -------------------
// Notifications::post(NotificationContent::create("Hello, World!", "Simple content."))