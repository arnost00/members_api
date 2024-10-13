<?php

namespace Controllers;

require_once \Manifest::$core_directory . "/api.php";
require_once \Manifest::$core_directory . "/notify.php";

use Core\Api;
use Core\ApiException;
use Core\NotifyContent;

class NotifyGenerator {
    public static function race_changed($race_id) {
        $output = Api::database()->fetch_assoc("SELECT `nazev` FROM `" . Tables::$RACE . "` WHERE `id` = ? LIMIT 1", $race_id);
        
        if ($output === null) {
            throw new ApiException("The race you are looking for does not exists.", 404);
            return;
        }
        
        $title = "Zmena - " . $output["nazev"];
        $body = "V tejto udalosti boli vykonané zmeny. Kliknutím zobrazíte podrobnosti.";

        $notify = new NotifyContent($title, $body);
        $notify->race($race_id);

        return $notify->export();
    }
}