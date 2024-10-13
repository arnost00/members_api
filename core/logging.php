<?php

namespace Core;

class Logging {
    // a file per day, one folder for each month, one folder for each year.

    public static $LEVEL_INFO = "INFO";
    public static $LEVEL_WARNING = "WARNING";
    public static $LEVEL_ERROR = "ERROR";
    public static $LEVEL_FATAL = "FATAL";

    // a four digit representation of a year
    public static $year_format = "Y";
    // a full textual representation of a month (January through December)
    public static $month_format = "F";
    // example: 2013-04-12
    public static $file_format = "Y-m-d";
    // example: 2013-04-12T15:52:01+00:00
    public static $stamp_format = DATE_W3C;

    public static function write($content) {
        // checks for year directory
        $path = \Manifest::$logging_directory . "/" . date(static::$year_format) . "/";
        is_dir($path) || mkdir($path);

        // checks for month directory
        $path .= date(static::$month_format) . "/";
        is_dir($path) || mkdir($path);

        // add filename to path
        $path .= date(static::$file_format) . ".log";

        return file_put_contents($path, $content . PHP_EOL, FILE_APPEND | LOCK_EX);
    }

    public static function parse($filepath) {
        $content = file_get_contents($filepath);
        $loglines = [];

        foreach (explode(PHP_EOL, $content) as $logline) {
            if (!$logline) continue;

            [$timestamp, $identifier, $level, $data] = explode(" - ", $logline, 4);
            [$clubname, $ip] = explode("::", $identifier, 2);
    
            $loglines[] = [
                "timestamp" => $timestamp,
                "clubname" => $clubname,
                "ip" => $ip,
                "level" => $level,
                "data" => $data,
            ];
        }
        
        return $loglines;
    }

    public static function custom($text, $level) {
        // <DATE> - <IP>::<URI> - <LEVEL> - <JSON OR TEXT>
        $content = date(static::$stamp_format) . " - " . $_SERVER["REMOTE_ADDR"] . "::" . $_SERVER["REQUEST_URI"] . " - " . $level . " - " . $text;

        static::write($content);
    }

    public static function info($text) {
        return static::custom($text, static::$LEVEL_INFO);
    }

    public static function warning($text) {
        return static::custom($text, static::$LEVEL_WARNING);
    }
    
    public static function error($text) {
        return static::custom($text, static::$LEVEL_ERROR);
    }

    public static function fatal($text) {
        return static::custom($text, static::$LEVEL_FATAL);
    }

    public static function exception($exception) {
        return static::error(json_encode([
            "message" => $exception->getMessage(),
            "code" => $exception->getCode(),
            "file" => $exception->getFile(),
            "trace" => $exception->getTrace(),
        ]));
    }
}