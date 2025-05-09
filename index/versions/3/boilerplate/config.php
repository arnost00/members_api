<?php

namespace ApiTwo;

require_once __DIR__ . "/database.php";

class Feed {
    // all tables where data should be fed by Clubs
    public static $feed = [
        Config::class,
        Tables::class,
        Enums::class,
        Uc::class,
        Globals::class,
    ];
}

class Config {
    // _cfg.php
    public static $g_dbserver;
    public static $g_dbport;
    public static $g_dbuser;
    public static $g_dbpass;
    public static $g_dbname;
    public static $g_enable_notify;
    public static $g_jwt_secret_key;
    public static $g_shortcut;
    public static $g_fullname;
    public static $g_www_title;
    public static $g_www_name;
    public static $g_www_meta_description;
    public static $g_www_meta_keyword;
    public static $g_baseadr;
    public static $g_mainwww;
    public static $g_log_loginfailed;
    public static $g_is_release;
    public static $g_emailadr;
    public static $g_mail_reply_to;
    public static $g_mail_from;
    public static $g_mail_smtp_host;
    public static $g_mail_smtp_user;
    public static $g_mail_smtp_pswd;
    public static $g_mail_smtp_port;
    public static $g_mail_smtp_auth;
    public static $g_mail_smtp_secure;
    public static $g_mail_in_public_directory;
    public static $g_enable_race_boss;
    public static $g_club_logo;
    public static $g_enable_mailinfo;
    public static $g_mailinfo_minimal_daysbefore;
    public static $g_mailinfo_maximal_daysbefore;
    public static $g_color_profile;
    public static $g_enable_finances;
    public static $g_enable_finances_claim;
    public static $g_finances_race_list_sort_old;
    public static $g_enable_race_transport;
    public static $g_race_transport_default;
    public static $g_enable_race_accommodation;
    public static $g_race_accommodation_default;
    // public static $g_external_is_connector; // disabled as it is not used in api and not all clubs uses it
    public static $g_custom_entry_list_text;
}

class Enums {
    // session_enums.php
    public static $g_www_admin_id;
    public static $_CURR_SESS_ID_;
    public static $_MNG_BIG_INT_VALUE_;
    public static $_MNG_SMALL_INT_VALUE_;
    public static $_USER_GROUP_ID_;
    public static $_MANAGER_GROUP_ID_;
    public static $_SMALL_MANAGER_GROUP_ID_;
    public static $_REGISTRATOR_GROUP_ID_;
    public static $_SMALL_ADMIN_GROUP_ID_;
    public static $_ADMIN_GROUP_ID_;
    public static $_FINANCE_GROUP_ID_;
    public static $_VAR_USER_LOGIN;
    public static $_VAR_USER_PASS;

    // race_enums.php
    public static $g_zebricek;
    public static $g_zebricek_cnt;
    public static $g_racetype;
    public static $g_racetype_cnt;
    public static $g_modify_flag;
    public static $g_modify_flag_cnt;
    public static $g_racetype0;
    public static $g_racetype0_idx;
    public static $g_racetype0_cnt;

    // Volby pro sdilenou dopravu
    public static $g_sedadel_cnt;
    public static $g_fin_mail_flag;
    public static $g_fin_mail_flag_cnt;
    public static $g_notify_type_flag;
    public static $g_notify_type_flag_cnt;
}

class Tables {
    // _tables.php
    public static $TBL_RACE;
    public static $TBL_NEWS;
    public static $TBL_USER;
    public static $TBL_ACCOUNT;
    public static $TBL_USXUS;
    public static $TBL_ZAVXUS;
    public static $TBL_MODLOG;
    public static $TBL_MAILINFO;
    public static $TBL_FINANCE;
    public static $TBL_CLAIM;
    public static $TBL_FINANCE_TYPES;
    public static $TBL_CATEGORIES_PREDEF;
    public static $TBL_TOKENS;
}

class UC {
    // _uc.php
    public static $g_is_system_running;
    public static $g_uc_finnish;
}

class Globals {
    // _globals.php
    public static $GC_SHOW_RACE_DAYS;
    public static $GC_SHOW_REG_DAYS;
    public static $GC_SHOW_RACE_AND_REG_DAYS;
    public static $GC_INTERNAL_NEWS_CNT_LIMIT;
    public static $CG_INTERNAL_NEWS_DAYS_LIMIT;
    public static $GC_NEWS_LIMIT;
    public static $GC_NEWS_MAX_TEXT_LENGTH;
    public static $GC_SHOW_AGE_LIMIT;
    public static $GC_MIN_RACES_2_SHOW_LINK;

    // cron - define only when debug (block email send)
    // public static $_CRON_DEBUG_SEND_;

    // bez prihlaseni neni nic videt
    //public static $GC_NOTHING_VISIBLE_WO_LOGIN;

    public static $GC_KATEG_W;
    public static $GC_KATEG_M;
    public static $GC_DEFAULT_NATIONALITY;
}
