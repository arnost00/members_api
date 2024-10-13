<?php

namespace ApiTwo;

require_once \Manifest::$libraries_directory . "/autoload_JWT.php";
require_once __DIR__ . "/curl.php";
require_once __DIR__ . "/clubs.php";

use Jwt\JWT;
use Core\ApiException;

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
            static::$_adminsdk = file_get_contents(\Manifest::$firebase_adminsdk);
            static::$_adminsdk = json_decode(static::$_adminsdk, true, 512, JSON_THROW_ON_ERROR);
        }

        return static::$_adminsdk;
    }
    
    private static function jwt_token() {
        if (static::$_jwt_token === null) {
            // load jwt instance from private key
            $jwt = new JWT([
                static::adminsdk()["private_key_id"] => openssl_get_privatekey(static::adminsdk()["private_key"]),
            ], "RS256");
            
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
            $tokens = file_get_contents(\Manifest::$firebase_tokens);
            $tokens = json_decode($tokens, true, 512, JSON_THROW_ON_ERROR);

            if (isset($tokens["exp"]) && time() + static::$leeway < $tokens["exp"]) {
                // token is still valid
                static::$exp = $tokens["exp"];
                static::$_oauth2_token = $tokens["oauth2_token"];
            } else {
                // token is expired
                $curl = curl_init();

                curl_setopt($curl, CURLOPT_URL, "https://oauth2.googleapis.com/token");
                curl_setopt($curl, CURLOPT_POST, true);
                curl_setopt($curl, CURLOPT_POSTFIELDS, "grant_type=urn:ietf:params:oauth:grant-type:jwt-bearer&assertion=" . static::jwt_token());
                curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($curl, CURLOPT_HTTPHEADER, [
                    "Content-Type: application/x-www-form-urlencoded",
                ]);

                $response = curl_exec($curl);
                $response = json_decode($response, true, 512, JSON_THROW_ON_ERROR);

                static::check_api_error($response, $curl);

                curl_close($curl);

                // expiration is already set by static::jwt_token()
                static::$_oauth2_token = $response["access_token"];

                // save new token
                file_put_contents(\Manifest::$firebase_tokens, json_encode([
                    "oauth2_token" => static::$_oauth2_token,
                    "exp" => static::$exp,
                ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR), LOCK_EX);
            }
        }

        return static::$_oauth2_token;
    }

    private static function check_api_error($response, $curl) {
        if (curl_getinfo($curl, CURLINFO_HTTP_CODE) < 400) {
            return;
        }

        $message = $response["error"]["message"];
        $code = $response["error"]["code"];
        $status = $response["error"]["status"];
        $details = json_encode($response["error"]["details"], JSON_THROW_ON_ERROR);
        
        throw new ApiException("Google API:", 500, "Google API ($code): $status. $message ($details)");
    }

    public static function send_multicast($array) {
        throw new ApiException("Function in development. Waiting for curl >= 7.43.0 to support CURLPIPE_MULTIPLEX.", 500);
        /*
        // expects array payloads
        $multi = curl_multi_init();

        // reuse the connection
        curl_multi_setopt($multi, CURLMOPT_PIPELINING, CURLPIPE_MULTIPLEX);

        // create handle template
        $template = curl_init();
        curl_setopt($template, CURLOPT_URL, "https://fcm.googleapis.com/v1/projects/orientacny-beh/messages:send");
        curl_setopt($template, CURLOPT_POST, true);
        curl_setopt($template, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($template, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_2_0);
        curl_setopt($template, CURLOPT_HTTPHEADER, [
            "Content-Type: application/json",
            "Authorization: Bearer " . static::oauth2_token(),
        ]);

        $handles = [];
        foreach ($array as $payload) {
            $curl = curl_copy_handle($template);

            // add payload
            curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($payload, JSON_THROW_ON_ERROR));
            
            // save the handle
            curl_multi_add_handle($multi, $curl);
            $handles[] = $curl;
        }

        $active = null;

        // execute requests
        do {
            do {
                $status = curl_multi_exec($multi, $active);
            } while ($status === CURLM_CALL_MULTI_PERFORM);

            if (curl_multi_select($multi) === -1) usleep(1);
        } while ($active && $status === CURLM_OK);

        // get responses
        $result = [];
        foreach ($handles as $curl) {
            $response = curl_multi_getcontent($curl);
            
            curl_multi_remove_handle($multi, $curl);
            
            static::check_api_error($response, $curl);
            
            curl_close($curl);
            
            $result[] = $response;
        }

        curl_multi_close($multi);

        return $result;
        */
    }
    
    public static function send($payload) {
        $curl = curl_init();
        
        curl_setopt($curl, CURLOPT_URL, "https://fcm.googleapis.com/v1/projects/orientacny-beh/messages:send");
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HTTPHEADER, [
            "Content-Type: application/json",
            "Authorization: Bearer " . static::oauth2_token(),
        ]);
        
        $response = curl_exec($curl);
        $response = json_decode($response, true, 512, JSON_THROW_ON_ERROR);

        static::check_api_error($response, $curl);
        
        curl_close($curl);
        
        return $response;
    }

    public static function topics($token) {
        // DEBUG //
        $curl = curl_init();
    
        curl_setopt($curl, CURLOPT_URL, "https://iid.googleapis.com/iid/info/" . $token . "?details=true");
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "GET");
        curl_setopt($curl, CURLOPT_POSTFIELDS, "");
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HTTPHEADER, [
            "Content-Type: application/json",
            "Authorization: Bearer " . static::oauth2_token(),
        ]);

        $response = curl_exec($curl);

        curl_close($curl);

        echo $response;

        // echo $curl->server;

        // if ($curl->is_error()) {
        //     throw new ApiException($curl->response, 400);
        //     return;
        // }

        // echo $curl->response;
    }
}

