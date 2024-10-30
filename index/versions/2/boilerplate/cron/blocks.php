<?php

namespace ApiTwo;

require_once __DIR__ . "/../../boilerplate/database.php";
require_once __DIR__ . "/../../boilerplate/notify.php";
require_once __DIR__ . "/../../boilerplate/config.php";
require_once __DIR__ . "/../../boilerplate/utils.php";

class ContentNewsBlock {
    public static $data;

    public array $selected = [];

    public function __construct($subscriber) {
        if (static::$data === null) {
            static::$data = Database::fetch_assoc_all("SELECT * FROM " . Tables::$TBL_NEWS . " WHERE modify_flag > 0 ORDER BY datum");
        }

        if ($subscriber["active_news"]) {
            $this->selected = static::$data;
        }
    }

    public function is_empty() {
        return empty(static::$data);
    }

    public function export_mail() {
        $content = "";

        if (!empty($this->selected)) {
            $content .= "<p>Přidána nebo změněna novinka:</p><ul>";
            foreach ($this->selected as ["nadpis" => $title, "datum" => $date, "internal" => $internal, "text" => $text]) {
                $text = $internal ? "<i>interní novinka</i>" : "\n$text";
                $content .= "<li>" . Utils::formatTimestamps($date) . " / $title / $text</li>";
            }
            $content .= "</ul>";
        }

        return $content;
    }

    public function export_notify() {
        $content = new NotifyContent();
        $queue = [];

        foreach ($this->selected as ["id" => $value, "nadpis" => $title, "text" => $text]) {
            $content->clear();
            $content->title("Přidána nebo změněna novinka: " . $title);
            $content->body($text);
            // TODO: set event
            $queue[] = clone $content;
        }

        return $queue;
    }
}

class ContentRacesBlock {
    public static $data;
    public static $current_date;

    public array $select_reminder_registration = [];
    public array $select_reminder_registration_expired = [];
    public array $select_changes = [];

    public function __construct($subscriber) {
        if (static::$data === null) {
            static::$current_date = Utils::getCurrentDate();
            static::$data = Database::fetch_assoc_all("SELECT * FROM " . Tables::$TBL_RACE . " WHERE datum >= ? AND (prihlasky > 0 OR modify_flag > 0) ORDER BY datum", static::$current_date);
        }

        // notify about current registration deadline
        if ($subscriber["active_tf"]) {
            foreach (static::$data as $child) {
                $registration = static::getCurrentRegistration($child, static::$current_date);
                $matched_type = static::matchRaceType($child["typ"], $subscriber["type"]);
                $matched_subtype = static::matchRaceSubType($child["zebricek"], $subscriber["sub_type"]);

                if ($registration[0] === 0 || !$matched_type || !$matched_subtype) {
                    continue;
                }

                $days_delta = (int)(abs($registration[0] - static::$current_date) / 86400);

                if ($days_delta !== $subscriber["daysbefore"]) {
                    continue;
                }

                $this->select_reminder_registration[] = $child;
            }
        }

        // notify about deadline expiration
        if ($subscriber["active_rg"]) {
            foreach (static::$data as $child) {
                $registration = static::getOldestRegistration($child, static::$current_date);

                if ($registration[0] === 0) {
                    continue;
                }

                $days_delta = (int)(abs($registration[0] - static::$current_date) / 86400);

                if ($days_delta !== 1) {
                    continue;
                }

                $this->select_reminder_registration_expired[] = $child;
            }
        }

        // subscriber wants changes
        if ($subscriber["active_ch"]) {
            foreach (static::$data as $child) {
                if (($subscriber["ch_data"] & $child["modify_flag"]) !== 0) {
                    $this->select_changes[] = $child;
                }
            }
        }
    }

    public function is_empty() {
        return empty($this->select_reminder_registration) && empty($this->select_reminder_registration_expired) && empty($this->select_changes);
    }

    public function export_mail() {
        $content = "";

        if (!empty($this->select_reminder_registration)) {
            $content .= "<p>Blíží se jeden nebo více termínu přihlášek:</p><ul>";
            foreach ($this->select_reminder_registration as $child) {
                $content .= "<li>" . static::formatRace($child) . "</li>";
            }
            $content .= "</ul>";
        }

        if (!empty($this->select_reminder_registration_expired)) {
            $content .= "<p>Právě skončil interní termín přihlášek:</p><ul>";
            foreach ($this->select_reminder_registration_expired as $child) {
                $content .= "<li>" . static::formatRace($child) . "</li>";
            }
            $content .= "</ul>";
        }

        if (!empty($this->select_changes)) {
            $content .= "<p>Změna v závodech:</p><ul>";
            foreach ($this->select_changes as $child) {
                $content .= "<li>" . static::formatRace($child) . " <i>(změny: " . static::formatChanges($child) . ")</i></li>";
            }
            $content .= "</ul>";
        }

        return $content;
    }

