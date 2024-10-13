<?php

namespace Core;

require_once __DIR__ . "/logging.php";

use Core\Logging;
use Pecee\SimpleRouter\Exceptions\HttpException;

// $content = [
//     "code" => $error->getCode(),
//     "message" => $error->getMessage(),
//     // debug
//     "method" => $_SERVER["REQUEST_METHOD"],
//     // PATH_INFO sometimes fails
//     "path" => @$_SERVER["PATH_INFO"],
//     "input" => input()->all(),
//     "auth" => request()->getHeader("authorization"),
//     "line" => $error->getLine(),
//     "file" => $error->getFile(),
//     "stacktrace" => $error->getTrace(),
// ];

class ApiCrashReport {
    public static function init() {
        set_exception_handler([static::class, "exception_handler"]);
        set_error_handler([static::class, "error_handler"]);
    }

    public static function exception_handler(\Throwable $exception) {
        $status_code = $exception->getCode();

        if (($exception instanceof ApiException == false && $exception instanceof HttpException == false) || $status_code < 400 || $status_code > 599) {
            // default status code when out of range
            $status_code = 500;
        }

        // the warning is fine, we do check the method exists
        $log_message = method_exists($exception, "getLogMessage") ? $exception->getLogMessage() : $exception->getMessage();

        http_response_code($status_code);

        $content = [
            "code" => $status_code,
            "message" => $exception->getMessage(),
        ];

        $content_debug = [
            "log_message" => $log_message,
            "method" => $_SERVER["REQUEST_METHOD"],
            "path" => $_SERVER["REQUEST_URI"],
            "line" => $exception->getLine(),
            "file" => $exception->getFile(),
        ];

        if (request()->debug) {
            $content += $content_debug;
        }

        // write a log entry
        Logging::error(json_encode($content + $content_debug));
        
        if (request()->isFormatAccepted("application/json")) {
            try {
                response()->json($content, JSON_THROW_ON_ERROR);
            } catch (\JsonException $e) {
                Logging::error(json_encode(["code" => "could not json_encode response: " . $e->getMessage()]));
                response()->json(["code" => 500, "message" => "Could not send response."]);
            }
        } else {
            echo "<html><header><title>" . $exception->getMessage() . "</title></header><link rel='stylesheet' href='" . \Manifest::$assets_url . "/exceptions.css' /><body>";
            echo "<h2>Uncaught exception</h2><h3><code><em>" . get_class($exception) . "</em>: " . $exception->getMessage() . "</code></h3>";

            if (request()->debug) {
                $space_around = 2;

                echo "<details open><summary>Stacktrace</summary><pre>";
                foreach ($exception->getTrace() as $index => $child) {
                    $file = isset($child["file"]) ? $child["file"] : $exception->getFile();
                    $line = isset($child["line"]) ? $child["line"] : $exception->getLine();

                    echo "<p><i>$index:</i> " . dirname($file) . "/<b>" . basename($file) . "</b></p>";

                    try {
                        $data = explode("\n", file_get_contents($file), $line + $space_around + 1);
                        $data = array_slice($data, $line > $space_around ? $line - $space_around - 1: 0, $line > $space_around ? $space_around * 2 + 1 : $line + $space_around, true);
                        
                        foreach ($data as $current => $value) {
                            // highlight error line
                            if ($current + 1 === $line) echo "<em>";
                            
                            echo "<i>$current:</i> " . (trim($value) === "" ? "<span>(empty line)</span>" : htmlentities($value)) . "\n";
                            
                            // highlight error line
                            if ($current + 1 === $line) {
                                $padding = str_repeat(" ", strlen($current));
                                
                                echo "</em>$padding<em>></em> ";
                                
                                // show additional message
                                if (isset($child["class"]) && isset($child["type"])) echo $child["class"] . $child["type"];
                                
                                echo "<b>" . $child["function"] . "</b>(";
                                
                                if (isset($child["args"][1])) echo join("\n$padding<em>></em> ", explode("\n", htmlentities(json_encode($child["args"][1], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR))));
                                    
                                echo ");\n\n";
                                    
                            };
                        }

                    } catch (\Throwable $e) {
                        echo "<em>?: Could not display code preview: " . $e->getMessage() . "</em>\n";
                        echo " <em>></em> line " . $e->getLine() . " in " . dirname($e->getFile()) . "/<b>" . basename($e->getFile()) . "</b></b>\n";
                    }
                    
                    if ($index === 0) echo "\n<hr />";
                }
                echo "</pre></details>";
            }

            echo "<details><summary>HTTP Request</summary>";
            echo "<pre>" . $_SERVER["REQUEST_METHOD"] . " " . $_SERVER["REQUEST_URI"] . " " . $_SERVER["SERVER_PROTOCOL"] . "\n";
            foreach (getallheaders() as $key => $value) {
                echo "$key: <b>$value</b>\n";
            }
            echo "</pre></details>";

            echo "<details><summary>HTTP Response</summary>";
            echo "<pre>" . $_SERVER["SERVER_PROTOCOL"] . " $status_code\n";
            foreach (headers_list() as $header) {
                [$key, $value] = explode(": ", $header, 2);
                echo "$key: <b>$value</b>\n";
            }
            echo "</pre></details>";

            echo "<details><summary>HTTP JSON Response</summary><pre>";
            echo json_encode($content, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
            echo "</pre></details>";
            
            if (!request()->debug) {
                echo "<p>For more debugging information, use test clubs or set <code>request()->debug = true;</code>.</p>";
            }

            echo "<p>This report was generated by <code>" . static::class . "</code>. Use header <code>Accept: application/json;</code> to display json only response.</p>";
    
            echo "</body></html>";
        }
    
        exit;
    }

    public static function error_handler($errno, $errstr, $errfile, $errline) {
        $exception = new \ErrorException($errstr, 0, $errno, $errfile, $errline);

        if (!(error_reporting() & $errno) || $errno === E_DEPRECATED || $errno === E_USER_DEPRECATED) {
            Logging::exception($exception);
            return;
        }

        throw $exception;

    }
}

class ApiException extends \Exception {
    protected $logMessage;

    public function __construct($message, $code = 500, $logMessage = null, \Throwable $previous = null) {
        if ($code < 400 || $code > 599) {
            throw new \Exception("status code for exception must follow http error codes");
        }
        
        parent::__construct($message, $code, $previous);
        $this->logMessage = $logMessage ?? $message;
    }

    public function getLogMessage() {
        return $this->logMessage;
    }
}

class ApiUndefinedKeyException extends ApiException {
    public function __construct($key, $code = 400, \Throwable $previous = null) {
        parent::__construct("Key '$key' was not found in the request.", $code = $code, $previous = $previous);
    }
}