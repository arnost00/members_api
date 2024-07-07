<?php

namespace Manifest;

class Manifest {
    // allowed clubs
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
    public static $firebase_adminsdk = __DIR__ . "/.secrets/your-adminsdk-file.json";
    
    // where current firebase token should be stored
    public static $firebase_tokens = __DIR__ . "/.secrets/orientacny-beh-firebase-tokens.json";

    // must NOT have trailing slash
    public static $logging_directory = __DIR__ . "/.logging";
    public static $versions_directory = __DIR__ . "/index/versions";
    public static $core_directory = __DIR__ . "/core";
}
