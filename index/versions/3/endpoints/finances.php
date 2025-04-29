<?php

namespace ApiTwo;

use Pecee\SimpleRouter\SimpleRouter as Router;

require_once __DIR__ . "/../boilerplate/middlewares.php";
require_once __DIR__ . "/../boilerplate/session.php";
require_once __DIR__ . "/../boilerplate/utils.php";
require_once __DIR__ . "/../boilerplate/database.php";
require_once __DIR__ . "/../boilerplate/endpoint.php";
require_once __DIR__ . "/../boilerplate/notify.php";
require_once __DIR__ . "/../boilerplate/config.php";
require_once __DIR__ . "/../boilerplate/input.php";

use Core\ApiException;
use Core\ApiUndefinedKeyException;

class Finances implements Endpoint {
    public static function init(): void {
        Router::partialGroup("/finances", function () {
            Router::get("/test", [static::class, "test"]);
            Router::group(["middleware" => AuthRequired::class], function () {
                $fin_id = ["fin_id" => "[0-9]+"];

                Router::get("/", [static::class, "overview"]);
                Router::get("/history", [static::class, "history"]);
                Router::get("/{fin_id}", [static::class, "detail"])->where($fin_id);
                Router::get("/{fin_id}/claim/history", [static::class, "claim_history"])->where($fin_id);
                Router::post("/{fin_id}/claim/message", [static::class, "claim_message"])->where($fin_id);
                Router::post("/{fin_id}/claim/close", [static::class, "claim_close"])->where($fin_id);
                Router::post("/{fin_id}", [static::class, "payment_update"])->where($fin_id);
                Router::delete("/{fin_id}", [static::class, "payment_delete"])->where($fin_id);
                Router::post("/import", [static::class, "payments_import"]);
            });
        });
    }

    public static function overview() {
        $result = Database::fetch_assoc_all(
            "SELECT
                user.id AS `user_id`,
                user.sort_name AS `sort_name`,
                CAST(IFNULL(SUM(fin.amount), 0) AS SIGNED) AS `total`
            FROM `" . Tables::$TBL_USER . "` user
            LEFT JOIN `" . Tables::$TBL_FINANCE . "` AS fin ON user.id = fin.id_users_user
            WHERE fin.storno IS NULL AND (fin.id_users_user = ? OR user.chief_pay = ?)
            GROUP BY user.id",
            Session::$user_id,
            Session::$user_id
        );

        response()->json($result);
    }

    public static function history() {
        $history = Database::fetch_assoc_all(
            "SELECT
                fin.id AS `fin_id`,
                fin.id_users_editor AS `editor_user_id`,
                editor.sort_name AS `editor_sort_name`,
                user.id AS `user_id`,
                user.sort_name AS `user_sort_name`,
                race.nazev AS `race_name`,
                race.cancelled AS `race_cancelled`,
                from_unixtime(race.datum) AS `race_date`,
                fin.note,
                fin.amount,
                fin.date,
                fin.storno,
                fin.storno_by AS `storno_user_id`,
                fin.storno_date,
                fin.storno_note,
                fin.claim
            FROM `" . Tables::$TBL_FINANCE . "` AS fin 
            LEFT JOIN `" . Tables::$TBL_USER . "` editor ON fin.id_users_editor = editor.id
            LEFT JOIN `" . Tables::$TBL_USER . "` user ON fin.id_users_user = user.id
            LEFT JOIN `" . Tables::$TBL_RACE . "` race ON fin.id_zavod = race.id
            WHERE (fin.id_users_user = ? OR user.chief_pay = ?) 
              AND fin.storno IS NULL 
            ORDER BY fin.date DESC, fin.id DESC",
            Session::$user_id,
            Session::$user_id
        );

        response()->json($history);
    }

    public static function detail($fin_id) {
        $result = Database::fetch_assoc(
            "SELECT
                fin.id AS `fin_id`,
                fin.id_users_editor AS `editor_user_id`,
                editor.sort_name AS `editor_sort_name`,
                user.id AS `user_id`,
                user.sort_name AS `user_sort_name`,
                user.chief_pay,
                fin.id_zavod AS `race_id`,
                race.nazev AS `race_name`,
                race.cancelled AS `race_cancelled`,
                from_unixtime(race.datum) AS `race_date`,
                fin.note,
                fin.amount,
                fin.date,
                fin.storno,
                fin.storno_by AS `storno_user_id`,
                storno.sort_name AS `storno_sort_name`,
                fin.storno_date,
                fin.storno_note,
                fin.claim
            FROM `" . Tables::$TBL_FINANCE . "` AS fin 
            LEFT JOIN `" . Tables::$TBL_USER . "` editor ON fin.id_users_editor = editor.id
            LEFT JOIN `" . Tables::$TBL_USER . "` user ON fin.id_users_user = user.id
            LEFT JOIN `" . Tables::$TBL_USER . "` storno ON fin.storno_by = storno.id
            LEFT JOIN `" . Tables::$TBL_RACE . "` race ON fin.id_zavod = race.id
            WHERE fin.id = ?
            ORDER BY fin.date DESC, fin.id DESC",
            $fin_id
        );

        if (($result["user_id"] !== Session::$user_id && $result["chief_pay"] !== Session::$user_id) && !Session::all(Session::$MASK_FIN)) {
            throw new ApiException("This transaction is not yours and you do not have finance permissions.", 403);
        }

        response()->json($result);
    }

