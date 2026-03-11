<?php

namespace ApiTwo;

require_once \Manifest::$libraries_directory . "/autoload_PHPMailer.php";
require_once __DIR__ . "/../../boilerplate/database.php";
require_once __DIR__ . "/../../boilerplate/notify.php";
require_once __DIR__ . "/../../boilerplate/config.php";
require_once __DIR__ . "/../../boilerplate/utils.php";
require_once __DIR__ . "/blocks.php";

use Core\Logging;
use PHPMailer\PHPMailer\PHPMailer;

class Cron {
    public static function start() {
        response()->json(static::mailinfo_notify());
    }

    public static function mailinfo_notify() {
        $timestamp = time();
        $problems = 0;
        $subscribers = Database::fetch_assoc_all("SELECT * FROM " . Tables::$TBL_MAILINFO . " WHERE notify_type != 0 ORDER BY `id`");

        if (!$subscribers) {
            return [
                "processed_in_seconds" => time() - $timestamp,
                "problems" => $problems,
            ];
        }

        $tokens = Database::query("
            SELECT
                mailinfo.id_user,
                tokens.fcm_token,
                tokens.device
            FROM `" . Tables::$TBL_MAILINFO . "` AS mailinfo
            LEFT JOIN  `" . Tables::$TBL_TOKENS . "` AS tokens ON mailinfo.id_user = tokens.user_id
            WHERE (mailinfo.notify_type & ?) != 0;
        ", Enums::$g_notify_type_flag[1]["id"]);

        $mail = new PHPMailer(true);
        $mail->Host = Config::$g_mail_smtp_host;
        $mail->Username = Config::$g_mail_smtp_user;
        $mail->Password = Config::$g_mail_smtp_pswd;
        $mail->Port = Config::$g_mail_smtp_port;
        $mail->SMTPAuth = Config::$g_mail_smtp_auth;
        $mail->SMTPSecure = Config::$g_mail_smtp_secure;

        $mail->isSMTP();
        $mail->isHTML();
        $mail->setFrom(Config::$g_mail_from, Config::$g_fullname);
        $mail->addReplyTo(Config::$g_mail_reply_to, Config::$g_fullname);
        $mail->CharSet = PHPMailer::CHARSET_UTF8;
        $mail->Subject = "Informace o oddílových přihláškách";

        // build notify queue
        // we can't send notifications immediately, because $tokens are not indexed by $user_id
        $notify_queue = [];

        foreach ($subscribers as $subscriber) {
            try {
                $user_id = $subscriber["id_user"];
                $news = new ContentNewsBlock($subscriber);
                $races = new ContentRacesBlock($subscriber);
                $finance = new ContentFinanceBlock($subscriber);

                if ($news->is_empty() && $races->is_empty() && $finance->is_empty()) {
                    continue;
                }

                if ($subscriber["email"] && ($subscriber["notify_type"] & Enums::$g_notify_type_flag[0]["id"]) !== 0) {
                    $body = "<!DOCTYPE html><html><body>";
                    $body .= "<h2>Vybrané informace o termínech a změnách v příhláškovém systému " . Config::$g_shortcut . "</h2>\n";
                    $body .= "<hr />";

                    $body .= $news->export_mail();
                    $body .= $races->export_mail();
                    $body .= $finance->export_mail();

                    $body .= "<hr />\n";
                    $body .= "<p>Vygenerováno dne " . Utils::formatTimestamps(Utils::getCurrentDate()) . "</p>\n";
                    $body .= "<p>Změnu a případné zrušení zasílaných informací provedete přes přihláškový systém oddílu " . Config::$g_shortcut . ".</p>\n";
                    $body .= "<p>Nejlépe přímo na adrese <a href='" . Config::$g_baseadr . "'>" . Config::$g_baseadr . "</a></p>\n";
                    $body .= "</body></html>";

                    $mail->clearAddresses();
                    $mail->addAddress($subscriber["email"]);
                    $mail->Body = $body;
                    $mail->send();
                }

                if (($subscriber["notify_type"] & Enums::$g_notify_type_flag[1]["id"]) !== 0) {
                    print_r("wants notify", $subscriber["id_user"]);
                    $notify_queue[$subscriber["id_user"]] = array_merge(
                        $news->export_notify(),
                        $races->export_notify(),
                        $finance->export_notify(),
                    );
                }
            } catch (\Throwable $exception) {
                $problems++;
                Logging::exception($exception, "mail:" . $subscriber["id_user"]);
            }
        }

        foreach ($tokens as ["id_user" => $user_id, "fcm_token" => $token, "device" => $device]) {
            try {
                if ($token === null) {
                    $problems++;
                    Logging::warning("User $user_id wants notifications, but no fcm_token is provided.");
                    continue;
                }

                if (!isset($notify_queue[$user_id])) {
                    continue;
                }

                print("sending notify for " . $user_id);
                print_r($notify_queue[$user_id]);

                foreach ($notify_queue[$user_id] as $content) {
                    // content have to be passed as an NotifyContent instance so we can set the token
                    $content->token($token);
                    Notifications::send($content->export());
                }
            } catch (\Throwable $exception) {
                print("problematic user " . $user_id);
                $problems++;
                Logging::exception($exception, "notify:" . $user_id);
            }
        }

        // mark as processed
        Database::query("UPDATE `" . Tables::$TBL_RACE . "` SET `modify_flag` = 0");
        Database::query("UPDATE `" . Tables::$TBL_NEWS . "` SET `modify_flag` = 0");

        return [
            "processed_in_seconds" => time() - $timestamp,
            "problems" => $problems,
        ];
    }
}
