<?php

namespace ApiTwo;

require_once __DIR__ . "/session.php";

use Core\ApiException;
use Core\ApiUndefinedKeyException;

class Input {
    // placeholders
    public static $FILTER_PHONE = "/^\+?\d+$/";
    public static $FILTER_DEVICE = "/^(?:[0-9a-f]{16}|[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12})$/";
    public static $FILTER_UUID = "/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/";
    public static $FILTER_ISO_DATE;
    public static $FILTER_BOOL;
    public static $FILTER_UINT;
    public static $FILTER_INT;

    public static $data = null;

    // non-static properties
    public array $collected = [];
    public $default_required;

    public static function validate($value, int | string | callable $filter) {
        switch (true) {
            case is_int($filter): return filter_var($value, $filter) !== false;
            case is_string($filter): return is_string($value) && preg_match($filter, $value);
            case is_callable($filter): return call_user_func($filter, $value);
            default: throw new ApiException("Internal server error.", 500, "Unrecognized filter type.");
        }
    }

    public static function init() {
        if (static::$data !== null) return;

        // initialize filter functions
        static::$FILTER_ISO_DATE = function ($test) { 
            return preg_match("/^(\d+)-(\d+)-(\d+)$/", $test, $groups) ? checkdate($groups[2], $groups[3], $groups[1]) : false;
        };
        static::$FILTER_BOOL = function ($test) {
            return is_bool($test) || $test === "true" || $test === "false";
        };
        static::$FILTER_UINT = function ($test) {
            return (is_int($test) && $test >= 0) || (is_string($test) && preg_match("/^\d+$/", $test));
        };
        static::$FILTER_INT = function ($test) {
            return is_int($test) || (is_string($test) && preg_match("/^[+-]?\d+$/", $test));
        };

        // load request data
        static::$data = [];
        static::$data += $_GET;
        static::$data += $_POST;

        $json = json_decode(file_get_contents("php://input"), true);

        if (json_last_error() === JSON_ERROR_NONE) {
            static::$data += $json;
        }
    }

    public static function key(string $key, bool $required = true, mixed $default = null, int | string | callable | null $filter = null) {
        $found = isset(static::$data[$key]);

        if ($required && !$found) {
            throw new ApiUndefinedKeyException($key);
        }

        $value = static::$data[$key];

        if ($filter !== null && !static::validate($value, $filter)) {
            throw new ApiException("Invalid value for '$key'.", 400);
        }

        return $found ? $value : $default;
    }

    public static function keys(array | null $keys = null, bool $required = true, mixed $default = null) {
        if ($keys === null) {
            return static::$data;
        }

        // return only defined keys
        $result = [];
        foreach ($keys as $key) {
            $result[$key] = static::key($key, $required, $default);
        }
        return $result;
    }

    public function __construct(bool $required = false) {
        $this->default_required = $required;
    }

    public function add($key, ?string $alias = null, ?bool $required = null, mixed $default = null, ?int $permission = null, int | string | callable | null $filter = null) {
        $required = $required ?? $this->default_required;
        $value = static::key($key, $required, $default);

        if ($permission !== null && !Session::has($permission)) {
            if ($required) {
                throw new ApiException("User has not permission to change '$key'.", 401, "User has no permission to change '$key' under '$permission' mask.");
            }
            
            // no permission, do not collect result
            return;
        }
        
        if ($filter !== null && !static::validate($value, $filter)) {
            // always raise error on invalid value
            throw new ApiException("Invalid value for '$key'.", 400);
        }

        $this->collected[$alias ?? $key] = $value;
    }

    public function collect() {
        return $this->collected;
    }
}

Input::init();