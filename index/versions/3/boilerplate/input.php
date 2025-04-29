<?php

namespace ApiTwo;

require_once __DIR__ . "/session.php";

use Core\ApiException;
use Core\ApiUndefinedKeyException;

class Input {
    // placeholders
    public static $FILTER_PHONE = "/^\+?[\d\s]+$/";
    public static $FILTER_DEVICE = "/^(?:[0-9a-f]{16}|[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12})$/";
    public static $FILTER_UUID = "/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/";
    public static $FILTER_ISO_DATE;
    public static $FILTER_BOOL;
    public static $FILTER_UINT;
    public static $FILTER_INT;
    public static $FILTER_NULL;

    public static $data = null;

    // non-static properties
    public array $collected = [];
    public $default_required;
    public $default_nullable;

    public static function validate($value, int | string | callable $filter) {
        switch (true) {
            case is_int($filter):
                return filter_var($value, $filter) !== false;
            case is_string($filter):
                return is_string($value) && preg_match($filter, $value);
            case is_callable($filter):
                return call_user_func($filter, $value);
            default:
                throw new ApiException("Internal server error.", 500, "Unrecognized filter type.");
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
        static::$FILTER_NULL = function ($test) {
            return $test === "" || $test === null;
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

    public static function exists(string $key) {
        // https://stackoverflow.com/a/9522522/14900791
        return isset(static::$data[$key]) || array_key_exists($key, static::$data);
    }

    public static function key(string $key, bool $required = true, mixed $default = null, int | string | callable | null $filter = null, bool $nullable = false) {
        if (static::exists($key)) {
            $value = static::$data[$key];

            if ($filter === null || static::validate($value, $filter)) {
                return $value;
            }

            if ($nullable && static::validate($value, static::$FILTER_NULL)) {
                return $default;
            }

            throw new ApiException("Invalid value for '$key'.", 400);
        }

        if ($required) {
            throw new ApiUndefinedKeyException($key);
        }

        return $default;
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

    public function __construct(bool $required = false, bool $nullable = false) {
        $this->default_required = $required;
        $this->default_nullable = $nullable;
    }

    public function add($key, ?string $alias = null, ?bool $required = null, mixed $default = null, ?int $permission = null, bool $access = true, int | string | callable | null $filter = null, ?bool $nullable = null) {
        $required ??= $this->default_required;
        $nullable ??= $this->default_nullable;

        if (!$access) {
            if ($required) {
                throw new ApiException("User has not access to change '$key'.", 403, "User has no access to change '$key'. Probably because of insufficient permission.");
            }

            // no access, silently exit
            return;
        }

        if ($permission !== null && !Session::all($permission)) {
            if ($required) {
                throw new ApiException("User has not permission to change '$key'.", 403, "User has no permission to change '$key' under '$permission' mask.");
            }

            // no permission, silently exit
            return;
        }

        // do not collect if key does not exists and is not required
        if (!$required && !static::exists($key)) {
            return;
        }

        $this->collected[$alias ?? $key] = static::key($key, $required, $default, $filter, $nullable);
    }

    public function collect() {
        return $this->collected;
    }
}

Input::init();