    public static function claim_history($fin_id) {
        $result = Database::fetch_assoc_all(
            "SELECT
                claim.id AS claim_id,
                claim.user_id,
                claim.payment_id,
                claim.text,
                claim.date,
                user.sort_name
            FROM `" . Tables::$TBL_CLAIM . "` AS claim
            LEFT JOIN `" . Tables::$TBL_USER . "` AS user ON user.id = claim.user_id
            WHERE claim.payment_id = ?
            ORDER BY claim.date DESC",
            $fin_id
        );

        response()->json($result);
    }

    public static function claim_message($fin_id) {
        $message = Input::key("message", required: true);

        $result = Database::fetch_assoc("SELECT id, user_id FROM `" . Tables::$TBL_CLAIM . "` WHERE payment_id = ? ORDER BY date DESC LIMIT 1", $fin_id);

        if ($result !== null && $result["user_id"] === Session::$user_id) {
            Database::query("UPDATE `" . Tables::$TBL_CLAIM . "` SET text = ? WHERE id = ?", $message, $result["id"]);
        } else {
            Database::query("INSERT INTO `" . Tables::$TBL_CLAIM . "` (user_id, payment_id, text) VALUES (?, ?, ?)", Session::$user_id, $fin_id, $message);
        }

        Database::query("UPDATE `" . Tables::$TBL_FINANCE . "` SET claim = 1 WHERE id = ?", $fin_id);
    }

    public static function claim_close($fin_id) {
        Database::query("UPDATE `" . Tables::$TBL_FINANCE . "` SET claim = 0 WHERE id = ?", $fin_id);
    }

    public static function payment_update($fin_id) {
        if (!Session::all(Session::$MASK_FIN)) {
            throw new ApiException("You must have big manager and finance permissions to proceed.", 403);
        }

        $input = new Input();
        $input->add("editor_user_id", "id_users_editor", filter: Input::$FILTER_UINT);
        $input->add("user_id", "id_users_user", filter: Input::$FILTER_UINT);
        $input->add("race_id", "id_zavod", filter: Input::$FILTER_UINT);
        $input->add("amount", filter: Input::$FILTER_INT);
        $input->add("date", filter: Input::$FILTER_ISO_DATE);
        $input->add("note");
        $input->add("storno", filter: Input::$FILTER_BOOL, nullable: true);
        $input->add("storno_by", filter: Input::$FILTER_UINT);
        $input->add("storno_date", filter: Input::$FILTER_ISO_DATE);
        $input->add("storno_note");
        $input->add("claim", filter: Input::$FILTER_BOOL, nullable: true);
        $input = $input->collect();

        // storno column uses two values: 1 and null
        if (isset($input["storno"]) && !$input["storno"]) {
            $input["storno"] = null;
        }

        Database::update(Tables::$TBL_FINANCE, $input, "WHERE id = ?", $fin_id);
        ModifyLog::edit(Tables::$TBL_FINANCE, "id=$fin_id|" . join("|", array_map(fn($key, $val) => "$key=$val", array_keys($input), $input)));
    }

    public static function payment_delete($fin_id) {
        if (!Session::all(Session::$MASK_MNG_BIG | Session::$MASK_FIN)) {
            throw new ApiException("You must have big manager and finance permissions to proceed.", 403);
        }

        Database::query("DELETE FROM `" . Tables::$TBL_FINANCE . "` WHERE id = ?", $fin_id);
        ModifyLog::delete(Tables::$TBL_FINANCE, "id=$fin_id");
    }

    public static function payments_import() {
        if (!Session::all(Session::$MASK_MNG_BIG | Session::$MASK_FIN)) {
            throw new ApiException("You must have big manager and finance permissions to proceed.", 403);
        }

        $array = Input::keys();

        // report each payment to track possible partial success
        $result = [];

        foreach ($array as $child) {
            try {
                Input::validate($child["user_id"], Input::$FILTER_UINT);
                Input::validate($child["race_id"], Input::$FILTER_UINT);
                Input::validate($child["amount"], Input::$FILTER_INT);
                Input::validate($child["date"], Input::$FILTER_ISO_DATE);

                if (!isset($child["note"])) {
                    throw new ApiUndefinedKeyException("note");
                }

                if (!isset($child["editor_user_id"])) {
                    $child["editor_user_id"] = Session::$user_id;
                }

                Database::insert(Tables::$TBL_FINANCE, [
                    "id_users_editor" => $child["editor_user_id"],
                    "id_users_user" => $child["user_id"],
                    "amount" => $child["amount"],
                    "note" => $child["note"],
                    "date" => $child["date"],
                    "race_id" => $child["race_id"],
                ]);

                $fin_id = Database::mysqli()->insert_id;

                ModifyLog::add(Tables::$TBL_FINANCE, "id=$fin_id|user_id=" . $child["user_id"] . "|amount=" . $child["amount"]);

                $result[] = [
                    "ok" => true,
                    "id" => $fin_id,
                ];
            } catch (\Throwable $error) {
                $result[] = [
                    "ok" => false,
                    "id" => null,
                    "error" => $error->getMessage(),
                ];
            }
        }

        response()->json($result);
    }
}
