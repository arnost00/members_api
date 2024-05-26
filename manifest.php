<?php

namespace Manifest;

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
    
    // firebase config file or null for disabled
    public static $firebase_config = __DIR__ . "/.secrets/your-adminsdk-file.json";

    // directory where to put log files
    // only allowed when club `is_release` is `false`
    // ends with slash or null for disabled
    public static $logging_directory = __DIR__ . "/.logging/";
    
    public static $versions_directory = __DIR__ . "/index/versions/";
}
