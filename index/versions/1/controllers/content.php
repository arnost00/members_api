<?php

namespace Controllers;

use Pecee\SimpleRouter\Exceptions\HttpException;
use Manifest\Manifest;

require_once Manifest::$core_directory . "/api.php";

use Core\Api;

class NotifyEvents {
    /**
     * No action happens.
     */
    static $BASIC = 0;

    /**
     * Should be used with `race_id` to open the correct race.
     */
    static $RACE = 1;
}

class NotifyContent {
    public string $title = "";
    public string $body = "";

    // hex code required
    public ?string $color = "#f76d1c";

    // maximum 1 MB
    public ?string $image = null;

    public ?int $event = null;
    public array $data = [];

    public function __construct(string $title, string $body = "") {
        $this->title = $title;
        $this->body = $body;
    }

    public function race(int $race_id) {
        $this->event = NotifyEvents::$RACE;
        $this->data = [
            ...$this->data,
            "race_id" => $race_id,
        ];
    }

    public function export(): array {
        $data = $this->data;
        $data["event"] = $this->event ?? NotifyEvents::$BASIC;

        foreach ($data as &$value) {
            $value = (string)$value;
        } unset($value);

        return [
            "message" => [
                "topic" => request()->current->clubname,
                "notification" => [
                    "title" => $this->title,
                    "body" => $this->body,
                ],
                "android" => [
                    "notification" => [
                        "image" => $this->image,
                        "color" => $this->color,
                    ],
                ],
                "data" => $data,
            ],
        ];
    }
}

class NotifyGenerator {
    public static function race_changed($race_id) {
        $output = Api::database()->fetch_assoc("SELECT `nazev` FROM `" . Tables::$RACE . "` WHERE `id` = ? LIMIT 1", $race_id);
        
        if ($output === null) {
            throw new HttpException("The race you are looking for does not exists.", 404);
            return;
        }
        
        $title = "Zmena - " . $output["nazev"];
        $body = "V tejto udalosti boli vykonané zmeny. Kliknutím zobrazíte podrobnosti.";

        $notify = new NotifyContent($title, $body);
        $notify->race($race_id);

        return $notify->export();
    }
}