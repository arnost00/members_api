<?php

class Manifest {
    public static $available_clubs = [
        "abm",
        "mbm",
        "pbm",
        "tbm",
        //  "ubm",
        "zbm",
        "spe",
        "spt",
    ];

    // path for each club, for example:
    // $path_to_clubs . $clubname . "/cfg/_cfg.php";
    public static $path_to_clubs = __DIR__ . "/../";

    // firebase adminsdk file from firebase
    public static $firebase_adminsdk = __DIR__ . "/.secrets/orientacny-beh-firebase-adminsdk-5cqvu-4f9fd5b2da.json";

    // where current firebase token should be stored
    public static $firebase_tokens = __DIR__ . "/.secrets/orientacny-beh-firebase-tokens.json";

    // must NOT have trailing slash
    public static $logging_directory = __DIR__ . "/.logging";
    public static $versions_directory = __DIR__ . "/index/versions";
    public static $core_directory = __DIR__ . "/core";
    public static $libraries_directory = __DIR__ . "/libraries";

    // url address to assets directory
    // must NOT have trailing slash
    public static $assets_url = "/api/debug/assets";
}
