<?php

// header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
// header("Pragma: no-cache");
echo "Testing cache headers";

// register_shutdown_function(function () {var_dump(headers_list());});

$arr = ["hello" => "ahoy", "hi" => "cau"];

$prepend = ["lol", "lolkjok"];

$res = [...$prepend, ...array_values($arr)];

// var_dump($res);
// var_dump(join($arr));