    public function export_notify() {
        $content = new NotifyContent();
        $queue = [];

        foreach ($this->select_reminder_registration as $child) {
            $content->clear();
            $content->title("Blíží se termín přihlášky na " . $child["nazev"]);
            $content->body(static::formatRace($child));
            $content->custom_event(NotifyContent::$EVENT_RACE, $child["id"]);
            $queue[] = clone $content;
        }

        foreach ($this->select_reminder_registration_expired as $child) {
            $content->clear();
            $content->title("Právě skončil interní termín přihlášky na " . $child["nazev"]);
            $content->body(static::formatRace($child));
            $content->custom_event(NotifyContent::$EVENT_RACE, $child["id"]);
            $queue[] = clone $content;
        }

        foreach ($this->select_changes as $child) {
            $content->clear();
            $content->title("Změna v závode " . $child["nazev"]);
            $content->body(static::formatChanges($child));
            $content->custom_event(NotifyContent::$EVENT_RACE, $child["id"]);
            $queue[] = clone $content;
        }

        return $queue;
    }

    public static function formatChanges($child) {
        $reasons = [];

        if (($child["modify_flag"] & Enums::$g_modify_flag[0]["id"]) !== 0) {
            $reasons[] = "termín přihlášek";
        }

        if (($child["modify_flag"] & Enums::$g_modify_flag[1]["id"]) !== 0) {
            $reasons[] = "závod přidán";
        }

        if (($child["modify_flag"] & Enums::$g_modify_flag[2]["id"]) !== 0) {
            $reasons[] = "termin závodu";
        }

        return join(", ", $reasons);
    }

    public static function formatRace($child) {
        $date = $child["vicedenni"]
        ? Utils::formatTimestamps($child["datum"], $child["datum2"])
        : Utils::formatTimestamps($child["datum"]);
                
        $title = $child["nazev"];
        $club = $child["oddil"];

        $registration = static::getCurrentRegistration($child, static::$current_date);
        
        $termin = Utils::formatTimestamps($registration[0]);
        if ($registration[1] !== 0) $termin .= " / " . $registration[1];

        $cancelled = $child["cancelled"] ? " / ZRUŠENO" : "";

        return "$date / $title / $club [$termin]" . $cancelled;
    }

    public static function matchRaceType($value, $bitflags) {
        // finds id of $value in Enums::$g_racetype, then checks id is in $bitflags
        foreach (Enums::$g_racetype as $item) {
            if ($value == $item["enum"]) {
                return ($bitflags & $item["id"]) !== 0;
            }
        }
        return false;
    }

    public static function matchRaceSubType($value, $bitflags) {
        // checks at least one of Enums::$g_zebricek has common
        // if none are selected, select all
        if ($value === 0 || $bitflags === 0) {
            return true;
        }

        return ($value & $bitflags) !== 0;
    }

    public static function getCurrentRegistration($row, $date) {
        // returns [<current registration unix timestamp>, <registration number>]
        if ($row['prihlasky'] == 1) {
            return $row['prihlasky1'] >= $date ? [$row['prihlasky1'], 0] : [0, 0];
        }
        if ($row['prihlasky'] >= 1 && $row['prihlasky1'] >= $date ) {
            return [$row['prihlasky1'], 1];
        }
        if ($row['prihlasky'] >= 2 && $row['prihlasky2'] >= $date ) {
            return [$row['prihlasky2'], 2];
        }
        if ($row['prihlasky'] >= 3 && $row['prihlasky3'] >= $date ) {
            return [$row['prihlasky3'], 3];
        }
        if ($row['prihlasky'] >= 4 && $row['prihlasky4'] >= $date ) {
            return [$row['prihlasky4'], 4];
        }
        if ($row['prihlasky'] >= 5 && $row['prihlasky5'] >= $date ) {
            return [$row['prihlasky5'], 5];
        }
        return [0, 0];
    }

