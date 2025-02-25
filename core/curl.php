<?php

class CurlRequest {
    private static function _convert_headers($headers) {
        return array_map(function ($key, $value) {
            return $key . ": " . $value;
        }, array_keys($headers), array_values($headers));
    }

    public static function post($server, $payload = "", $headers = []) {
        $response = new CurlResponse($server);

        $curl = curl_init();

        curl_setopt($curl, CURLOPT_URL, $server);
        curl_setopt($curl, CURLOPT_HTTPHEADER, static::_convert_headers($headers));
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

        $response->server = $server;
        $response->response = curl_exec($curl);
        $response->status_code = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $response->error = curl_error($curl);

        curl_close($curl);

        return $response;
    }

    public static function get($server, $payload = "", $headers = []) {
        $response = new CurlResponse($server);

        $curl = curl_init();

        curl_setopt($curl, CURLOPT_URL, $server);
        curl_setopt($curl, CURLOPT_HTTPHEADER, static::_convert_headers($headers));
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "GET");
        curl_setopt($curl, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

        $response->response = curl_exec($curl);
        $response->status_code = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $response->error = curl_error($curl);

        curl_close($curl);

        return $response;
    }
}

class CurlResponse {
    public $server;
    public $response;
    public $status_code;
    public $error;

    public function __construct($server) {
        $this->$server = $server;
    }

    function is_ok() {
        return !$this->is_error();
    }

    function is_error() {
        return $this->error || $this->status_code >= 400;
    }

    function raise_for_error() {
        if ($this->error) {
            throw new ApiException($this->error, 500);
            return;
        }

        if ($this->status_code >= 400) {
            throw new ApiException("curl: Server ($this->server) returned $this->status_code.", 500);
            return;
        }
    }

    function json() {
        $data = json_decode($this->response, true);

        if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
            throw new ApiException(json_last_error_msg(), 500);
        }

        return $data;
    }
}
