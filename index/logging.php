<?php

use Pecee\SimpleRouter\SimpleRouter as Router;
use Core\Logging;

if (!isset($_SERVER["PHP_AUTH_USER"])) {
    http_response_code(401);
    header('WWW-Authenticate: Basic realm="Logging"');

    die("This site is protected");
}

// sorry
// baked in password is in .secrets
if (!password_verify($_SERVER["PHP_AUTH_USER"] . ":" . $_SERVER["PHP_AUTH_PW"], '$2y$10$IB08YWlrS8uJJSH9Fm/e/.BRz7JjBYCncHGlTMWc9.KZ0JVMyqp7q')) {
    http_response_code(401);
    header('WWW-Authenticate: Basic realm="Logging"');
    die("Invalid credentials");
};

echo "<!DOCTYPE><html><head><link rel='stylesheet' href='" . \Manifest::$assets_url . "/exceptions.css' /></head><body>";

Router::get("/", function () {
    echo "<h1>Log viewer</h1>";
    echo "<p>Show logs for date: <code>/api/logging/" . Logging::$file_format . "</code></p>";
    echo "<p>Show logs for <a href='/api/logging/" . date(Logging::$file_format) . "'>today</a></p>";
});

Router::get("/{date}", function ($date) {
    $filepath = Manifest::$logging_directory . "/" . date_format(date_create_from_format(Logging::$file_format, $date), Logging::$year_format . "/" . Logging::$month_format . "/" . Logging::$file_format) . ".log";

    try {
        $parsed_log = Logging::parse($filepath);
    } catch (\Throwable $e) {
        return "Could not open log for <code>$date<code>";
    }

    echo "<p>Showing results for <code>$filepath</code></p>";

    foreach ($parsed_log as ["clubname" => $path, "data" => $data, "ip" => $ip, "level" => $level, "timestamp" => $timestamp]) {
        $parsed_data = json_decode($data, true);

        if (!$parsed_data) {
            echo "<details><summary>$level - $timestamp<span>$path</span></summary><pre>";
            echo $data;
        } else {
            $marker = $parsed_data["marker"] ?? "";
            $message = $parsed_data["message"] ?? "";
            $log_message = $parsed_data["log_message"] ?? "";
            $status_code = $parsed_data["code"] ?? "";
            $line = $parsed_data["line"] ?? null;
            $file = $parsed_data["file"] ?? null;
            $trace = $parsed_data["trace"] ?? [];

            echo "<details><summary>$level - $timestamp: <em>$message</em> - <span>$path</span></summary>";
            echo "<table style='margin: 1em;'><tbody>";
            echo "<tr><td>User Message</td><td><em>" . htmlspecialchars($message) . "</em></td></tr>";
            echo "<tr><td>Log Message</td><td><b>" . htmlspecialchars($log_message) . "</b></td></tr>";
            echo "<tr><td>Level</td><td>" . htmlspecialchars($level) . "</td></tr>";
            echo "<tr><td>Timestamp</td><td>" . htmlspecialchars($timestamp) . "</td></tr>";
            echo "<tr><td>Status code</td><td>" . htmlspecialchars($status_code) . "</td></tr>";
            echo "<tr><td>IP</td><td>" . htmlspecialchars($ip) . "</td></tr>";
            echo "<tr><td>Requested path</td><td><code>" . htmlspecialchars($path) . "</code></td></tr>";
            echo "<tr><td>Marker</td><td><code>" . htmlspecialchars($marker) . "</code></td></tr>";
            echo "<tr><td>Line</td><td><code>" . htmlspecialchars($line) . "</code></td></tr>";
            echo "<tr><td>File</td><td><code>" . htmlspecialchars($file) . "</code></td></tr>";
            echo "</tbody></table>";

            if (count($trace) == 0) {
                echo "<em>No stacktrace recorded</em>";
            } else {
                echo "<pre>";
                foreach ($trace as $stack) {
                    $stack_file = $stack["file"] ?? $file;
                    $stack_line = $stack["line"] ?? $line;

                    echo "<p>" . dirname($stack_file) . "/<b>" . basename($stack_file) . "</b>:$stack_line</p>";
                    if (isset($stack["class"]) && isset($stack["type"])) {
                        echo "<b>" . $stack["class"] . $stack["type"] . "</b>";
                    }

                    echo "<em>" . $stack["function"] . "</em>(";
                    echo json_encode($stack["args"], JSON_PRETTY_PRINT);
                    echo ")\n";
                }
                echo "</pre>";
            }
        }

        echo "</pre></details>";
    }
});
