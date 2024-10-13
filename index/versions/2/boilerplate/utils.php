<?php

namespace ApiTwo;

class Utils {
    public static function getCurrentDate() {
        $date = explode(".", date("d.m.Y"));

        if (sizeof($date) !== 3) {
            return 0;
        }

        return mktime(0, 0, 0, $date[1], $date[0], $date[2]);
    }

    public static function dateToISO($date) {
        return @date("Y-m-d", $date);
    }

    public static function Date2String($date, $long = false) {
        return $date == 0 ? "" : @date($long ? "d.m.Y" : "j.n.Y", $date);
    }

    public static function getTimeToRace($race_date) {
        $diff = (int)($race_date - static::getCurrentDate());

        if ($diff > 0) {
            $diff = static::secondsToDays($diff);
        } else if ($diff < 0) {
            $diff = -1;
        }

        return $diff;
    }

    public static function getTimeToReg($entry_date) {
            // 90000 = 25 * 60 * 60 - posun terminu prihlasek o 2 hodiny navic, kvuli time() ktery vraci UTC je tam +25 hodin
            $entry_date += 90000;
            
            // puvodni pred pridanim 2 hodin navic
            //	$diff = (int)(($t_p) - GetCurrentDate());
            
            $diff = (int)(($entry_date) - time());
            if ($diff > 0) {
                $diff = static::secondsToDays($diff);
            } else if ($diff < 0) {
                $diff = -1;
            }
            
            return $diff;
    }

    public static function secondsToDays($seconds) {
        // 86400 = 24 * 60 * 60
        return floor($seconds / 86400);
    }

    public static function formatTimestamps(int ...$stamps) {
        // Examples:
        // formatTimestamps(1724596850, 1724683300) === "25.8.2024 - 26.8.2024"
        // formatTimestamps(1724596850, 0) === "25.8.2024 - ?"
        return join(" - ", array_map(function ($stamp) {
            return $stamp === 0 ? "?" : date("j.n.Y", $stamp);
        }, $stamps));
    }
}