    public static function getOldestRegistration($row, $date) {
        // returns [<oldest registration unix timestamp>, <registration number>]
        if ($row['prihlasky'] == 1) {
            return $row['prihlasky1'] < $date ? [$row['prihlasky1'], 0] : [0, 0];
        }
    
        if ($row['prihlasky'] >= 5 && $row['prihlasky5'] < $date) {
            return [$row['prihlasky5'], 5];
        }
        if ($row['prihlasky'] >= 4 && $row['prihlasky4'] < $date) {
            return [$row['prihlasky4'], 4];
        }
        if ($row['prihlasky'] >= 3 && $row['prihlasky3'] < $date) {
            return [$row['prihlasky3'], 3];
        }
        if ($row['prihlasky'] >= 2 && $row['prihlasky2'] < $date) {
            return [$row['prihlasky2'], 2];
        }
        if ($row['prihlasky'] >= 1 && $row['prihlasky1'] < $date) {
            return [$row['prihlasky1'], 1];
        }
        
        return [0, 0];
    }
}

class ContentFinanceBlock {
    public static $data;
    public static $data_negative_table;

    public string $message = "";
    public bool $show_negative_table;

    public function __construct($subscriber) {
        if (!Config::$g_enable_finances) {
            return;
        }

        if (static::$data === null) {
            static::$data = static::getAllUsersCurrentBalance();
            static::$data_negative_table = [];

            foreach (static::$data as $child) {
                if ($child["fin_total"] < 0) {
                    static::$data_negative_table[] = $child;
                }
            }
        }

        if ($subscriber["active_fin"]) {
            // @ is used to prevent code duplication
            $table = @static::$data[$subscriber["id_user"]];

            if (isset($table) && !is_array($table)) {
                if (($subscriber["fin_type"] & Enums::$g_fin_mail_flag["1"]["id"]) !== 0 && $table["fin_total"] < 0) {
                    $this->message = "Tvůj zůstatek na oddílovém účtu poklesl do záporu, a činí " . $table["fin_total"] . ",-";
                } else if (($subscriber["fin_type"] & Enums::$g_fin_mail_flag[0]["id"]) != 0 && $table["fin_total"] < $subscriber["fin_limit"]) {
                    $this->message = "Tvůj zůstatek na oddílovém účtu poklesl pod definovanou hranici, a činí " . $table["fin_total"] . ",-";
                }
            }
        }
        
        $this->show_negative_table = $subscriber["active_finf"];
    }

    public function is_empty() {
        return !Config::$g_enable_finances || ($this->message === "" && !$this->show_negative_table);
    }
    
    public function export_mail() {
        $content = "";

        if ($this->message) {
            $content .= "<p>" . $this->message . "</p>";
        }

        if ($this->show_negative_table) {
            $content .= "<p>Členové se záporným zůstatkem na účtu:</p><ul>";
            
            foreach (static::$data_negative_table as $child) {
                $content .= "<li>" . $child["jmeno"] . " " . $child["prijmeni"] . " (" . $child["fin_total"] . ")</li>";
            }
            
            $content .= "</ul>";
        }

        return $content;
    }

    public function export_notify() {
        $queue = [];
        
        if ($this->message) {
            $queue[] = new NotifyContent($this->message);
        }

        // finance table probably will not be implemented
        
        return $queue;
    }

    public static function getAllUsersCurrentBalance() {
        $result = Database::query("SELECT u.id, hidden, prijmeni,jmeno, ifnull(f.sum_amount,0) sum_amount, (n.amount+f.sum_amount) total_amount, u.chief_pay FROM " . Tables::$TBL_USER . " u 
            left join (select sum(fin.amount) sum_amount, id_users_user from ".Tables::$TBL_FINANCE." fin where (fin.storno is null) group by fin.id_users_user) f on u.id=f.id_users_user 
            left join (select ui.chief_pay payer_id, ifnull(sum(fi.amount),0) amount from ".Tables::$TBL_USER." ui 
            left join " . Tables::$TBL_FINANCE . " fi on fi.id_users_user = ui.id where ui.chief_pay is not null and (fi.storno is null or fi.storno != 1) group by ui.chief_pay) n on u.id=n.payer_id 
            left join " . Tables::$TBL_FINANCE_TYPES . " ft on ft.id = u.finance_type
            group by u.id ORDER BY u.`sort_name` ASC;");
        
        $data = [];

        while ($row = $result->fetch_assoc()) {
            if (($row["chief_pay"] > 0 && $row["chief_pay"]<>$row["id"]) || $row["hidden"]) {
                // pokud za nej plati nekdo jiny, vubec nebrat v potaz !
                // nebo pokud je skryt
            } else {
                $data[$row["id"]] = $row;
                $data[$row["id"]]["fin_total"] = $row["sum_amount"];
                
                if ($row["total_amount"] != null) {
                    $data[$row["id"]]["fin_total"] = $row["total_amount"];
                }
            }
        }

        return $data;
    }
}