class NotifyContent {
    /**
     * No action happens.
     */
    public static string $EVENT_BASIC = "0";

    /**
     * Should be used with `race_id` to open the correct race.
     */
    public static string $EVENT_RACE = "1";

    public array $content = [];

    public function __construct($title = null, $body = null) {
        if ($title !== null) $this->title($title);
        if ($body !== null) $this->body($body);
    }

    public function clear() {
        $this->content = [];
    }

    public function export() {
        if (!isset($this->content["message"]["notification"]["title"])) {
            throw new ApiException("FCM title is not set.", 500);
        }
        
        if (!isset($this->content["message"]["notification"]["body"])) {
            throw new ApiException("FCM body is not set.", 500);
        }

        if (!isset($this->content["message"]["token"]) && !isset($this->content["message"]["topic"])) {
            throw new ApiException("FCM recipient is not set.", 500);
        }

        return json_encode($this->content, JSON_THROW_ON_ERROR);
    }

    /**
     * Common fields
     * https://firebase.google.com/docs/cloud-messaging/concept-options#when-to-use-common-fields
     */

    public function title($value) {
        $this->content["message"]["notification"]["title"] = $value;
    }

    public function body($value) {
        $this->content["message"]["notification"]["body"] = $value;
    }

    public function data($value) {
        $this->content["message"]["data"] = $value;
    }

    /**
     * Device identification
     */

    public function token($value) {
        if (isset($this->content["message"]["topic"])) {
            throw new ApiException("FCM topic is already set.", 500);
        }

        $this->content["message"]["token"] = $value;
    }

    public function topic($value) {
        if (isset($this->content["message"]["token"])) {
            throw new ApiException("FCM token is already set.", 500);
        }

        $this->content["message"]["topic"] = $value;
    }

    /**
     * Events
     */

    public function custom_event($event, $value) {
        $this->content["message"]["data"]["event"] = static::$EVENT_RACE;
        $this->content["message"]["data"]["value"] = (string)$value;
    }

    /**
     * Customization
     * https://firebase.google.com/docs/cloud-messaging/send-message#example-notification-message-with-platform-specific-delivery-options
     */

    public function image($value) {
        $this->content["message"]["notification"]["image"] = $value;
    }

    public function icon($value) {
        $this->content["message"]["android"]["notification"]["icon"] = $value;
    }
}