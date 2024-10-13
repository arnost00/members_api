<?php

namespace ApiTwo;

require_once \Manifest::$libraries_directory . "/autoload_PHPMailer.php";
require_once __DIR__ . "/../../boilerplate/database.php";
require_once __DIR__ . "/../../boilerplate/notify.php";
require_once __DIR__ . "/../../boilerplate/config.php";
require_once __DIR__ . "/../../boilerplate/utils.php";
require_once __DIR__ . "/blocks.php";

use PHPMailer\PHPMailer\PHPMailer;

class Cron {
    public static function start() {
        response()->json(static::mailinfo_notify());
    }

    public static function mailinfo_notify() {
        $subscribers = Database::fetch_assoc_all("SELECT * FROM " . Tables::$TBL_MAILINFO . " ORDER BY `id`");
        
        if (count($subscribers) === 0) {
            return;
        }

        $tokens = Database::fetch_assoc_all("SELECT `mailinfo`.`id_user`, `tokens`.`fcm_token` FROM `" . Tables::$TBL_MAILINFO . "` AS `mailinfo` LEFT JOIN  `" . Tables::$TBL_TOKENS . "` AS `tokens` ON `mailinfo`.`id_user` = `tokens`.`user_id` WHERE (`mailinfo`.`notify_type` & ?) != 0;", Enums::$g_notify_type_flag[1]["id"]);

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

        $mail_queue = [];
        $notify_queue = [];
        
        foreach ($subscribers as $subscriber) {
            $news = new ContentNewsBlock($subscriber);
            $races = new ContentRacesBlock($subscriber);
            $finance = new ContentFinanceBlock($subscriber);

            if ($news->is_empty() && $races->is_empty() && $finance->is_empty()) {
                continue;
            }

            if (($subscriber["notify_type"] & Enums::$g_notify_type_flag[0]["id"]) !== 0) {
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

                $mail_queue[$subscriber["email"]] = $body;
            }

            if (($subscriber["notify_type"] & Enums::$g_notify_type_flag[1]["id"]) !== 0) {
                $notify_queue[$subscriber["id_user"]] = array_merge(
                    $news->export_notify(),
                    $races->export_notify(),
                    $finance->export_notify(),
                );
            }
        }

        foreach ($mail_queue as $email => $body) {
            $mail->clearAddresses();
            $mail->addAddress($email);
            $mail->Body = $body;
            $mail->send();
        }

        $report = [];
        $response = [];
        
        foreach ($tokens as ["id_user" => $user_id, "fcm_token" => $token]) {
            if ($token === null) {
                $report[] = "Missing token for '$user_id'.";
                continue;
            }

            if (!isset($notify_queue[$user_id]) || empty($notify_queue[$user_id])) {
                continue;
            }

            foreach ($notify_queue[$user_id] as $content) {
                // content have to be passed as an NotifyContent instance so we can set the token
                $content->token($token);
                
                // send notification
                $response[] = Notifications::send($content->export());
            }
        }

        // mark as processed
        Database::query("UPDATE `" . Tables::$TBL_RACE . "` SET `modify_flag` = 0");
        Database::query("UPDATE `" . Tables::$TBL_NEWS . "` SET `modify_flag` = 0");

        return [
            "report" => $report,
            "response" => $response,
        ];
    }
}
